<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required', // Bisa username atau email
                'password' => 'required|min:8',
            ]);


            $loginType = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $credentials = [
                $loginType => $request->username,
                'password' => $request->password,
            ];

            if (Auth::attempt($credentials)) {
                $user = Auth::user();

                $token = $user->createToken('auth_token')->plainTextToken;
                $data = [
                    'user' => $user,
                    'token' => $token
                ];

                return ResponseFormatter::success($data, 'Login berhasil', 200);
            } else {
                return ResponseFormatter::error(null, 'Email atau Username atau Password salah.', 401);
            }
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 401);
        }
    }

    public function logout(Request $request)
    {
        // Hapus token saat logout
        $request->user()->currentAccessToken()->delete();

        return ResponseFormatter::success($request, 'Logout berhasil', 200);
    }
}
