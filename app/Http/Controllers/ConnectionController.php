<?php

namespace App\Http\Controllers;

use App\Imports\PppoeAccountsImport;
use App\Exports\ImportErrorsExport;
use App\Models\ImportErrorLog;
use App\Helpers\ActivityLogger;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Http\Request;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Services\ConnectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Models\Area;
use App\Models\Batch;
use App\Models\OpticalDist;
use App\Models\Profiles;
use App\Models\Member;
use DateInterval;
use DateTime;
use Yajra\DataTables\DataTables;
use App\Models\Nas;
use Google\Service\AnalyticsReporting\Activity;
use Illuminate\Validation\Rule;

class ConnectionController extends Controller
{
    /**
     * Find available IP in isolir range
     */
    private static function findAvailableIsolirIp($usedIps)
    {
        $baseIp = ip2long('172.30.0.2');
        $endIp = ip2long('172.30.255.254');

        for ($ip = $baseIp; $ip <= $endIp; $ip++) {
            $currentIp = long2ip($ip);
            if (!in_array($currentIp, $usedIps)) {
                return $currentIp;
            }
        }

        return null;
    }

    public function index()
    {
        $user = Auth::user();
        $area = Area::where('group_id', $user->group_id)->get();
        $odp = OpticalDist::where('group_id', $user->group_id)
            ->where('type', 'ODP')
            ->get();
        $profile = Profiles::where('group_id', $user->group_id)->get();
        $member = Member::where('group_id', $user->group_id)->get();

        $query = Connection::where('group_id', $user->group_id);
        $total = $query->count();
        $isolir = (clone $query)->where('isolir', true)->count();
        $active = (clone $query)->where('isolir', false)->count();

        $nas = Nas::where('group_id', $user->group_id)->get();



        return view('pages.ppp.pppoe.index', compact('nas', 'profile', 'member', 'area', 'odp', 'total', 'isolir', 'active'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $query = Connection::where('group_id', $user->group_id)
            ->orderBy('created_at', 'desc')
            ->with(['profile', 'member', 'area', 'optical', 'nas']);

        // Filter berdasarkan Status (isolir/active)
        if ($request->has('status_filter') && !empty($request->status_filter)) {
            if ($request->status_filter == 'CREATE') { // Active
                $query->where('isolir', 0);
            } elseif ($request->status_filter == 'READ') { // Suspend
                $query->where('isolir', 1);
            }
        }

        // Filter berdasarkan Profile - handle space value di HTML
        if ($request->has('profile_filter') && !empty($request->profile_filter) && trim($request->profile_filter) != '') {
            $query->where('profile_id', $request->profile_filter);
        }
        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('profile', fn($account) => $account->profile->name ?? '-')
            ->addColumn('togle', function ($account) {
                $checked = $account->isolir ? '' : 'checked';
                return '
            <div class="form-check form-switch">
                <input class="form-check-input toggle-isolir" data-id="' . $account->id . '" data-name="' . $account->username . '" type="checkbox" ' . $checked . '
                    id="flexSwitchCheck' . $account->id . '">
            </div>';
            })
            ->addColumn('member', fn($account) => $account->member->fullname ?? '-')
            ->addColumn('type', fn($row) => ucfirst($row->type))
            ->addColumn('nas', fn($row) => $row->nas->name ?? 'All')
            ->addColumn('area', fn($row) => $row->area->name ?? '-')
            ->addColumn('optical', fn($row) => $row->optical->name ?? '-')
            ->addColumn('status', function ($account) {
                if ($account->isolir) {
                    return "<span class='text-warning'>Isolir</span>";
                } else {
                    return "<span class='text-success'>Active</span>";
                }
            })
            ->addColumn('internet', function ($account) {
                $latestSession = DB::connection('radius')
                    ->table('radacct')
                    ->where('username', $account->username ?? $account->mac_address)
                    ->orderBy('acctstarttime', 'DESC')
                    ->first();

                return $latestSession && is_null($latestSession->acctstoptime)
                    ? "<span class='text-success'>Online</span>"
                    : "<span class='text-danger'>Offline</span>";
            })
            ->addColumn('action', function ($account) {
                return '
            <div class="btn-group" role="group">
                <button id="btn-session" class="btn btn-outline-primary ms-1" data-username="' . ($account->username ?? $account->mac_address) . '"
                     data-id="' . $account->id . '"><i class="fa-solid fa-clock-rotate-left"></i></button>
                <button id="btn-edit" class="btn btn-outline-warning ms-1"
                    data-username="' . $account->username . '"
                    data-password="' . $account->password . '"
                    data-type="' . $account->type . '"
                    data-mac_address="' . $account->mac_address . '"
                    data-profile="' . $account->profile_id . '"
                    data-area="' . $account->area_id . '"
                    data-optical="' . $account->optical_id . '"
                    data-nas="' . $account->nas_id . '"
                    data-isolir="' . ($account->isolir ? 1 : 0) . '"
                    data-billing="' . ($account->billing_active ? 1 : 0) . '"
                    data-id="' . $account->id . '"><i class="fa-solid fa-pencil"></i></button>
                <button id="btn-delete" class="btn btn-outline-danger ms-1" data-username="' . ($account->username ?? $account->mac_address) . '"
                     data-id="' . $account->id . '"><i class="fa-solid fa-trash"></i></button>
            </div>';
            })
            ->rawColumns(['status', 'togle', 'action', 'internet'])
            ->make(true);
    }

    public function getSession($username)
    {
        $user = Auth::user();
        // Ambil tanggal awal & akhir bulan ini
        $startOfMonth = now()->startOfMonth();
        $endOfMonth = now()->endOfMonth();

        // Query untuk sesi user tertentu
        $query = DB::connection('radius')->table('radacct as ra')
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
            ->orderByDesc('ra.acctstarttime');

        // Hitung total upload & download bulan ini
        $usageThisMonth = DB::connection('radius')->table('radacct')
            ->where('username', $username)
            ->whereBetween('acctstarttime', [$startOfMonth, $endOfMonth])
            ->selectRaw('SUM(acctinputoctets) as total_upload, SUM(acctoutputoctets) as total_download')
            ->first();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('login_time', fn($row) => "<small>{$row->login_time}</small>")
            ->addColumn('last_update', fn($row) => "<small>{$row->last_update}</small>")
            ->addColumn('ip_mac', fn($row) => "<small>{$row->ip_address}<br>{$row->mac_address}</small>")
            ->addColumn('upload', fn($row) => "<small>" . formatBytes($row->upload) . "</small>")
            ->addColumn('download', fn($row) => "<small>" . formatBytes($row->download) . "</small>")
            ->addColumn('uptime', function ($row) {
                $seconds = $row->uptime;
                $days = floor($seconds / 86400);
                $time = gmdate("H:i:s", $seconds % 86400);
                return "<small>" . ($days > 0 ? "{$days}d " : "") . $time . "</small>";
            })
            ->with([
                'total_upload' => formatBytes($usageThisMonth->total_upload ?? 0),
                'total_download' => formatBytes($usageThisMonth->total_download ?? 0)
            ])
            ->rawColumns(['login_time', 'last_update', 'ip_mac', 'upload', 'download', 'uptime'])
            ->make(true);
    }

    public function store(Request $request)
    {
        $user = Auth::user();
        $groupId = $user->group_id;

        // Validation rules
        $rules = [
            'type' => 'required|string|in:pppoe,dhcp',
            'profile_id' => 'required|exists:profiles,id',
            'nas_id' => 'nullable|exists:nas,id',
            'area_id' => 'nullable|exists:areas,id',
            'optical_id' => 'nullable|exists:optical_dists,id',
            'billing_active' => 'boolean',
            'isolir' => 'boolean',
            'active_date' => 'nullable|date',
        ];

        // Add type-specific validation
        if ($request->type === 'pppoe') {
            $rules['username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('connections', 'username')->where(function ($query) use ($groupId) {
                    return $query->where('group_id', $groupId);
                }),
            ];
            $rules['password'] = 'required|string';
        } elseif ($request->type === 'dhcp') {
            $rules['mac_address'] = 'required|string|max:17';
        }

        $request->validate($rules);

        try {
            DB::beginTransaction();

            $data = Connection::create([
                'type' => $request->type,
                'username' => $request->username,
                'password' => $request->password,
                'mac_address' => $request->mac_address,
                'profile_id' => $request->profile_id,
                'group_id' => $groupId,
                'internet_number' => Connection::generateNomorLayanan($groupId),
                'billing_active' => $request->input('billing_active', false),
                'isolir' => $request->input('isolir', false),
                'nas_id' => $request->nas_id,
                'area_id' => $request->area_id,
                'optical_id' => $request->optical_id,
            ]);

            // Create radius records for PPPoE
            if ($data->type === 'pppoe' && $data->username && $data->password) {
                $this->createRadiusRecords($data, $groupId);
            }

            DB::commit();
            ActivityLogController::logCreate($data);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil disimpan!',
                'data' => $data
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            ActivityLogController::logCreateF([$e->getMessage()]);

            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function createWithMember(Request $request)
    {
        $groupId = Auth::user()->group_id;

        // Base validation for connection
        $connectionRules = [
            'type' => 'required|string|in:pppoe,dhcp',
            'profile_id' => 'required|exists:profiles,id',
            'nas_id' => 'nullable|exists:nas,id',
            'area_id' => 'nullable|exists:areas,id',
            'optical_id' => 'nullable|exists:optical_dists,id',
            'isolir' => 'boolean',
            'active_date' => 'nullable|date',
        ];

        // Add type-specific validation
        if ($request->type === 'pppoe') {

            $connectionRules['username'] = [
                'required',
                'string',
                'max:255',
                Rule::unique('connections', 'username')->where(function ($query) use ($groupId) {
                    return $query->where('group_id', $groupId);
                }),
            ];
            $connectionRules['password'] = 'required|string';
        } else if ($request->type === 'dhcp') {
            $connectionRules['mac_address'] = 'required|string|max:17';
        }

        $request->validate($connectionRules);

        // Additional validation for billing
        if ($request->input('add_on_billing') == 1) {
            $request->validate([
                'fullname' => 'required|string|max:255',
                'phone_number' => 'nullable|string|max:20',
                'email' => 'nullable|email',
                'id_card' => 'nullable|string|max:16',
                'address' => 'nullable|string|max:500',
                'billing' => 'boolean',
                'payment_type' => 'required|string|in:prabayar,pascabayar',
                'billing_period' => 'required|string|in:fixed,renewal',
                'amount' => 'required|numeric|min:0',
                'discount' => 'nullable|numeric|min:0',
                'ppn' => 'nullable|numeric|min:0',
            ]);
        } else {
            return $this->store($request);
        }

        $data = $request->only([
            'type',
            'username',
            'password',
            'mac_address',
            'profile_id',
            'nas_id',
            'area_id',
            'optical_id',
            'isolir',
            'active_date',
            'member_id',
            'fullname',
            'phone_number',
            'email',
            'id_card',
            'mac_address',
            'address',
            'payment_type',
            'billing_period',
            'amount',
            'discount',
            'ppn'
        ]);


        //Data For Activity Log
        $memberData = [
            'fullname' => $data['fullname'],
            'phone_number' => $data['phone_number'],
            'email' => $data['email'],
            'id_card' => $data['id_card'],
            'address' => $data['address'],
        ];

        $connectionData = [
            'type' => $data['type'],
            'username' => $data['username'] ?? null,
            'password' => $data['password'] ?? null,
            'mac_address' => $data['mac_address'] ?? null,
            'profile_id' => $data['profile_id'],
            'nas_id' => $data['nas_id'] ?? null,
            'area_id' => $data['area_id'],
            'optical_id' => $data['optical_id'],
            'isolir' => $data['isolir'] ?? null,
        ];

        $paymentDetailData = [
            'payment_type' => $data['payment_type'],
            'billing_period' => $data['billing_period'],
            'amount' => $data['amount'],
            'discount' => $data['discount'],
            'ppn' => $data['ppn'],
        ];

        // Calculate next invoice date
        if ($data['billing_period'] === 'renewal') {
            $activeDate = new DateTime($data['active_date'] ?? now());
            $nextInvoice = $activeDate->add(new DateInterval('P1M'))->format('Y-m-d');
            $data['next_invoice'] = $nextInvoice;
        }

        $data['billing'] = $request->input('add_on_billing') == 1;

        try {
            $result = (new ConnectionService())->createOrUpdateMemberConnectionPaymentDetail($data);
            ActivityLogController::logCreate($memberData, 'members');
            ActivityLogController::logCreate($connectionData, 'connections');
            ActivityLogController::logCreate($paymentDetailData, 'payment_details');
            return response()->json($result, $result['success'] ? 201 : 500);
        } catch (\Exception $e) {
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function update(Request $request, $id)
    {
        $connection = Connection::findOrFail($id);
        $oldData = $connection->toArray();

        // Validation rules
        $rules = [
            'profile_id' => 'required|exists:profiles,id',
            'billing_active' => 'boolean',
            'isolir' => 'boolean',
            'nas_id' => 'nullable|exists:nas,id',
            'area_id' => 'nullable|exists:areas,id',
            'optical_id' => 'nullable|exists:optical_dists,id',
        ];

        // Add type-specific validation
        if ($request->type === 'dhcp') {
            $rules['mac_address'] = 'required|string|max:17';
        } elseif ($request->type === 'pppoe') {
            $rules['username'] = "required|string|max:255|unique:connections,username,$id,id,group_id," . Auth::user()->group_id;
            $rules['password'] = 'required|string|';
        }

        $request->validate($rules);

        $groupId = $connection->group_id;
        $oldUsername = $connection->username ?? $connection->mac_address;
        $newUsername = trim($request->username ?? $request->mac_address);

        try {
            DB::beginTransaction();

            // Update radius tables for PPPoE
            if ($connection->type === 'pppoe' && $oldUsername && $newUsername) {
                $this->updateRadiusRecords($oldUsername, $newUsername, $request->password, $request->profile_id, $groupId);
            }

            // Update connection
            $connection->update([
                'username' => $request->username,
                'password' => $request->password,
                'mac_address' => $request->mac_address,
                'profile_id' => $request->profile_id,
                'billing_active' => $request->input('billing_active', false),
                'isolir' => $request->input('isolir', false),
                'nas_id' => $request->nas_id,
                'area_id' => $request->area_id,
                'optical_id' => $request->optical_id,
            ]);

            DB::commit();
            ActivityLogController::logUpdate($oldData, 'connections', $connection);

            return response()->json([
                'success' => true,
                'message' => 'Data connection berhasil diperbarui.',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function assignIsolirIp(Request $request, $id)
    {
        $user = Auth::user();
        $connection = Connection::findOrFail($id);
        $oldData = $connection->toArray();
        $isolirMode = GlobalSettings::where('group_id', $user->group_id)->first();
        $username = $connection->username ?? $connection->mac_address;

        try {
            DB::beginTransaction();

            if (!$connection->isolir) {
                // Activate isolir
                $connection->update(['isolir' => true]);

                if ($isolirMode && $isolirMode->isolir_mode == true) {
                    // Get used IPs and assign isolir IP
                    $usedIps = DB::connection('radius')->table('radreply')
                        ->where('attribute', 'Framed-IP-Address')
                        ->pluck('value')->toArray();

                    $isolirIp = $this->findAvailableIsolirIp($usedIps);

                    DB::connection('radius')->table('radreply')
                        ->where('username', $username)
                        ->where('attribute', 'Framed-IP-Address')
                        ->delete();

                    DB::connection('radius')->table('radreply')->insert([
                        'username' => $username,
                        'attribute' => 'Framed-IP-Address',
                        'op' => ':=',
                        'value' => $isolirIp,
                        'group_id' => $user->group_id
                    ]);

                    $message = "IP isolir $isolirIp diberikan ke $username";
                } else {
                    // Disable account
                    DB::connection('radius')->table('radcheck')->updateOrInsert(
                        ['username' => $username, 'attribute' => 'Auth-Type', 'group_id' => $user->group_id],
                        ['op' => ':=', 'value' => 'Reject']
                    );

                    $message = "Akun $username telah dinonaktifkan";
                }
            } else {
                // Remove isolir
                $connection->update(['isolir' => false]);

                // Remove isolir IP
                DB::connection('radius')->table('radreply')
                    ->where('username', $username)
                    ->where('attribute', 'Framed-IP-Address')
                    ->delete();

                // Remove reject
                DB::connection('radius')->table('radcheck')
                    ->where('username', $username)
                    ->where('attribute', 'Auth-Type')
                    ->where('value', 'Reject')
                    ->delete();

                $message = "Akun $username telah diaktifkan kembali";
            }

            DB::commit();
            ActivityLogController::logUpdate($oldData, 'connections', $connection);

            return response()->json(['message' => $message], 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json(['error' => $e->getMessage()], 500);
        }
    }

    public function destroy($id)
    {
        $connection = Connection::findOrFail($id);
        $username = $connection->username ?? $connection->mac_address;

        try {
            DB::beginTransaction();

            // Delete from all radius tables
            DB::connection('radius')->table('radcheck')
                ->where('username', $username)
                ->where('group_id', $connection->group_id)
                ->delete();

            DB::connection('radius')->table('radusergroup')
                ->where('username', $username)
                ->where('group_id', $connection->group_id)
                ->delete();

            DB::connection('radius')->table('radacct')
                ->where('username', $username)
                ->delete();

            $connection->delete();

            DB::commit();
            ActivityLogController::logDelete($connection);

            return response()->json([
                'success' => true,
                'message' => 'Data berhasil dihapus!',
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal menghapus data: ' . $e->getMessage(),
            ], 500);
        }
    }

    public function import(Request $request)
    {
        // Validate request
        $validator = Validator::make($request->all(), [
            'file' => 'required|file|mimes:csv,xls,xlsx|max:10240', // Max 10MB
            'dry_run' => 'boolean' // Optional: for testing without actual import
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        $file = $request->file('file');
        $groupId = $request->user()->group_id ?? null;
        $userId = $request->user()->id ?? null;
        $isDryRun = $request->boolean('dry_run', false);

        if (!$groupId) {
            Log::error('Import attempted without group_id', ['user_id' => $userId]);
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized: missing group'
            ], 403);
        }

        try {
            // Log import start
            Log::info('Import initiated', [
                'file_name' => $file->getClientOriginalName(),
                'file_size' => $file->getSize(),
                'user_id' => $userId,
                'group_id' => $groupId,
                'dry_run' => $isDryRun
            ]);

            // Create import instance
            $import = new PppoeAccountsImport($groupId);

            // Execute import
            if ($isDryRun) {
                // For dry run, just validate without processing
                $collection = Excel::toCollection($import, $file);
                $totalRows = $collection->first()->count();

                return response()->json([
                    'success' => true,
                    'message' => 'Dry run completed',
                    'total_rows' => $totalRows,
                    'dry_run' => true
                ]);
            }

            // Actual import
            Excel::import($import, $file);
            $batchId = $import->getImportBatchId();

            // Update batch record with additional info
            DB::table('import_batches')
                ->where('id', $batchId)
                ->update([
                    'imported_by' => $userId,
                    'file_name' => $file->getClientOriginalName(),
                    'metadata' => json_encode([
                        'file_size' => $file->getSize(),
                        'mime_type' => $file->getMimeType(),
                        'original_name' => $file->getClientOriginalName()
                    ])
                ]);

            // Log activity
            ActivityLogController::logImportStart(
                [
                    'batch_id' => $batchId,
                    'file_size' => $file->getSize(),
                    'mime_type' => $file->getMimeType(),
                    'dry_run' => $isDryRun,
                ],
                'connections'
            );

            return response()->json([
                'success' => true,
                'message' => 'Import started successfully',
                'batch_id' => $batchId,
                'info' => 'Use batch_id to check import status'
            ]);
        } catch (\Maatwebsite\Excel\Validators\ValidationException $e) {
            // Handle Excel validation errors
            $failures = $e->failures();
            $errors = [];

            foreach ($failures as $failure) {
                $errors[] = [
                    'row' => $failure->row(),
                    'attribute' => $failure->attribute(),
                    'errors' => $failure->errors(),
                    'values' => $failure->values()
                ];
            }

            Log::error('Excel validation failed', [
                'file_name' => $file->getClientOriginalName(),
                'errors' => $errors
            ]);

            ActivityLogController::logImportF(
                [
                    'file_name' => $file->getClientOriginalName(),
                    'errors' => 'IMPORT_VALIDATION_FAILED',
                    'validation_errors' => $errors
                ],
                'connections'
            );

            return response()->json([
                'success' => false,
                'message' => 'Validation errors in Excel file',
                'errors' => $errors
            ], 422);
        } catch (\Exception $e) {
            Log::error('Import failed', [
                'file' => $file->getClientOriginalName(),
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            ActivityLogger::log(
                'IMPORT_FAILED',
                'pppoe_import',
                [
                    'file_name' => $file->getClientOriginalName(),
                    'error' => $e->getMessage()
                ],
                $userId,
                null
            );

            return response()->json([
                'success' => false,
                'message' => 'Import failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred during import'
            ], 500);
        }
    }

    public function importStatus($batchId)
    {
        $batch = Batch::findOrFail($batchId);

        $errors = [];
        if (in_array($batch->status, ['completed_with_errors', 'failed'])) {
            $errors = ImportErrorLog::where('import_batch_id', $batchId)
                ->take(5) // ambil 5 error terbaru
                ->get(['row_number', 'username', 'error_message']);
        }

        return response()->json([
            'status' => $batch->status,
            'processed' => $batch->processed_rows,
            'total' => $batch->total_rows,
            'failed' => $batch->failed_rows,
            'percentage' => $batch->total_rows > 0 ? round(($batch->processed_rows / $batch->total_rows) * 100, 2) : 0,
            'errors' => $errors,
        ]);
    }

    public function getImportStatus(Request $request, $batchId)
    {
        $groupId = $request->user()->group_id;

        $batch = DB::table('import_batches')
            ->where('id', $batchId)
            ->where('group_id', $groupId)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found'
            ], 404);
        }

        // Calculate progress
        $progress = 0;
        if ($batch->total_rows > 0) {
            $progress = round(($batch->processed_rows / $batch->total_rows) * 100, 2);
        }

        // Get error summary
        $errorSummary = ImportErrorLog::where('import_batch_id', $batchId)
            ->select('error_type', DB::raw('count(*) as count'))
            ->groupBy('error_type')
            ->get();

        // Get recent errors (last 5)
        $recentErrors = ImportErrorLog::where('import_batch_id', $batchId)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get(['id', 'row_number', 'username', 'error_type', 'error_message', 'created_at']);

        // Calculate estimated time remaining
        $estimatedTimeRemaining = null;
        if ($batch->status === 'processing' && $batch->processed_rows > 0) {
            $elapsedSeconds = now()->diffInSeconds($batch->started_at);
            $rowsPerSecond = $batch->processed_rows / max($elapsedSeconds, 1);
            $remainingRows = $batch->total_rows - $batch->processed_rows;
            $estimatedTimeRemaining = $rowsPerSecond > 0 ?
                round($remainingRows / $rowsPerSecond) : null;
        }

        return response()->json([
            'success' => true,
            'batch' => [
                'id' => $batch->id,
                'status' => $batch->status,
                'progress' => $progress,
                'total_rows' => $batch->total_rows,
                'processed_rows' => $batch->processed_rows,
                'failed_rows' => $batch->failed_rows,
                'file_name' => $batch->file_name,
                'started_at' => $batch->started_at,
                'completed_at' => $batch->completed_at,
                'estimated_time_remaining' => $estimatedTimeRemaining
            ],
            'error_summary' => $errorSummary,
            'recent_errors' => $recentErrors
        ]);
    }


    public function getImportErrors(Request $request, $batchId)
    {
        $groupId = $request->user()->group_id;

        // Verify batch belongs to user's group
        $batch = DB::table('import_batches')
            ->where('id', $batchId)
            ->where('group_id', $groupId)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found'
            ], 404);
        }

        $query = ImportErrorLog::where('import_batch_id', $batchId);

        // Apply filters
        if ($request->has('error_type')) {
            $query->where('error_type', $request->input('error_type'));
        }

        if ($request->has('resolved')) {
            $query->where('resolved', $request->boolean('resolved'));
        }

        if ($request->has('username')) {
            $query->where('username', 'like', '%' . $request->input('username') . '%');
        }

        // Sorting
        $sortBy = $request->input('sort_by', 'row_number');
        $sortOrder = $request->input('sort_order', 'asc');
        $query->orderBy($sortBy, $sortOrder);

        // Pagination
        $perPage = min($request->input('per_page', 20), 100); // Max 100 per page
        $errors = $query->paginate($perPage);

        return response()->json([
            'success' => true,
            'batch_id' => $batchId,
            'errors' => $errors
        ]);
    }


    public function exportImportErrors(Request $request, $batchId)
    {
        $groupId = $request->user()->group_id;

        // Verify batch
        $batch = DB::table('import_batches')
            ->where('id', $batchId)
            ->where('group_id', $groupId)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found'
            ], 404);
        }

        $errors = ImportErrorLog::where('import_batch_id', $batchId)
            ->orderBy('row_number')
            ->get();

        if ($errors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No errors to export'
            ], 404);
        }

        // Create Excel export
        $export = new ImportErrorsExport($errors, $batch);
        $fileName = 'import_errors_' . $batchId . '_' . now()->format('Y-m-d_His') . '.xlsx';

        // Log export
        ActivityLogger::log(
            'IMPORT_ERRORS_EXPORTED',
            'pppoe_import',
            [
                'batch_id' => $batchId,
                'error_count' => $errors->count()
            ],
            $request->user()->id,
            null
        );

        return Excel::download($export, $fileName);
    }


    public function retryFailedImports(Request $request, $batchId)
    {
        $groupId = $request->user()->group_id;
        $userId = $request->user()->id;

        // Verify batch
        $batch = DB::table('import_batches')
            ->where('id', $batchId)
            ->where('group_id', $groupId)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found'
            ], 404);
        }

        // Check if batch is already processing
        if ($batch->status === 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Import is still processing'
            ], 400);
        }

        // Get unresolved errors
        $errors = ImportErrorLog::where('import_batch_id', $batchId)
            ->where('resolved', false)
            ->whereNotIn('error_type', ['DUPLICATE_USERNAME', 'MISSING_USERNAME']) // Skip unretryable errors
            ->get();

        if ($errors->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No retryable errors found'
            ], 404);
        }

        $retryCount = 0;
        DB::beginTransaction();

        try {
            foreach ($errors as $error) {
                // Dispatch retry job
                \App\Jobs\ImportPppoeAccountRow::dispatch(
                    $error->row_data,
                    $groupId,
                    $error->row_number,
                    $batchId
                )->onQueue('imports-retry');

                $retryCount++;
            }

            // Update batch status
            DB::table('import_batches')
                ->where('id', $batchId)
                ->update([
                    'status' => 'processing',
                    'updated_at' => now()
                ]);

            DB::commit();

            // Log retry action
            ActivityLogger::log(
                'IMPORT_RETRY_INITIATED',
                'pppoe_import',
                [
                    'batch_id' => $batchId,
                    'retry_count' => $retryCount
                ],
                $userId,
                null
            );

            return response()->json([
                'success' => true,
                'message' => "Retrying {$retryCount} failed imports",
                'retry_count' => $retryCount
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to retry imports', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to initiate retry'
            ], 500);
        }
    }

    public function resolveImportError(Request $request, $errorId)
    {
        $userId = $request->user()->id;
        $notes = $request->input('notes', '');

        $error = ImportErrorLog::find($errorId);

        if (!$error) {
            return response()->json([
                'success' => false,
                'message' => 'Error not found'
            ], 404);
        }

        // Verify user has access to this error (same group)
        $batch = DB::table('import_batches')
            ->where('id', $error->import_batch_id)
            ->where('group_id', $request->user()->group_id)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 403);
        }

        try {
            $error->markAsResolved($userId, $notes);

            // Update batch failed count
            $unresolvedCount = ImportErrorLog::where('import_batch_id', $error->import_batch_id)
                ->where('resolved', false)
                ->count();

            DB::table('import_batches')
                ->where('id', $error->import_batch_id)
                ->update([
                    'failed_rows' => $unresolvedCount,
                    'updated_at' => now()
                ]);

            // Log resolution
            ActivityLogger::log(
                'IMPORT_ERROR_RESOLVED',
                'pppoe_import',
                [
                    'error_id' => $errorId,
                    'batch_id' => $error->import_batch_id,
                    'notes' => $notes
                ],
                $userId,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Error marked as resolved'
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to resolve error', [
                'error_id' => $errorId,
                'exception' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to resolve error'
            ], 500);
        }
    }
    public function getImportStatistics(Request $request)
    {
        $groupId = $request->user()->group_id;
        $period = $request->input('period', 30); // Days

        $stats = [
            'summary' => [
                'total_imports' => DB::table('import_batches')
                    ->where('group_id', $groupId)
                    ->where('created_at', '>=', now()->subDays($period))
                    ->count(),

                'successful_imports' => DB::table('import_batches')
                    ->where('group_id', $groupId)
                    ->where('status', 'completed')
                    ->where('created_at', '>=', now()->subDays($period))
                    ->count(),

                'imports_with_errors' => DB::table('import_batches')
                    ->where('group_id', $groupId)
                    ->whereIn('status', ['completed_with_errors', 'failed'])
                    ->where('created_at', '>=', now()->subDays($period))
                    ->count(),

                'total_rows_processed' => DB::table('import_batches')
                    ->where('group_id', $groupId)
                    ->where('created_at', '>=', now()->subDays($period))
                    ->sum('processed_rows'),

                'total_errors' => ImportErrorLog::where('group_id', $groupId)
                    ->where('created_at', '>=', now()->subDays($period))
                    ->count(),

                'unresolved_errors' => ImportErrorLog::where('group_id', $groupId)
                    ->where('resolved', false)
                    ->count()
            ],

            'error_breakdown' => ImportErrorLog::where('group_id', $groupId)
                ->where('created_at', '>=', now()->subDays($period))
                ->select('error_type', DB::raw('count(*) as count'))
                ->groupBy('error_type')
                ->orderByDesc('count')
                ->limit(10)
                ->get(),

            'recent_batches' => DB::table('import_batches')
                ->where('group_id', $groupId)
                ->orderByDesc('created_at')
                ->limit(5)
                ->get(['id', 'file_name', 'status', 'total_rows', 'failed_rows', 'created_at']),

            'daily_imports' => DB::table('import_batches')
                ->where('group_id', $groupId)
                ->where('created_at', '>=', now()->subDays(7))
                ->select(
                    DB::raw('DATE(created_at) as date'),
                    DB::raw('count(*) as count'),
                    DB::raw('sum(processed_rows) as rows_processed')
                )
                ->groupBy('date')
                ->orderBy('date')
                ->get()
        ];

        return response()->json([
            'success' => true,
            'period_days' => $period,
            'statistics' => $stats
        ]);
    }

    public function deleteImportBatch(Request $request, $batchId)
    {
        $groupId = $request->user()->group_id;
        $userId = $request->user()->id;

        // Verify batch
        $batch = DB::table('import_batches')
            ->where('id', $batchId)
            ->where('group_id', $groupId)
            ->first();

        if (!$batch) {
            return response()->json([
                'success' => false,
                'message' => 'Import batch not found'
            ], 404);
        }

        // Don't delete if still processing
        if ($batch->status === 'processing') {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete batch that is still processing'
            ], 400);
        }

        DB::beginTransaction();

        try {
            // Delete errors
            ImportErrorLog::where('import_batch_id', $batchId)->delete();

            // Delete batch
            DB::table('import_batches')->where('id', $batchId)->delete();

            DB::commit();

            // Log deletion
            ActivityLogger::log(
                'IMPORT_BATCH_DELETED',
                'pppoe_import',
                [
                    'batch_id' => $batchId,
                    'file_name' => $batch->file_name
                ],
                $userId,
                null
            );

            return response()->json([
                'success' => true,
                'message' => 'Import batch deleted successfully'
            ]);
        } catch (\Exception $e) {
            DB::rollBack();

            Log::error('Failed to delete batch', [
                'batch_id' => $batchId,
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to delete batch'
            ], 500);
        }
    }

    public function downloadImportTemplate()
    {
        $headers = [
            'Username (Required)',
            'Password',
            'Profile Name (Required)',
            'Area',
            'ODP',
            'NAS ID',
            'Member Name',
            'Phone Number',
            'Email',
            'ID Card',
            'Address',
            'Billing (Ya/Tidak)',
            'Active Date (YYYY-MM-DD)',
            'Payment Type (prabayar/pascabayar)',
            'Billing Period (renewal/fixed)',
            'PPN (%)',
            'Discount',
            'Amount'
        ];

        $sampleData = [
            [
                'user001',
                'password123',
                'Profile-10Mbps',
                'Area Jakarta',
                'ODP-01',
                '1',
                'John Doe',
                '081234567890',
                'john@example.com',
                '1234567890123456',
                'Jl. Contoh No. 123',
                'Ya',
                '2025-01-01',
                'pascabayar',
                'renewal',
                '11',
                '0',
                '150000'
            ]
        ];

        $export = new class($headers, $sampleData) implements
            \Maatwebsite\Excel\Concerns\FromArray,
            \Maatwebsite\Excel\Concerns\WithHeadings,
            \Maatwebsite\Excel\Concerns\WithEvents,
            \Maatwebsite\Excel\Concerns\ShouldAutoSize
        {
            protected $headers;
            protected $data;

            public function __construct($headers, $data)
            {
                $this->headers = $headers;
                $this->data = $data;
            }

            public function array(): array
            {
                return $this->data;
            }

            public function headings(): array
            {
                return $this->headers;
            }

            public function registerEvents(): array
            {
                return [
                    \Maatwebsite\Excel\Events\AfterSheet::class => function (\Maatwebsite\Excel\Events\AfterSheet $event) {
                        // Add instructions in first 6 rows
                        $event->sheet->insertNewRowBefore(1, 6);
                        $event->sheet->setCellValue('A1', 'PPPOE ACCOUNT IMPORT TEMPLATE');
                        $event->sheet->setCellValue('A2', 'Instructions:');
                        $event->sheet->setCellValue('A3', '1. Fill in the data starting from row 7');
                        $event->sheet->setCellValue('A4', '2. Username and Profile Name are required fields');
                        $event->sheet->setCellValue('A5', '3. Date format: YYYY-MM-DD');
                        $event->sheet->setCellValue('A6', '4. Do not modify the header row (row 7)');

                        // Style the header
                        $event->sheet->getStyle('A1')->getFont()->setBold(true)->setSize(14);
                        $event->sheet->getStyle('A7:R7')->getFont()->setBold(true);
                        $event->sheet->getStyle('A7:R7')->getFill()
                            ->setFillType(\PhpOffice\PhpSpreadsheet\Style\Fill::FILL_SOLID)
                            ->getStartColor()->setARGB('FFE0E0E0');
                    }
                ];
            }
        };

        return Excel::download($export, 'pppoe_import_template.xlsx');
    }

    /**
     * Create radius records for new connection
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
                'username' => $username,
                'groupname' => 'mitra_' . $groupId,
                'priority' => 1,
                'group_id' => $groupId
            ]);


            DB::connection('radius')->table('radusergroup')->insert([
                'username' => $username,
                'groupname' => $profile->name . '-' . $groupId,
                'priority' => 1,
                'group_id' => $groupId
            ]);
        }
    }

    /**
     * Update radius records for existing connection
     */
    private function updateRadiusRecords($oldUsername, $newUsername, $password, $profileId, $groupId)
    {
        // Update radcheck
        DB::connection('radius')->table('radcheck')
            ->where('username', $oldUsername)
            ->where('group_id', $groupId)
            ->update([
                'username' => $newUsername,
                'value' => $password ?? ''
            ]);

        // Update radreply
        $profile = DB::table('profiles')->find($profileId);
        if ($profile) {

            DB::connection('radius')->table('radreply')
                ->where('username', $oldUsername)
                ->where('group_id', $groupId)
                ->update([
                    'username' => $newUsername,
                    'value' => "{$profile->rate_rx}/{$profile->rate_tx} {$profile->burst_rx}/{$profile->burst_tx} {$profile->threshold_rx}/{$profile->threshold_tx} {$profile->time_rx}/{$profile->time_tx} {$profile->priority}"
                ]);
        }

        // Update radusergroup
        DB::connection('radius')->table('radusergroup')
            ->where('username', $oldUsername)
            ->where('group_id', $groupId)
            ->update(['username' => $newUsername]);
    }
}
