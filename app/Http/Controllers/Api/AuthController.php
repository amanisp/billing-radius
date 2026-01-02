<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\User;
use App\Models\Area;
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
            // 🔐 Validasi API Token
            $token = $request->input('token');
            $validToken = config('app.api_token', env('API_ACCESS_TOKEN'));

            if (!$token || $token !== $validToken) {
                return ResponseFormatter::error(
                    null,
                    'Unauthorized: Token tidak valid atau tidak ditemukan',
                    401
                );
            }

            // 📝 Validasi Input
            $validated = $request->validate([
                'fullname'     => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone'        => 'required|string|max:255|unique:users,phone_number',
                'email'        => 'required|string|email|max:255|unique:users,email',
                'username'     => 'required|string|max:255|unique:users,username',
                'password'     => 'required|string|min:8',
                'area_id'      => 'nullable|exists:areas,id', // 🔥 Optional
            ]);

            // 🏢 Cari atau Set Primary Area
            $area = null;

            if (isset($validated['area_id'])) {
                // Jika area_id dikirim, validasi harus milik superadmin
                $area = Area::where('id', $validated['area_id'])
                    ->where('group_id', 1)
                    ->first();

                if (!$area) {
                    return ResponseFormatter::error(
                        null,
                        'Area tidak valid atau bukan milik superadmin',
                        400
                    );
                }
            } else {
                // Jika tidak ada area_id, ambil primary area
                $area = Area::where('group_id', 1)
                    ->where('is_primary', true)
                    ->first();

                // Fallback: jika tidak ada primary, ambil area pertama
                if (!$area) {
                    $area = Area::where('group_id', 1)
                        ->orderBy('id', 'asc')
                        ->first();
                }

                if (!$area) {
                    return ResponseFormatter::error(
                        null,
                        'Tidak ada area superadmin yang tersedia. Silakan buat area terlebih dahulu.',
                        400
                    );
                }
            }

            // 🏗️ Create Group untuk Mitra
            $group = Groups::create([
                'name' => $validated['fullname'],
                'slug' => Str::slug($validated['fullname']),
                'wa_api_token' => Str::random(15),
                'group_type' => 'mitra',
            ]);

            // ⚙️ Create Global Settings
            GlobalSettings::create([
                'isolir_mode' => false,
                'group_id' => $group->id,
            ]);

            // 🔢 Generate Customer Number
            $customerNumber = $this->generateCustomerNumber($area->area_code);

            // 👤 Create User Mitra
            $newUser = User::create([
                'name'           => $validated['fullname'],
                'fullname'       => $validated['fullname'],
                'email'          => $validated['email'],
                'phone_number'   => $validated['phone'],
                'role'           => 'mitra',
                'username'       => $validated['username'],
                'password'       => Hash::make($validated['password']),
                'group_id'       => $group->id,
                'area_id'        => $area->id,
                'customer_number' => $customerNumber,
            ]);

            // 📤 Response
            $data = [
                'user' => [
                    'id' => $newUser->id,
                    'username' => $newUser->username,
                    'name' => $newUser->name,
                    'fullname' => $newUser->fullname,
                    'email' => $newUser->email,
                    'phone_number' => $newUser->phone_number,
                    'role' => $newUser->role,
                    'group_id' => $newUser->group_id,
                    'area_id' => $newUser->area_id,
                    'customer_number' => $newUser->customer_number,
                ],
                'group' => [
                    'id' => $group->id,
                    'name' => $group->name,
                    'slug' => $group->slug,
                    'group_type' => $group->group_type,
                ],
                'area' => [
                    'id' => $area->id,
                    'name' => $area->name,
                    'area_code' => $area->area_code,
                ]
            ];

            return ResponseFormatter::success($data, 'Signup berhasil', 201);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error(
                $e->errors(),
                'Validasi gagal',
                422
            );
        } catch (\Throwable $th) {
            return ResponseFormatter::error(
                null,
                $th->getMessage(),
                500
            );
        }
    }

    /**
     * 🔢 Generate Customer Number
     * Format: {area_code}-{running_number}
     * Example: 112-00001, 112-00002, 113-00001
     */
    private function generateCustomerNumber($areaCode)
    {
        // Cari customer_number terakhir dengan area_code yang sama
        $lastCustomer = User::where('customer_number', 'like', $areaCode . '-%')
            ->orderBy('customer_number', 'desc')
            ->first();

        if ($lastCustomer) {
            // Extract running number dari format: 112-00001
            $parts = explode('-', $lastCustomer->customer_number);
            $lastNumber = isset($parts[1]) ? (int) $parts[1] : 0;
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        // Format: area_code-00001 (5 digit padding)
        return $areaCode . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    /**
     * 🔐 LOGIN
     *
     * POST /api/v1/login
     * Body:
     * {
     *   "username": "superadmin", // or email
     *   "password": "password123"
     * }
     */
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

                // 🔥 Response dengan field lengkap
                $data = [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'fullname' => $user->fullname,
                        'email' => $user->email,
                        'role' => $user->role,
                        'group_id' => $user->group_id,
                        'area_id' => $user->area_id,
                        'phone_number' => $user->phone_number,
                        'address' => $user->address,
                        'nik' => $user->nik,
                        'npwp' => $user->npwp,
                        'nip' => $user->nip,
                        'customer_number' => $user->customer_number,
                        'register' => $user->register,
                        'payment' => $user->payment,
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

    /**
     * 🚪 LOGOUT
     *
     * POST /api/v1/logout
     * Headers: Authorization: Bearer {token}
     */
    public function logout(Request $request)
    {
        try {
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

    /**
     * 👤 GET USER INFO
     *
     * GET /api/v1/me
     * Headers: Authorization: Bearer {token}
     */
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
                'fullname' => $user->fullname,
                'email' => $user->email,
                'role' => $user->role,
                'group_id' => $user->group_id,
                'area_id' => $user->area_id,
                'phone_number' => $user->phone_number,
                'address' => $user->address,
                'nik' => $user->nik,
                'npwp' => $user->npwp,
                'nip' => $user->nip,
                'customer_number' => $user->customer_number,
                'register' => $user->register,
                'payment' => $user->payment,
                'created_at' => $user->created_at,
                'updated_at' => $user->updated_at,
            ];

            return ResponseFormatter::success($data, 'User data berhasil dimuat', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
