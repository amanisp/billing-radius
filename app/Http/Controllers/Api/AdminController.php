<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Area;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function index(Request $request)
    {
        $mitras = User::where('role', 'mitra')
            ->with(['area'])
            ->paginate($request->per_page ?? 10);

        return ResponseFormatter::success($mitras, 'List mitra berhasil dimuat', 200);
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone_number' => 'required|string|max:255|unique:users,phone_number',
                'email' => 'required|email|unique:users,email',
                'username' => 'required|unique:users,username',
                'password' => 'required|min:8',
                'area_id' => 'required|exists:areas,id',
                'register' => 'nullable|date',
                'payment' => 'nullable|string',
                'nip' => 'nullable|string|max:20',
            ]);

            $area = Area::where('id', $validated['area_id'])->where('group_id', 1)->first();
            if (!$area) {
                return ResponseFormatter::error(null, 'Area tidak valid', 400);
            }

            $group = Groups::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'wa_api_token' => Str::random(15),
                'group_type' => 'mitra',
            ]);

            GlobalSettings::create(['isolir_mode' => false, 'group_id' => $group->id]);

            $customerNumber = $this->generateCustomerNumber($area->area_code);

            $newMitra = User::create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'role' => 'mitra',
                'username' => $validated['username'],
                'password' => Hash::make($validated['password']),
                'group_id' => $group->id,
                'area_id' => $validated['area_id'],
                'customer_number' => $customerNumber,
                'register' => $validated['register'] ?? null,
                'payment' => $validated['payment'] ?? null,
                'nip' => $validated['nip'] ?? null,
            ]);

            return ResponseFormatter::success($newMitra, 'Mitra berhasil dibuat', 201);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'Validasi gagal', 422);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $mitra = User::where('id', $id)->where('role', 'mitra')->firstOrFail();

            $validated = $request->validate([
                'name' => 'sometimes|required|string|max:255|unique:users,name,' . $id . '|unique:groups,name,' . $mitra->group_id,
                'phone_number' => 'sometimes|required|string|max:255|unique:users,phone_number,' . $id,
                'email' => 'sometimes|required|email|unique:users,email,' . $id,
                'username' => 'sometimes|required|unique:users,username,' . $id,
                'password' => 'sometimes|min:8',
                'area_id' => 'sometimes|required|exists:areas,id',
                'register' => 'nullable|date',
                'payment' => 'nullable|string',
                'nip' => 'nullable|string|max:20',
            ]);

            if (isset($validated['area_id'])) {
                $area = Area::where('id', $validated['area_id'])->where('group_id', 1)->first();
                if (!$area) {
                    return ResponseFormatter::error(null, 'Area tidak valid', 400);
                }
            }

            if (isset($validated['password'])) {
                $validated['password'] = Hash::make($validated['password']);
            }

            $mitra->update($validated);

            if (isset($validated['name'])) {
                $group = Groups::find($mitra->group_id);
                $group->update([
                    'name' => $validated['name'],
                    'slug' => Str::slug($validated['name']),
                ]);
            }

            return ResponseFormatter::success($mitra->fresh(), 'Mitra berhasil diupdate', 200);
        } catch (\Illuminate\Validation\ValidationException $e) {
            return ResponseFormatter::error($e->errors(), 'Validasi gagal', 422);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    public function destroy($id)
    {
        try {
            $mitra = User::where('id', $id)->where('role', 'mitra')->firstOrFail();
            $groupId = $mitra->group_id;

            $mitra->delete();
            Groups::destroy($groupId);
            GlobalSettings::where('group_id', $groupId)->delete();

            return ResponseFormatter::success(null, 'Mitra berhasil dihapus', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
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
}
