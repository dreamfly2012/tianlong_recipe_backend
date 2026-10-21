<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Http\Helpers\ApiResponse;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

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
 */
class AuthController extends Controller
{
	public function __construct()
	{
        $this->middleware('auth:sanctum')->only('logout');
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

        // 未配置微信时使用模拟数据，便于本地开发和测试
        if (empty($appid) || empty($secret)) {
            $wechatUser = [
                'openid' => 'mock_openid_'.Str::random(10),
                'unionid' => 'mock_unionid_'.Str::random(10),
                'session_key' => Str::random(20)
            ];
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
}
