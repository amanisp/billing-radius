<?php

namespace App\Http\Controllers;

use App\Models\Nas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class RadiusController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = Nas::where('group_id', $user->group_id)->get();

        return view('pages.radius.radius', compact('user', 'data'));
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();
            $groupId = $user->group_id;

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('nas')->where(fn($query) => $query->where('group_id', $groupId))
                ],
                'ip_router'    => 'required|string|ipv4',
                'secret'    => 'required|string|max:255',
            ]);


            $data = Nas::create([
                'name'        => $request->name,
                'group_id'    => $groupId,
                'ip_radius'     => '103.203.233.235',
                'ip_router'         => $request->ip_router,
                'secret'         => $request->secret,
            ]);

            DB::connection('radius')->table('nas')->insert([
                'nasname' => $data->ip_router,
                'shortname' => $data->name,
                'secret' => $data->secret,
                'description' => $data->group_id,
                'group_id'    => $groupId,
            ]);

            DB::connection('radius')->table('radgroupcheck')->insert([
                'groupname' => 'mitra_' . $groupId,
                'attribute' => 'NAS-IP-Address',
                'op' => '==',
                'value' => $data->ip_router
            ]);

            return redirect()->route('radius.index')->with('success', 'Data VPN berhasil disimpan!');
        } catch (\Throwable $th) {
            return redirect()->route('radius.index')->with('error', $th->getMessage());
        }
    }


    public function destroy($id)
    {
        $nas = Nas::where('id', $id)->firstOrFail();

        DB::transaction(function () use ($nas) {
            // Hapus dari radcheck (password VPN)
            DB::connection('radius')->table('nas')->where('nasname', $nas->ip_router)->delete();


            // Hapus dari database Laravel (vpn_users)
            $nas->delete();
        });

        return redirect()->route('radius.index')->with('success', 'Radius berhasil dihapus dari sistem.');
    }
}
