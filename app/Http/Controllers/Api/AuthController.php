<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User; // TAMBAHKAN INI
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required',
                'password' => 'required|min:8',
            ]);

            $loginType = filter_var($request->username, FILTER_VALIDATE_EMAIL) ? 'email' : 'username';

            $credentials = [
                $loginType => $request->username,
                'password' => $request->password,
            ];

            if (Auth::attempt($credentials)) {
                /** @var User $user */
                $user = Auth::user();

                if (!$user instanceof User) {
                    return ResponseFormatter::error(null, 'User tidak ditemukan.', 401);
                }

                // Create token
                $token = $user->createToken('auth_token')->plainTextToken;

                $data = [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'group_id' => $user->group_id,
                        'phone_number' => $user->phone_number,
                    ],
                    'token' => $token,
                    'token_type' => 'Bearer'
                ];

                return ResponseFormatter::success($data, 'Login berhasil', 200);
            } else {
                return ResponseFormatter::error(null, 'Email atau Username atau Password salah.', 401);
            }
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function logout(Request $request)
    {
        try {
            // Pastikan user terautentikasi
            if (!$request->user()) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            // Hapus token saat logout
            $request->user()->currentAccessToken()->delete();

            return ResponseFormatter::success(null, 'Logout berhasil', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    public function me(Request $request)
    {
        try {
            /** @var User $user */
            $user = $request->user();

            if (!$user) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            $data = [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'group_id' => $user->group_id,
                'phone_number' => $user->phone_number,
            ];

            return ResponseFormatter::success($data, 'User data berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
