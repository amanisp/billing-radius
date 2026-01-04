<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\User;
use App\Models\Area;
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
                return ResponseFormatter::error(null, 'Unauthorized: Token tidak valid atau tidak ditemukan', 401);
            }

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone_number' => 'required|string|max:255|unique:users,phone_number',
                'email' => 'required|string|email|max:255|unique:users,email',
                'username' => 'required|string|max:255|unique:users,username',
                'password' => 'required|string|min:8',
                'area_id' => 'nullable|exists:areas,id', 
            ]);

            $area = null;

            if (isset($validated['area_id'])) {
                // Validasi area_id milik superadmin
                $area = Area::where('id', $validated['area_id'])
                    ->where('group_id', 1)
                    ->first();

                if (!$area) {
                    return ResponseFormatter::error(null, 'Area tidak valid atau bukan milik superadmin', 400);
                }
            } else {
                // Auto assign first superadmin area
                $area = Area::where('group_id', 1)
                    ->orderBy('id', 'asc')
                    ->first();

                if (!$area) {
                    return ResponseFormatter::error(null, 'Tidak ada area superadmin yang tersedia. Silakan buat area terlebih dahulu.', 400);
                }
            }

            $group = Groups::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'wa_api_token' => Str::random(15),
                'group_type' => 'mitra',
            ]);

            GlobalSettings::create([
                'isolir_mode' => false,
                'group_id' => $group->id,
            ]);

            $customerNumber = $this->generateCustomerNumber($area->area_code);

            $newUser = User::create([
                'name'           => $validated['name'],
                'email'          => $validated['email'],
                'phone_number'   => $validated['phone_number'],
                'role'           => 'mitra',
                'username'       => $validated['username'],
                'password'       => Hash::make($validated['password']),
                'group_id'       => $group->id,
                'area_id'        => $area->id,
                'customer_number' => $customerNumber,
            ]);

            $data = [
                'user' => [
                    'id' => $newUser->id,
                    'username' => $newUser->username,
                    'name' => $newUser->name,
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

    private function generateCustomerNumber($areaCode)
    {
        $lastCustomer = User::where('customer_number', 'like', $areaCode . '%')
            ->orderBy('customer_number', 'desc')
            ->first();

        if ($lastCustomer) {
            $lastNumber = (int) substr($lastCustomer->customer_number, strlen($areaCode));
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $areaCode . str_pad($newNumber, 7, '0', STR_PAD_LEFT);
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

                $token = $user->createToken('auth_token')->plainTextToken;

                $data = [
                    'user' => [
                        'id' => $user->id,
                        'username' => $user->username,
                        'name' => $user->name,
                        'email' => $user->email,
                        'role' => $user->role,
                        'group_id' => $user->group_id,
                        'area_id' => $user->area_id,
                        'phone_number' => $user->phone_number,
                        'address' => $user->address,
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

    public function logout(Request $request)
    {
        try {
            if (!$request->user()) {
                return ResponseFormatter::error(null, 'Unauthorized', 401);
            }

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
                'area_id' => $user->area_id,
                'phone_number' => $user->phone_number,
                'address' => $user->address,
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
