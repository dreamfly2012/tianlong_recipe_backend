<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

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
}
