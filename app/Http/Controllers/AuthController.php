<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
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
	    //$this->middleware('auth:api')->only('logout');
	}

    public function register(Request $request)
    {
        $this->validate($request, [
            'name' => 'required|max:255',
            'email' => 'required|email|unique:users',
            'password' => 'required|between:6,25|confirmed'
        ]);

        $user = new User($request->all());
        $user->password = bcrypt($request->password);
        $user->save();

        return response()
            ->json([
                'registered' => true
            ]);
    }

    public function login(Request $request)
    {
        $this->validate($request, [
            'email' => 'required|email',
            'password' => 'required|between:6,25'
        ]);

        $user = User::where('email', $request->email)
            ->first();

        if($user && Hash::check($request->password, $user->password)) {
              // 生成 Sanctum token
            $token = $user->createToken('api-token')->plainTextToken;

            return response()
                ->json([
                    'authenticated' => true,
                    'api_token' => $token,
                    'user_id' => $user->id,
                    'is_admin' => $user->is_admin,
                    'name' => $user->name,
                ]);
        }

        return response()
            ->json([
                'errors' => [
                    'email' => ['Provided email and password does not match!']
                ]
            ], 422);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();


        return response()
            ->json([
                'done' => true
            ]);
    }

    /**
     * 微信小程序登录
     * 
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function wechatLogin(Request $request)
    {
        $this->validate($request, [
            'code' => 'required|string',
            'user_info' => 'array'
        ]);

        // 获取微信配置
        $appid = env('WECHAT_MINIPROGRAM_APPID');
        $secret = env('WECHAT_MINIPROGRAM_SECRET');
        
        // 如果配置不存在，则使用模拟数据
        if (empty($appid) || empty($secret)) {
            // 模拟微信返回的数据
            $wechatUser = [
                'openid' => 'mock_openid_' . Str::random(10),
                'unionid' => 'mock_unionid_' . Str::random(10),
                'session_key' => Str::random(20)
            ];
        } else {
            // 调用微信接口验证code并获取openid
            $url = "https://api.weixin.qq.com/sns/jscode2session?appid={$appid}&secret={$secret}&js_code={$request->code}&grant_type=authorization_code";
            
            try {
                // 使用Http客户端并配置SSL选项
                $response = Http::withOptions([
                    'verify' => false // 在开发环境中禁用SSL验证，生产环境中应配置正确的证书
                ])->get($url);
                
                $wechatUser = $response->json();
                
                if (isset($wechatUser['errcode'])) {
                    return response()->json([
                        'authenticated' => false,
                        'message' => '微信登录失败: ' . $wechatUser['errmsg']
                    ], 401);
                }
            } catch (\Exception $e) {
                return response()->json([
                    'authenticated' => false,
                    'message' => '微信登录失败: ' . $e->getMessage()
                ], 500);
            }
        }

        // 查找用户是否已存在
        $user = User::where('wechat_openid', $wechatUser['openid'])->first();

        // 如果用户不存在，则创建新用户
        if (!$user) {
            $userInfo = $request->input('user_info', []);
            $user = User::create([
                'name' => $userInfo['nickName'] ?? '微信用户',
                'email' => 'wechat_' . $wechatUser['openid'] . '@example.com',
                'password' => bcrypt(Str::random(32)), // 生成随机密码
                'wechat_openid' => $wechatUser['openid'],
                'wechat_unionid' => $wechatUser['unionid'] ?? null,
                'wechat_avatar' => $userInfo['avatarUrl'] ?? null,
                'wechat_nickname' => $userInfo['nickName'] ?? null,
            ]);
        }

        // 生成 Sanctum token
        $token = $user->createToken('wechat-token')->plainTextToken;

        return response()->json([
            'authenticated' => true,
            'token' => $token,
            'user' => $user
        ]);
    }
}
