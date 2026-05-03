<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\WireguardClient;
use App\Services\WireguardService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WireguardController extends Controller
{
    protected WireguardService $wgService;

    public function __construct(WireguardService $wgService)
    {
        $this->wgService = $wgService;
    }

    /**
     * Helper untuk mengambil auth user
     */
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $user;
        }

        $id = Auth::id();
        if ($id) {
            return User::find($id);
        }

        return null;
    }

    /**
     * Menampilkan daftar Peer WireGuard berdasarkan group_id
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 🔍 Query dasar: filter berdasarkan group_id user
            $query = WireguardClient::select('id', 'group_id', 'name', 'ip_address', 'public_key', 'created_at')
                ->where('group_id', $user->group_id);

            // 🔍 Search (Misal mencari berdasarkan IP atau Public Key)
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('ip_address', 'like', "%{$search}%")
                        ->orWhere('public_key', 'like', "%{$search}%");
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'id');
            $sortDirection = $request->get('sort_direction', 'asc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 10);
            $wgClients = $query->paginate($perPage);

            return ResponseFormatter::success($wgClients, 'Data WireGuard berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Menyimpan Peer WireGuard baru ke server dan database
     */
    public function store(Request $request)
    {
        // 1. Validasi input dari request frontend
        $request->validate([
            'name'       => 'nullable|string|max:255',
            'public_key' => 'required|string',
        ]);

        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 2. Dapatkan IP yang belum dipakai dari subnet /23
            $ipAddress = $this->getNextAvailableIp();

            // 3. Eksekusi service dengan membawa parameter public_key dari MikroTik
            $result = $this->wgService->createPeer($ipAddress, $request->public_key);

            if ($result['status'] === 'success') {
                // 4. Simpan ke database jika berhasil di server
                $client = WireguardClient::create([
                    'group_id'   => $user->group_id,
                    'name'       => $request->name, // Simpan name dari form
                    'ip_address' => $result['ip_address'],
                    'public_key' => $request->public_key, // Simpan public key MikroTik
                    'config'     => $result['config'] ?? null,
                ]);

                return ResponseFormatter::success($client, 'VPN Tunnel berhasil dibuat!', 201);
            }

            return ResponseFormatter::error($result['error'] ?? null, 'Gagal mengeksekusi WireGuard di server.', 500);
        } catch (\Exception $e) {
            return ResponseFormatter::error(null, $e->getMessage(), 422);
        }
    }

    /**
     * Menghapus Peer dari server dan database
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            // 1. Cari data client di database, pastikan hanya milik group_id tersebut
            $client = WireguardClient::where('group_id', $user->group_id)->findOrFail($id);

            // 2. Eksekusi penghapusan di server Ubuntu
            $isRemoved = $this->wgService->removePeer($client->public_key);

            if ($isRemoved) {
                // 3. Hapus dari database jika berhasil dihapus dari server
                $client->delete();

                // Opsional: Jika menggunakan Activity Log
                // ActivityLogController::logCreate(['action' => 'destroy', 'status' => 'success'], 'wireguard_clients');

                return ResponseFormatter::success(null, 'VPN Tunnel berhasil dihapus dari sistem.');
            }

            return ResponseFormatter::error(null, 'Gagal menghapus peer dari server Ubuntu.', 500);
        } catch (\Throwable $th) {
            // Opsional: Jika menggunakan Activity Log
            // ActivityLogController::logCreateF(['action' => 'destroy', 'error' => $th->getMessage()], 'wireguard_clients');

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Helper untuk mencari IP kosong di subnet 172.31.18.1/23
     */
    private function getNextAvailableIp()
    {
        // Subnet /23 = 172.31.18.1 sampai 172.31.19.254
        // Kita mulai dari .2 karena .1 biasanya dipakai oleh interface server (wg0)
        $startIp = ip2long('172.31.18.2');
        $endIp   = ip2long('172.31.19.254');

        // Ambil semua IP yang sudah terdaftar di database (tidak dibatasi group, karena IP pool bersifat global)
        $usedIps = WireguardClient::pluck('ip_address')->toArray();
        $usedIpsLong = array_map('ip2long', $usedIps);

        // Cari IP pertama yang tidak ada di array $usedIpsLong
        for ($i = $startIp; $i <= $endIp; $i++) {
            if (!in_array($i, $usedIpsLong)) {
                return long2ip($i);
            }
        }

        throw new \Exception('IP Pool VPN WireGuard sudah penuh.');
    }
}
