<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\ActivityLogController;
use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\ResetTokens;
use App\Models\User;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
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

            // $wa = new WhatsappService();
            // $create = $wa->createSession($request->fullname);


            $group = Groups::create([
                'name' => $validated['fullname'],
                'slug' => Str::slug($validated['fullname']),
                'wa_api_token' => 'default',
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
            ActivityLogController::logCreate(['action' => 'signup', 'status' => 'success'], 'users');
            return ResponseFormatter::success($data, 'Signup Berhasil', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'signup', 'error' => $th->getMessage()], 'users');
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function login(Request $request)
    {
        try {
            $request->validate([
                'username' => 'required|string',
                'password' => 'required|string',
            ]);

            $field = filter_var($request->username, FILTER_VALIDATE_EMAIL)
                ? 'email'
                : 'username';

            $user = User::where($field, $request->username)->first();

            if (!$user) {
                return ResponseFormatter::error(null, 'User tidak ditemukan', 401);
            }

            if (!Hash::check($request->password, $user->password)) {
                return ResponseFormatter::error(null, 'Password salah', 401);
            }

            $token = $user->createToken('auth_token')->plainTextToken;

            ActivityLogController::logCreate(['action' => 'login', 'status' => 'success'], 'users');
            return ResponseFormatter::success([
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
            ], 'Login berhasil');
        } catch (\Throwable $e) {
            ActivityLogController::logCreateF(['action' => 'login', 'error' => $e->getMessage()], 'users');
            return ResponseFormatter::error(null, $e->getMessage(), 500);
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

            ActivityLogController::logCreate(['action' => 'logout', 'status' => 'success'], 'users');
            return ResponseFormatter::success(null, 'Logout berhasil', 200);
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF(['action' => 'logout', 'error' => $th->getMessage()], 'users');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }


    public function me(Request $request)
    {
        try {
            /** @var User $user */
            $user = $request->user()->load('group');


            if (!$user) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

            $global = GlobalSettings::where('group_id', $user->group_id)->first();

            $data = [
                'id' => $user->id,
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role,
                'group_id' => $user->group_id,
                'group' => $user->group,
                'phone_number' => $user->phone_number,
                'global_setting' => $global
            ];

            return ResponseFormatter::success($data, 'User data berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function sendToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
        ]);

        try {
            $email = $request->email;

            // Cek user secara silent
            $user = User::where('email', $email)->first();

            if ($user) {
                // Generate 6 digit token
                $token = random_int(100000, 999999);

                // Hapus token lama
                ResetTokens::where('email', $email)->delete();

                // Simpan token baru
                ResetTokens::create([
                    'email'      => $email,
                    'token' => Hash::make($token),
                    'expired_at' => now()->addMinutes(10),
                ]);

                // Kirim email
                Mail::raw(
                    "Kode reset password Anda adalah: $token\nBerlaku selama 10 menit.",
                    function ($message) use ($email) {
                        $message->to($email)
                            ->subject('Kode Reset Password');
                    }
                );
            }

            // RESPONSE SELALU SAMA
            ActivityLogController::logCreate(['action' => 'sendToken', 'status' => 'success'], 'users');
            return ResponseFormatter::success(
                null,
                'Kode reset password telah dikirim'
            );
        } catch (\Throwable $e) {
            ActivityLogController::logCreateF(['action' => 'sendToken', 'error' => $e->getMessage()], 'users');
            // Response aman
            return ResponseFormatter::error(
                null,
                'Terjadi kesalahan, silakan coba lagi'
            );
        }
    }

    public function verifyToken(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required|digits:6',
        ]);

        try {
            $reset = ResetTokens::where('email', $request->email)->first();

            if (! $reset) {
                return ResponseFormatter::error(
                    null,
                    'Token tidak valid atau sudah kadaluarsa'
                );
            }

            // Cek expired
            if ($reset->isExpired()) {
                $reset->delete();

                return ResponseFormatter::error(
                    null,
                    'Token tidak valid atau sudah kadaluarsa'
                );
            }

            // Cocokkan token dengan hash
            if (! Hash::check($request->token, $reset->token)) {
                return ResponseFormatter::error(
                    null,
                    'Token tidak valid atau sudah kadaluarsa'
                );
            }

            // Token VALID → hapus agar tidak bisa dipakai ulang
            $reset->delete();

            ActivityLogController::logCreate(['action' => 'verifyToken', 'status' => 'success'], 'users');
            return ResponseFormatter::success(
                null,
                'Token valid'
            );
        } catch (\Throwable $e) {
            ActivityLogController::logCreateF(['action' => 'verifyToken', 'error' => $e->getMessage()], 'users');
            return ResponseFormatter::error(
                null,
                'Terjadi kesalahan, silakan coba lagi'
            );
        }
    }

    public function updateAccountToken(Request $request)
    {
        try {
            $request->validate([
                'account_token' => 'required|string|min:10|max:255',  // Fonnte Account Token
            ]);

            $user = Auth::user();

            GlobalSettings::updateOrCreate(
                ['group_id' => $user->group_id],
                ['whatsapp_api_key' => $request->account_token]  // Account Token
            );

            ActivityLogController::logCreate([
                'action' => 'update_account_token',
                'group_id' => $user->group_id,
                'status' => 'success'
            ], 'global_settings');

            return ResponseFormatter::success([
                'account_token_saved' => true,
                'next_step' => 'Call GET /whatsapp/status to see devices'
            ], 'Fonnte Account Token saved! Next: generate QR', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }

    public function updateWhatsappToken(Request $request)
    {
        try {
            $request->validate([
                'device_token' => 'required|string|min:10|max:255',  // Fonnte Account Token
            ]);

            $user = Auth::user();

            Groups::updateOrCreate(
                ['id' => $user->group_id],
                ['wa_api_token' => $request->device_token]  // Account Token
            );

            ActivityLogController::logCreate([
                'action' => 'update_account_token',
                'group_id' => $user->group_id,
                'status' => 'success'
            ], 'global_settings');

            return ResponseFormatter::success([
                'device_token_saved' => true,
                'next_step' => 'Call GET /whatsapp/status to see devices'
            ], 'Fonnte Account Token saved! Next: generate QR', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
