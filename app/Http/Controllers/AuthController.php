<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Helpers\ApiResponse;
use App\Models\User;
use App\Services\CosService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

/**
 * @OA\Post(
 *      path="/api/login",
 *      operationId="loginUser",
 *      tags={"Authentication"},
 *      summary="用户登录",
 *      description="通过邮箱和密码进行用户登录",
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"email","password"},
 *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *              @OA\Property(property="password", type="string", format="password", example="password123")
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="登录成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="authenticated", type="boolean", example=true),
 *              @OA\Property(property="user", type="object"),
 *              @OA\Property(property="token", type="string")
 *          )
 *      ),
 *      @OA\Response(response=401, description="认证失败")
 * )
 * 
 * @OA\Post(
 *      path="/api/register",
 *      operationId="registerUser",
 *      tags={"Authentication"},
 *      summary="用户注册",
 *      description="创建一个新的用户账户",
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"name","email","password","password_confirmation"},
 *              @OA\Property(property="name", type="string", example="John Doe"),
 *              @OA\Property(property="email", type="string", format="email", example="user@example.com"),
 *              @OA\Property(property="password", type="string", format="password", example="password123"),
 *              @OA\Property(property="password_confirmation", type="string", format="password", example="password123")
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="注册成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="registered", type="boolean", example=true)
 *          )
 *      ),
 *      @OA\Response(response=422, description="验证错误")
 * )
 * 
 * @OA\Post(
 *      path="/api/logout",
 *      operationId="logoutUser",
 *      tags={"Authentication"},
 *      summary="用户登出",
 *      description="注销当前用户会话",
 *      security={{"bearerAuth":{}}},
 *      @OA\Response(
 *          response=200,
 *          description="登出成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="logged_out", type="boolean", example=true)
 *          )
 *      ),
 *      @OA\Response(response=401, description="认证失败")
 * )
 * 
 * @OA\Post(
 *      path="/api/wechat/login",
 *      operationId="wechatLogin",
 *      tags={"Authentication"},
 *      summary="微信小程序登录",
 *      description="通过微信小程序登录，如果用户不存在则自动注册",
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              required={"code"},
 *              @OA\Property(property="code", type="string", example="081VS80w3JWVyK2a2v0w3CxW80wVS80m"),
 *              @OA\Property(property="user_info", type="object", example={"nickName": "张三", "avatarUrl": "头像地址"})
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="登录成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="authenticated", type="boolean", example=true),
 *              @OA\Property(property="token", type="string"),
 *              @OA\Property(property="user", type="object")
 *          )
 *      ),
 *      @OA\Response(response=401, description="认证失败")
 * )
 *
 * @OA\Post(
 *      path="/api/upload/avatar",
 *      operationId="uploadAvatar",
 *      tags={"Authentication"},
 *      summary="上传用户头像",
 *      description="需要登录。multipart/form-data 上传头像图片（字段名 file 或 avatar），成功后写入 users.wechat_avatar 并返回头像地址；也支持直接提交 avatarUrl / wechat_avatar 字符串（如微信返回的临时头像地址）而不带文件。",
 *      security={{"bearerAuth":{}}},
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\MediaType(
 *              mediaType="multipart/form-data",
 *              @OA\Schema(
 *                  @OA\Property(property="file", type="string", format="binary", description="头像图片（jpg/png/webp，≤2MB）"),
 *                  @OA\Property(property="avatarUrl", type="string", description="不带文件时，直接保存为头像的地址")
 *              )
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="上传成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="code", type="integer", example=0),
 *              @OA\Property(property="message", type="string", example="头像上传成功"),
 *              @OA\Property(property="data", type="object", @OA\Property(property="url", type="string", example="https://cdn.example.com/avatars/xxx.jpg"))
 *          )
 *      ),
 *      @OA\Response(response=401, description="未登录"),
 *      @OA\Response(response=422, description="参数错误")
 * )
 *
 * @OA\Get(
 *      path="/api/account/info",
 *      operationId="accountInfo",
 *      tags={"Authentication"},
 *      summary="获取当前用户资料",
 *      description="需要登录。返回用户对象，头像地址同时挂在 data.url 上，方便小程序直接读取。",
 *      security={{"bearerAuth":{}}},
 *      @OA\Response(
 *          response=200,
 *          description="获取成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="code", type="integer", example=0),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="url", type="string", example="https://cdn.example.com/avatars/xxx.jpg"),
 *                  @OA\Property(property="user", type="object")
 *              )
 *          )
 *      ),
 *      @OA\Response(response=401, description="未登录")
 * )
 *
 * @OA\Put(
 *      path="/api/account/info",
 *      operationId="updateAccountInfo",
 *      tags={"Authentication"},
 *      summary="更新当前用户资料",
 *      description="需要登录。头像只落在 users.wechat_avatar 一个字段：avatarUrl 与 wechat_avatar 等价，任一即可（同时传时以 avatarUrl 为准）。昵称接受 nickname / nickName / name。头像必须是可访问的完整地址——先用 /api/upload/avatar 上传拿到地址，再保存到本接口，微信选图返回的临时路径（wxfile:// 、http://tmp/）无法跨设备访问，会被拒绝。",
 *      security={{"bearerAuth":{}}},
 *      @OA\RequestBody(
 *          required=true,
 *          @OA\JsonContent(
 *              @OA\Property(property="avatarUrl", type="string", example="https://cdn.example.com/avatars/xxx.jpg"),
 *              @OA\Property(property="wechat_avatar", type="string", example="https://cdn.example.com/avatars/xxx.jpg"),
 *              @OA\Property(property="nickname", type="string", example="张三")
 *          )
 *      ),
 *      @OA\Response(
 *          response=200,
 *          description="更新成功",
 *          @OA\JsonContent(
 *              @OA\Property(property="code", type="integer", example=0),
 *              @OA\Property(property="message", type="string", example="资料已更新"),
 *              @OA\Property(property="data", type="object",
 *                  @OA\Property(property="url", type="string"),
 *                  @OA\Property(property="user", type="object")
 *              )
 *          )
 *      ),
 *      @OA\Response(response=401, description="未登录"),
 *      @OA\Response(response=422, description="参数错误")
 * )
 */
class AuthController extends Controller
{
	public function __construct()
	{
        $this->middleware('auth:sanctum')->only([
            'logout',
            'uploadAvatar',
            'accountInfo',
            'updateAccountInfo',
        ]);
	}

    public function register(Request $request)
    {
        $data = $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|between:6,25|confirmed'
        ]);

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => $data['password'],
        ]);

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'registered' => true,
            'api_token' => $token,
            'token' => $token,
            'user' => $user,
        ], '注册成功', 201);
    }

    public function login(Request $request)
    {
        $data = $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|between:6,25'
        ]);

        $user = User::where('email', $data['email'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            return ApiResponse::error('邮箱或密码不正确', ApiResponse::CODE_ERROR, 422);
        }

        $token = $user->createToken('api-token')->plainTextToken;

        return ApiResponse::success([
            'authenticated' => true,
            'api_token' => $token,
            'token' => $token,
            'user' => $user,
        ], '登录成功');
    }

    public function logout(Request $request)
    {
        $token = $request->user()->currentAccessToken();

        if ($token && method_exists($token, 'delete')) {
            $token->delete();
        }

        return ApiResponse::success(['done' => true], '已退出登录');
    }

    /**
     * 微信小程序登录
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function wechatLogin(Request $request)
    {
        $data = $this->validate($request, [
            'code' => 'required|string',
            'user_info' => 'array'
        ]);

        $appid = config('services.wechat_miniprogram.appid');
        $secret = config('services.wechat_miniprogram.secret');

        // 未配置微信凭据时回退到模拟登录（仅本地/测试环境）
        if (empty($appid) || empty($secret)) {
            // 关键修复：mock openid 必须是“稳定可复现”的。
            // 旧逻辑用 Str::random(10) 每次生成随机 openid，会导致每次登录都新建一个
            // user 记录，上一次登录用户收藏的菜谱（存于 recipe_user，按 user_id 关联）
            // 被孤儿化，表现为“退出再登录收藏消失”。
            // 这里以客户端传入的稳定标识（mock_openid 或昵称）派生固定 openid，
            // 保证同一“用户”多次登录解析到同一条 user 记录。
            $seed = $request->input('mock_openid')
                ?? $request->input('user_info.nickName')
                ?? 'dev';
            $hash = substr(md5((string) $seed), 0, 16);

            $wechatUser = [
                'openid' => 'mock_'.$hash,
                'unionid' => 'mock_union_'.$hash,
                'session_key' => Str::random(20),
            ];

            Log::warning(
                '[wechat] WECHAT_MINIPROGRAM_APPID/SECRET 未配置，已回退到 mock 登录'
                .'（仅限本地测试；生产环境请配置真实凭据，否则每次登录都会“新建用户”导致收藏等数据丢失）。'
            );
        } else {
            try {
                $response = Http::withOptions([
                    'verify' => base_path('resources/certs/cacert.pem'),
                ])->get('https://api.weixin.qq.com/sns/jscode2session', [
                    'appid' => $appid,
                    'secret' => $secret,
                    'js_code' => $data['code'],
                    'grant_type' => 'authorization_code',
                ]);

                $wechatUser = $response->json();

                if (isset($wechatUser['errcode'])) {
                    return ApiResponse::error(
                        '微信登录失败: '.($wechatUser['errmsg'] ?? '未知错误'),
                        ApiResponse::CODE_UNAUTHORIZED,
                        401
                    );
                }
            } catch (\Exception $e) {
                return ApiResponse::serverError('微信登录失败: '.$e->getMessage());
            }
        }

        $user = User::where('wechat_openid', $wechatUser['openid'])->first();

        // 兜底：按 openid 找不到时，若拿到了 unionid 则尝试用 unionid 关联，
        // 避免同一微信用户因 openid 变化（如小程序 appid 调整）被重复建号而丢失收藏。
        if (! $user && ! empty($wechatUser['unionid'])) {
            $user = User::where('wechat_unionid', $wechatUser['unionid'])->first();

            if ($user && $user->wechat_openid !== $wechatUser['openid']) {
                $user->wechat_openid = $wechatUser['openid'];
                $user->save();
            }
        }

        if (! $user) {
            $userInfo = $request->input('user_info', []);
            $user = User::create([
                'name' => $userInfo['nickName'] ?? '微信用户',
                'email' => 'wechat_'.$wechatUser['openid'].'@example.com',
                'password' => Str::random(32),
                'wechat_openid' => $wechatUser['openid'],
                'wechat_unionid' => $wechatUser['unionid'] ?? null,
                'wechat_avatar' => $userInfo['avatarUrl'] ?? null,
                'wechat_nickname' => $userInfo['nickName'] ?? null,
            ]);
        }

        $token = $user->createToken('wechat-token')->plainTextToken;

        return ApiResponse::success([
            'authenticated' => true,
            'api_token' => $token,
            'token' => $token,
            'user' => $user,
        ], '登录成功');
    }

    /**
     * 上传/更新当前用户头像。
     *
     * 头像统一落在 users.wechat_avatar 字段：前端无论传 avatarUrl 还是
     * wechat_avatar，服务端都只认这一个存储字段。
     */
    public function uploadAvatar(Request $request)
    {
        $this->validate($request, [
            'file' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'avatar' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
        ]);

        $file = $request->file('file') ?: $request->file('avatar');

        if (! $file) {
            $provided = $request->input('avatarUrl') ?: $request->input('wechat_avatar');

            if (! is_string($provided) || $provided === '') {
                return ApiResponse::validationError('缺少头像文件或头像地址');
            }

            $avatar = $provided;
        } else {
            $key = 'avatars/'.Str::random(32).'.'.$file->extension();

            Storage::disk('images')->putFileAs('avatars', $file, basename($key));

            $cos = app(CosService::class);

            if ($cos->isConfigured()) {
                $cos->uploadFile($key, $file->getRealPath());
            }

            $avatar = $this->avatarUrl($key);
        }

        $request->user()->update(['wechat_avatar' => $avatar]);

        return ApiResponse::success([
            'url' => $avatar,
            'user' => $request->user(),
        ], '头像上传成功');
    }

    /**
     * 头像地址：小程序端需要可外链的绝对地址，配置了 CDN 时走 CDN，否则用 APP_URL。
     */
    private function avatarUrl(string $key): string
    {
        $cdn = config('filesystems.disks.cos.cdn');

        if (! empty($cdn)) {
            return rtrim((string) $cdn, '/').'/'.ltrim($key, '/');
        }

        return url('/images/'.$key);
    }

    /**
     * 当前用户资料（小程序「我的」页）。
     */
    public function accountInfo(Request $request)
    {
        $user = $request->user();

        return ApiResponse::success([
            'url' => $user->wechat_avatar,
            'user' => $user,
        ], '获取成功');
    }

    /**
     * 更新当前用户资料。
     *
     * 头像只有 users.wechat_avatar 一个存储字段，avatarUrl / wechat_avatar
     * 两种入参名等价（同时传时以 avatarUrl 为准），前端只需保留一个。
     */
    public function updateAccountInfo(Request $request)
    {
        $data = $this->validate($request, [
            'avatarUrl' => 'nullable|string|max:2048',
            'wechat_avatar' => 'nullable|string|max:2048',
            'nickname' => 'nullable|string|max:255',
            'nickName' => 'nullable|string|max:255',
            'name' => 'nullable|string|max:255',
        ]);

        $attributes = [];

        $avatar = $this->firstFilled([$data['avatarUrl'] ?? null, $data['wechat_avatar'] ?? null]);

        if ($avatar !== null) {
            if (Str::startsWith($avatar, ['wxfile://', 'http://tmp/', 'https://tmp/', 'blob:', 'file://'])) {
                return ApiResponse::validationError('头像地址无效：请先调用 /api/upload/avatar 上传，再保存返回的地址');
            }

            if (! Str::startsWith($avatar, ['http://', 'https://', '/images/'])) {
                return ApiResponse::validationError('头像地址无效：需要完整的 http(s) 地址');
            }

            $attributes['wechat_avatar'] = $avatar;
        }

        $nickname = $this->firstFilled([$data['nickname'] ?? null, $data['nickName'] ?? null, $data['name'] ?? null]);

        if ($nickname !== null) {
            // name 与 wechat_nickname 同步：SPA 读 name，小程序读 wechat_nickname。
            $attributes['name'] = $nickname;
            $attributes['wechat_nickname'] = $nickname;
        }

        if (empty($attributes)) {
            return ApiResponse::validationError('没有需要更新的字段');
        }

        $request->user()->update($attributes);

        return ApiResponse::success([
            'url' => $request->user()->wechat_avatar,
            'user' => $request->user(),
        ], '资料已更新');
    }

    /**
     * 取第一个非空字符串（前端同一含义的字段可能有多个别名）。
     */
    private function firstFilled(array $candidates): ?string
    {
        foreach ($candidates as $value) {
            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }
        }

        return null;
    }
}
