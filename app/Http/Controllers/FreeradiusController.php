<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\ActivityLog;
use App\Models\GlobalSettings;
use App\Models\Groups;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Request as FacadeRequest;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;


class FreeradiusController extends Controller
{
    public function index()
    {
        $data = User::where('role', 'mitra')->get();

        return view('pages.freeradius', compact('data'));
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $validated = $request->validate([
                'name'         => 'required|string|max:255|unique:users,name|unique:groups,name',
                'phone_number' => 'required|string|max:255|unique:users,phone_number',
                'email'        => 'required|string|max:255|unique:users,email',
                'username'     => 'required|unique:users,username',
                'password'     => 'required|min:8',
                'area_id'      => 'required|exists:areas,id', // 🔥 TAMBAHAN
            ]);

            // 🔥 Validasi area_id harus milik superadmin yang login
            $area = \App\Models\Area::where('id', $validated['area_id'])
                ->where('group_id', $user->group_id)
                ->first();

            if (!$area) {
                return back()->with('error', 'Area tidak valid!');
            }

            $groups = Groups::create([
                'name' => $validated['name'],
                'slug' => Str::slug($validated['name']),
                'wa_api_token' => Str::random(15),
                'group_type' => 'mitra',
            ]);

            GlobalSettings::create(['isolir_mode' => false, 'group_id' => $groups['id']]);

            // 🔥 Generate customer_number
            $customerNumber = $this->generateCustomerNumber($area->area_code);

            $newUser = User::create([
                'name'           => $validated['name'],
                'fullname'       => $validated['name'], // 🔥 TAMBAHAN
                'email'          => $validated['email'],
                'phone_number'   => $validated['phone_number'],
                'role'           => 'mitra',
                'username'       => $validated['username'],
                'password'       => Hash::make($validated['password']),
                'group_id'       => $groups['id'],
                'area_id'        => $validated['area_id'], // 🔥 TAMBAHAN
                'customer_number' => $customerNumber, // 🔥 TAMBAHAN
            ]);

            // Logging untuk CREATE operation
            ActivityLogged::dispatch('CREATE', null, $newUser);

            return back()->with('success', 'Akses radius berhasil ditambah!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }
    private function generateCustomerNumber($areaCode)
    {
        $lastCustomer = User::where('customer_number', 'like', $areaCode . '-%')
            ->orderBy('customer_number', 'desc')
            ->first();

        if ($lastCustomer) {
            $lastNumber = (int) explode('-', $lastCustomer->customer_number)[1];
            $newNumber = $lastNumber + 1;
        } else {
            $newNumber = 1;
        }

        return $areaCode . '-' . str_pad($newNumber, 5, '0', STR_PAD_LEFT);
    }

    public function destroy($id)
    {
        $user = Auth::user();
        $targetUser = User::where('id', $id)->firstOrFail();

        // Simpan data yang akan dihapus untuk logging
        $deletedData = $targetUser->toArray();

        // Hapus data
        $targetUser->delete();

        // Logging untuk DELETE operation
        ActivityLogged::dispatch(
            'DELETE',
            null,
            $deletedData
        );

        return redirect()->route('freeradius.index')->with('success', 'Akses radius berhasil dihapus.');
    }
}
