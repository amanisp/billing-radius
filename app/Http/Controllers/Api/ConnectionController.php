<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Events\ActivityLogged;
use App\Helpers\ResponseFormatter;
use App\Models\Connection;
use App\Models\User;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\Area;
use App\Models\OpticalDist;
use App\Models\Profiles;
use App\Models\Nas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class ConnectionController extends Controller
{
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    /**
     * GET /api/v1/connections
     * List connections dengan pagination, search, dan sort
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Connection::where('group_id', $user->group_id)
                ->with(['profile', 'member', 'area', 'optical', 'nas'])
                ->withCount(['member']);

            // 🔍 Search
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('username', 'like', "%{$search}%")
                        ->orWhere('internet_number', 'like', "%{$search}%")
                        ->orWhere('mac_address', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($q2) use ($search) {
                            $q2->where('fullname', 'like', "%{$search}%");
                        });
                });
            }

            // Filter by status (isolir/active)
            if ($request->filled('status')) { // cek kalau status ada dan tidak kosong
                $statusMap = [
                    'isolir' => 1,  // suspend
                    'active' => 0,  // aktif
                ];

                $statusValue = $statusMap[$request->status] ?? null;

                if ($statusValue !== null) {
                    $query->where('isolir', $statusValue);
                }
            }


            // Filter by profile
            if ($request->has('profile_id') && $request->profile_id) {
                $query->where('profile_id', $request->profile_id);
            }

            // Filter by type
            if ($request->has('type') && $request->type) {
                $query->where('type', $request->type);
            }

            // Filter by area
            if ($request->has('area_id') && $request->area_id) {
                $query->where('area_id', $request->area_id);
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 15);
            $connections = $query->paginate($perPage);

            // Add online status to each connection
            $connections->getCollection()->transform(function ($connection) {
                $username = $connection->username ?? $connection->mac_address;

                $latestSession = DB::connection('radius')
                    ->table('radacct')
                    ->where('username', $username)
                    ->orderBy('acctstarttime', 'DESC')
                    ->first();

                $connection->is_online = $latestSession && is_null($latestSession->acctstoptime);
                return $connection;
            });

            return ResponseFormatter::success($connections, 'Data connections berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/connections/stats
     * Get connection statistics
     */
    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $query = Connection::where('group_id', $user->group_id);

            $stats = [
                'total' => (clone $query)->count(),
                'active' => (clone $query)->where('isolir', false)->count(),
                'isolir' => (clone $query)->where('isolir', true)->count(),
                'pppoe' => (clone $query)->where('type', 'pppoe')->count(),
                'dhcp' => (clone $query)->where('type', 'dhcp')->count(),
            ];

            return ResponseFormatter::success($stats, 'Statistics berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/connections
     * Create new connection only (without member)
     */
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $groupId = $user->group_id;

            // Base validation
            $rules = [
                'type' => 'required|string|in:pppoe,dhcp',
                'profile_id' => 'required|exists:profiles,id',
                'nas_id' => 'nullable|exists:nas,id',
                'area_id' => 'nullable|exists:areas,id',
                'optical_id' => 'nullable|exists:optical_dists,id',
                'isolir' => 'boolean',
            ];

            // Type-specific validation
            if ($request->type === 'pppoe') {
                $rules['username'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('connections', 'username')->where(fn($q) => $q->where('group_id', $groupId)),
                ];
                $rules['password'] = 'required|string';
            } elseif ($request->type === 'dhcp') {
                $rules['mac_address'] = 'required|string|max:17';
            }

            $validated = $request->validate($rules);

            DB::beginTransaction();

            $connection = Connection::create([
                'type' => $validated['type'],
                'username' => $request->username,
                'password' => $request->password,
                'mac_address' => $request->mac_address,
                'profile_id' => $validated['profile_id'],
                'group_id' => $groupId,
                'internet_number' => Connection::generateNomorLayanan($groupId),
                'isolir' => $request->input('isolir', false),
                'nas_id' => $request->nas_id,
                'area_id' => $request->area_id,
                'optical_id' => $request->optical_id,
            ]);

            // Create radius records for PPPoE
            if ($connection->type === 'pppoe' && $connection->username && $connection->password) {
                $this->createRadiusRecords($connection, $groupId);
            }

            DB::commit();

            ActivityLogged::dispatch('CREATE', null, $connection);

            return ResponseFormatter::success($connection, 'Connection berhasil ditambahkan', 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/connections/with-member
     * Create new connection WITH member and payment detail
     */
    public function storeWithMember(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) return response()->json(['message' => 'Unauthorized'], 401);

            $groupId = $user->group_id;

            // Validation for connection
            $connectionRules = [
                'type' => 'required|string|in:pppoe,dhcp',
                'profile_id' => 'required|exists:profiles,id',
                'nas_id' => 'nullable|exists:nas,id',
                'area_id' => 'nullable|exists:areas,id',
                'optical_id' => 'nullable|exists:optical_dists,id',
                'isolir' => 'boolean',
            ];

            // Type-specific validation
            if ($request->type === 'pppoe') {
                $connectionRules['username'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('connections', 'username')->where(fn($q) => $q->where('group_id', $groupId)),
                ];
                $connectionRules['password'] = 'required|string';
            } elseif ($request->type === 'dhcp') {
                $connectionRules['mac_address'] = 'required|string|max:17';
            }

            // Validation for member
            $memberRules = [
                'fullname' => 'required|string|max:255',
                'phone_number' => 'nullable|string|max:20',
                'email' => 'nullable|email',
                'id_card' => 'nullable|string|max:16',
                'address' => 'nullable|string|max:500',
            ];

            // Validation for payment detail
            $paymentRules = [
                'billing' => 'required|boolean',
                'payment_type' => 'required_if:billing,true|string|in:prabayar,pascabayar',
                'billing_period' => 'required_if:billing,true|string|in:fixed,renewal',
                'active_date' => 'required_if:billing,true|date',
                'amount' => 'required_if:billing,true|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'ppn' => 'nullable|numeric|min:0',
            ];

            $allRules = array_merge($connectionRules, $memberRules, $paymentRules);
            $validated = $request->validate($allRules);

            DB::beginTransaction();

            // 1. Create Connection
            $connection = Connection::create([
                'type' => $validated['type'],
                'username' => $request->username,
                'password' => $request->password,
                'mac_address' => $request->mac_address,
                'profile_id' => $validated['profile_id'],
                'group_id' => $groupId,
                'internet_number' => Connection::generateNomorLayanan($groupId),
                'isolir' => $request->input('isolir', false),
                'nas_id' => $request->nas_id,
                'area_id' => $request->area_id,
                'optical_id' => $request->optical_id,
            ]);

            // 2. Create Member
            $member = Member::create([
                'connection_id' => $connection->id,
                'group_id' => $groupId,
                'fullname' => $validated['fullname'],
                'phone_number' => $request->phone_number,
                'email' => $request->email,
                'id_card' => $request->id_card,
                'address' => $request->address,
                'billing' => $request->input('billing', false),
            ]);

            // 3. Create Payment Detail (if billing is enabled)
            $paymentDetail = null;
            if ($request->input('billing') == true) {
                // Calculate next invoice date
                $activeDate = new \DateTime($request->active_date ?? now());
                $nextInvoice = null;

                if ($request->billing_period === 'renewal') {
                    $nextInvoice = (clone $activeDate)->add(new \DateInterval('P1M'))->format('Y-m-d');
                }

                $paymentDetail = PaymentDetail::create([
                    'member_id' => $member->id,
                    'group_id' => $groupId,
                    'payment_type' => $validated['payment_type'],
                    'billing_period' => $validated['billing_period'],
                    'active_date' => $request->active_date,
                    'next_invoice' => $nextInvoice,
                    'last_invoice' => null,
                    'amount' => $validated['amount'],
                    'discount' => $request->input('discount', 0),
                    'ppn' => $request->input('ppn', 0),
                ]);
            }

            // 4. Create radius records for PPPoE
            if ($connection->type === 'pppoe' && $connection->username && $connection->password) {
                $this->createRadiusRecords($connection, $groupId);
            }

            DB::commit();

            // Load relationships for response
            $connection->load(['member.paymentDetail', 'profile', 'area', 'optical', 'nas']);

            ActivityLogged::dispatch('CREATE', null, [
                'connection' => $connection->toArray(),
                'member' => $member->toArray(),
                'payment_detail' => $paymentDetail ? $paymentDetail->toArray() : null,
            ]);

            return ResponseFormatter::success([
                'connection' => $connection,
                'member' => $member,
                'payment_detail' => $paymentDetail,
            ], 'Connection with member berhasil ditambahkan', 201);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * PUT /api/v1/connections/{id}
     * Update connection
     */
    public function update(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $connection = Connection::where('id', $id)->firstOrFail();

            if ($connection->group_id !== $user->group_id) {
                return response()->json(['message' => 'Connection tidak ditemukan!'], 403);
            }

            $oldData = $connection->toArray();

            // Validation
            $rules = [
                'profile_id' => 'required|exists:profiles,id',
                'isolir' => 'boolean',
                'nas_id' => 'nullable|exists:nas,id',
                'area_id' => 'nullable|exists:areas,id',
                'optical_id' => 'nullable|exists:optical_dists,id',
            ];

            if ($request->type === 'dhcp') {
                $rules['mac_address'] = 'required|string|max:17';
            } elseif ($request->type === 'pppoe') {
                $rules['username'] = [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('connections')->ignore($id)->where(fn($q) => $q->where('group_id', $user->group_id))
                ];
                $rules['password'] = 'required|string';
            }

            $validated = $request->validate($rules);

            $groupId = $connection->group_id;
            $oldUsername = $connection->username ?? $connection->mac_address;
            $newUsername = trim($request->username ?? $request->mac_address);
            $newPassword = $request->password;

            DB::beginTransaction();

            // Update connection
            $connection->update([
                'username' => $newUsername,
                'password' => $newPassword,
                'mac_address' => $request->mac_address,
                'profile_id' => $validated['profile_id'],
                'isolir' => $request->input('isolir', false),
                'nas_id' => $request->nas_id,
                'area_id' => $request->area_id,
                'optical_id' => $request->optical_id,
            ]);

            // Update radius records
            DB::connection('radius')->table('radcheck')
                ->where('username', $oldUsername)
                ->where('group_id', $groupId)
                ->delete();

            DB::connection('radius')->table('radusergroup')
                ->where('username', $oldUsername)
                ->where('group_id', $groupId)
                ->delete();

            // Re-create radius records
            DB::connection('radius')->table('radcheck')->insert([
                'username' => $newUsername,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $newPassword ?? '',
                'group_id' => $groupId
            ]);

            $profile = DB::table('profiles')->find($validated['profile_id']);
            if ($profile) {
                DB::connection('radius')->table('radusergroup')->insert([
                    [
                        'username' => $newUsername,
                        'groupname' => 'mitra_' . $groupId,
                        'priority' => 1,
                        'group_id' => $groupId
                    ],
                    [
                        'username' => $newUsername,
                        'groupname' => $profile->name . '-' . $groupId,
                        'priority' => 1,
                        'group_id' => $groupId
                    ]
                ]);
            }

            DB::connection('radius')->table('radreply')
                ->where('username', $oldUsername)
                ->where('group_id', $groupId)
                ->update(['username' => $newUsername]);

            DB::commit();

            ActivityLogged::dispatch('UPDATE', $oldData, $connection);

            return ResponseFormatter::success($connection, 'Connection berhasil diperbarui', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * POST /api/v1/connections/{id}/toggle-isolir
     * Toggle isolir status
     */
    public function toggleIsolir(Request $request, $id)
    {
        try {
            $user = $this->getAuthUser();
            $connection = Connection::where('id', $id)->firstOrFail();

            if ($connection->group_id !== $user->group_id) {
                return response()->json(['message' => 'Connection tidak ditemukan!'], 403);
            }

            $oldData = $connection->toArray();
            $username = $connection->username ?? $connection->mac_address;

            DB::beginTransaction();

            $newIsolirStatus = !$connection->isolir;
            $connection->update(['isolir' => $newIsolirStatus]);

            DB::commit();

            ActivityLogged::dispatch('UPDATE', $oldData, $connection);

            return ResponseFormatter::success([
                'isolir' => $newIsolirStatus
            ], 'Status isolir berhasil diubah', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * DELETE /api/v1/connections/{id}
     * Delete connection
     */
    public function destroy($id)
    {
        try {
            $user = $this->getAuthUser();
            $connection = Connection::with(['member.paymentDetail'])->findOrFail($id);

            if ($connection->group_id !== $user->group_id) {
                return response()->json(['message' => 'Connection tidak ditemukan!'], 403);
            }

            $username = $connection->username ?? $connection->mac_address;

            DB::beginTransaction();

            // Delete payment detail if exists
            if ($connection->member && $connection->member->paymentDetail) {
                $connection->member->paymentDetail->delete();
            }

            // Delete from radius tables
            DB::connection('radius')->table('radcheck')
                ->where('username', $username)
                ->delete();

            DB::connection('radius')->table('radusergroup')
                ->where('username', $username)
                ->delete();

            DB::connection('radius')->table('radacct')
                ->where('username', $username)
                ->delete();

            $deletedData = $connection->toArray();
            $connection->delete();

            DB::commit();

            ActivityLogged::dispatch('DELETE', null, $deletedData);

            return ResponseFormatter::success($deletedData, 'Connection berhasil dihapus', 200);
        } catch (\Throwable $th) {
            DB::rollBack();
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * GET /api/v1/connections/{username}/sessions
     * Get connection session history
     */
    public function getSessions($username)
    {
        try {
            $user = $this->getAuthUser();

            $startOfMonth = now()->startOfMonth();
            $endOfMonth = now()->endOfMonth();

            $sessions = DB::connection('radius')->table('radacct as ra')
                ->where('ra.username', $username)
                ->select([
                    'ra.acctsessionid as session_id',
                    'ra.username',
                    'ra.acctstarttime as login_time',
                    'ra.acctstoptime as logout_time',
                    'ra.acctupdatetime as last_update',
                    'ra.framedipaddress as ip_address',
                    'ra.callingstationid as mac_address',
                    'ra.acctinputoctets as upload',
                    'ra.acctoutputoctets as download',
                    'ra.acctsessiontime as uptime'
                ])
                ->orderByDesc('ra.acctstarttime')
                ->paginate(20);

            // Calculate monthly usage
            $usageThisMonth = DB::connection('radius')->table('radacct')
                ->where('username', $username)
                ->whereBetween('acctstarttime', [$startOfMonth, $endOfMonth])
                ->selectRaw('SUM(acctinputoctets) as total_upload, SUM(acctoutputoctets) as total_download')
                ->first();

            return ResponseFormatter::success([
                'sessions' => $sessions,
                'monthly_usage' => [
                    'upload' => $usageThisMonth->total_upload ?? 0,
                    'download' => $usageThisMonth->total_download ?? 0,
                    'total' => ($usageThisMonth->total_upload ?? 0) + ($usageThisMonth->total_download ?? 0)
                ]
            ], 'Session data berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    /**
     * Private helper: Create radius records
     */
    private function createRadiusRecords($connection, $groupId)
    {
        $username = $connection->username ?? $connection->mac_address;

        DB::connection('radius')->table('radcheck')->insert([
            'username' => $username,
            'attribute' => 'Cleartext-Password',
            'op' => ':=',
            'value' => $connection->password ?? '',
            'group_id' => $groupId
        ]);

        $profile = DB::table('profiles')->find($connection->profile_id);
        if ($profile) {
            DB::connection('radius')->table('radusergroup')->insert([
                [
                    'username' => $username,
                    'groupname' => 'mitra_' . $groupId,
                    'priority' => 1,
                    'group_id' => $groupId
                ],
                [
                    'username' => $username,
                    'groupname' => $profile->name . '-' . $groupId,
                    'priority' => 1,
                    'group_id' => $groupId
                ]
            ]);
        }
    }
}
