<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\Area;
use App\Models\OpticalDist;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class OpticalController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $isSuperadmin = is_null($user->group_id);

        $area = Area::where('group_id', $user->group_id)->get();

        $data = OpticalDist::where('group_id', $user->group_id)->get();

        return view('pages.optical.index', compact('data', 'area', 'user'));
    }


    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $isSuperadmin = $user->role === 'superadmin'; // Jika group_id null, berarti Superadmin

            // Validasi umum
            $rules = [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('optical_dists')->where(function ($query) use ($user) {
                        return $query->where('group_id', $user->group_id);
                    }),
                ],
                'area_id'   => 'required|string|max:15',
                'lat'       => 'required|string|max:255',
                'lng'       => 'required|string|max:255',
                'capacity'  => 'required|string|max:15',
            ];

            // Jika Superadmin, tambahkan validasi khusus
            if ($isSuperadmin) {
                $rules['ip_public'] = 'required|ipv4';
                $rules['device_name'] = 'required';
            }

            $request->validate($rules);

            // Data umum
            $data = [
                'name'      => $request->name,
                'group_id'  => $user->group_id,
                'area_id'   => $request->area_id,
                'lat'       => $request->lat,
                'lng'       => $request->lng,
                'capacity'  => $request->capacity,
                'type'      => $isSuperadmin ? 'POP' : $request->type
            ];

            // Jika Superadmin, tambahkan field tambahan
            if ($isSuperadmin) {
                $data['ip_public'] = $request->ip_public;
                $data['device_name'] = $request->device_name;
            }

            OpticalDist::create($data);

            return redirect()->route('optical.index')->with('success', 'Data berhasil disimpan!');
        } catch (\Throwable $th) {
            return redirect()->route('optical.index')->with('error', $th->getMessage());
        }
        ActivityLogged::dispatch(
            'CREATE',
            null,
            $data
        );
    }


    public function update(Request $request, $id)
    {
        try {
            $odp = OpticalDist::where('id', $id)->firstOrFail();
            $user = Auth::user();

            $isSuperadmin = $user->role === 'superadmin';  // Jika group_id null, berarti Superadmin

            // Validasi input berdasarkan role
            $rules = [
                'name'      => 'required|string|max:255',
                'capacity'  => 'required|string|max:15',
                'lat'       => 'required|string|max:255',
                'lng'       => 'required|string|max:255',
            ];

            if ($isSuperadmin) {
                $rules['device_name'] = 'required|string|max:255';
                $rules['ip_public']   = 'required|ipv4';
            } else {
                $rules['type'] = 'required';
            }

            $request->validate($rules);

            // Data yang akan diupdate
            $data = $request->only(['name', 'capacity', 'lat', 'lng']);

            if ($isSuperadmin) {
                $data['device_name'] = $request->device_name;
                $data['ip_public']   = $request->ip_public;
            } else {
                $data['type'] = $request->type;
            }

            $odp->update($data);

            return redirect()->route('optical.index')->with('success', 'Data berhasil diperbarui.');
        } catch (\Throwable $th) {
            return redirect()->route('optical.index')->with('error', $th->getMessage());
        }
        ActivityLogged::dispatch(
            'UPDATE',
            null,
            $odp
        );
    }


    public function destroy($id)
    {
        $odp = OpticalDist::where('id', $id)->firstOrFail();

        // Hapus data
        $odp->delete();
        ActivityLogged::dispatch(
            'DELETE',
            null,
            $odp
        );

        return redirect()->route('optical.index')->with('success', 'Data ODP/ODC berhasil dihapus.');
    }
}
