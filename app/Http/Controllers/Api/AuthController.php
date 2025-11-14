<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AuthController extends Controller
{

    public function signup(Request $request)
    {
        try {
            $token = $request->input('token');

            $validToken = config('app.api_token', env('API_ACCESS_TOKEN'));

            if (!$token || $token !== $validToken) {
                return ResponseFormatter::error(null, 'Unauthorized: Token tidak valid atau tidak ditemukan', 200);
            }

            $validated = $request->validate([
                'fullname'     => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone'        => 'required|string|max:255|unique:users,phone_number',
                'email'        => 'required|string|email|max:255|unique:users,email',
                'username'     => 'required|string|max:255|unique:users,username',
                'password'     => 'required|string|min:8',
            ]);

            $wa = new WhatsappService();
            $create = $wa->createSession($request->fullname);


            $group = Groups::create([
                'name' => $validated['fullname'],
                'slug' => Str::slug($validated['fullname']),
                'wa_api_token' => $create['sessionId'],
                'group_type' => 'mitra',
            ]);

            GlobalSettings::create([
                'isolir_mode' => false,
                'group_id' => $group->id,
            ]);

            $newUser = User::create([
                'name'        => $validated['fullname'],
                'email'       => $validated['email'],
                'phone_number' => $validated['phone'],
                'role'        => 'mitra',
                'username'    => $validated['username'],
                'password'    => Hash::make($validated['password']),
                'group_id'    => $group->id,
            ]);


            $data = [
                'user' => $newUser,
                'group' => $group
            ];
            return ResponseFormatter::success($data, 'Signup Berhasil', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }


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
            return ResponseFormatter::error(null, $th->getMessage(), 200);
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
