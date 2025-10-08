<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\VpnUsers;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class VpnController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $data = VpnUsers::where('group_id', $user->group_id)->get();

        return view('pages.radius.vpn', compact('user', 'data'));
    }

    private $ip_range_start = '172.31.18.2';
    private $ip_range_end = '172.31.19.254';

    private function getAvailableIp()
    {
        $start = ip2long($this->ip_range_start);
        $end = ip2long($this->ip_range_end);

        for ($ip = $start; $ip <= $end; $ip++) {
            $ip_address = long2ip($ip);
            if (!VpnUsers::where('ip_address', $ip_address)->exists()) {
                return $ip_address;
            }
        }

        return null; // Jika tidak ada IP tersedia
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
                    Rule::unique('vpn_users')->where(fn($query) => $query->where('group_id', $groupId))
                ],
                'username'     => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('vpn_users')->where(fn($query) => $query->where('group_id', $groupId))
                ],
                'password'    => 'required|string|max:255',
            ]);

            $ip_address = $this->getAvailableIp();
            if (!$ip_address) {
                return response()->json(['message' => 'IP Pool sudah penuh!'], 400);
            }

            $data = VpnUsers::create([
                'name'        => $request->name,
                'group_id'    => $groupId,
                'username'     => $request->username,
                'password'         => $request->password,
                'ip_address'         => $ip_address,
            ]);

            DB::connection('radius')->table('radcheck')->insert([
                'username' => $data->username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $data->password,
                'group_id' => $groupId
            ]);

            DB::connection('radius')->table('radusergroup')->insert([
                'username'  => $data->username,
                'groupname' => 'nas_vpn',
                'priority'  => 1,
                'group_id' => $groupId
            ]);

            // Simpan ke radreply (IP Address)
            DB::connection('radius')->table('radreply')->insert([
                'username' => $data->username,
                'attribute' => 'Framed-IP-Address',
                'op' => ':=',
                'value' => $ip_address,
                'group_id' => $groupId
            ]);

            return redirect()->route('vpn.index')->with('success', 'Data VPN berhasil disimpan!');
        } catch (\Throwable $th) {
            return redirect()->route('vpn.index')->with('error', $th->getMessage());
        }
        ActivityLogged::dispatch(
            'CREATE',
            null,
            $data
        );
    }

    public function destroy($id)
    {
        $profile = VpnUsers::where('id', $id)->firstOrFail();

        DB::transaction(function () use ($profile) {
            // Hapus dari radcheck (password VPN)
            DB::connection('radius')->table('radcheck')->where('username', $profile->username)->delete();

            // Hapus dari radreply (IP Address)
            DB::connection('radius')->table('radreply')->where('username', $profile->username)->delete();

            DB::connection('radius')->table('radusergroup')->where('username', $profile->username)->delete();

            // Hapus dari database Laravel (vpn_users)
            $profile->delete();
        });
        ActivityLogged::dispatch(
            'DELETE',
            null,
            $profile
        );

        return redirect()->route('vpn.index')->with('success', 'VPN berhasil dihapus dari sistem.');
    }
}
