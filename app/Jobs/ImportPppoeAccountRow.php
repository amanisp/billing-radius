<?php

namespace App\Jobs;

use App\Models\Area;
use App\Models\Member;
use App\Models\OpticalDist;
use App\Models\Connection;
use App\Models\Profiles;
use App\Models\ImportErrorLog;
use App\Services\ConnectionService;
use App\Helpers\ActivityLogger;
use App\Http\Controllers\ActivityLogController;
use DateTime;
use DateInterval;
use Google\Service\AnalyticsReporting\Activity;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;

class ImportPppoeAccountRow implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $row;
    protected $group_id;
    protected $rowNumber;
    protected $importBatchId;
    protected $username;
    protected $mac;
    protected $userRole;

    public $timeout = 120;
    public $tries = 3;

    /**
     * NOTE: disesuaikan dengan dispatch sebelumnya yang mengirim 6 argumen.
     * Jika kamu ingin mengirim userRole juga, ubah dispatch menjadi mengirim 7 argumen
     * atau ubah signature lagi.
     */
    public function __construct(array $row, $group_id, $rowNumber = null, $importBatchId = null, $username = null, $userRole = null)
    {
        $this->row = $row;
        $this->group_id = $group_id;
        $this->rowNumber = $rowNumber;
        $this->importBatchId = $importBatchId;
        $this->username = $username;
        $this->mac = null;
        $this->userRole = $userRole;
    }

    public function handle()
    {
        $errors = [];
        $username = null;
        $mac = null;

        // --- Normalisasi row: pastikan array dengan numeric indexes ---
        $row = is_array($this->row) ? $this->row : (array)$this->row;
        // trim semua values untuk aman
        foreach ($row as $k => $v) {
            $row[$k] = is_null($v) ? '' : trim((string)$v);
        }

        // --- Tentukan type dari kolom 0 (jika ada) ---
        $type = strtolower(trim($this->row[0] ?? ''));

        if (in_array($type, ['static/dhcp', 'dhcp static', 'static'])) {
            $type = 'dhcp';
        } elseif (in_array($type, ['pppoe', 'ppp'])) {
            $type = 'pppoe';
        }


        // Jika import sebelumnya mengganti row[0] menjadi username untuk PPPoE,
        // kita support dua kemungkinan:
        // - Format lama: [type, mac, username, password, profile, ...]
        // - Format normalisasi di import: [username, ...] (untuk PPPoE)
        if ($type === 'pppoe') {
            // original: type in col 0, username in col 2
            $username = $row[2] ?? '';
            $mac = $row[1] ?? '';
        } else {
            // bukan PPPoE (anggap DHCP): mac biasanya di col 1, username bisa kosong
            $mac = $row[1] ?? '';
            // juga support case jika import sudah memindahkan username ke index 0
            if (!empty($row[0]) && strcasecmp($row[0], 'pppoe') !== 0) {
                // kemungkinan import sudah set row[0] = username
                $username = $row[0] ?? '';
            } else {
                // fallback username di col 2
                $username = $row[2] ?? '';
            }
        }

        // Jika import sisi collection set row[0] = username (new flow), detect:
        if (empty($username) && !empty($row[0]) && strcasecmp($row[0], 'pppoe') !== 0) {
            $username = $row[0];
        }

        // Debug log singkat (hapus atau turunkan level saat produksi)
        Log::debug('Import job received row', [
            'batch' => $this->importBatchId,
            'row_number' => $this->rowNumber,
            'raw_row' => $row,
            'detected_type' => $type,
            'username' => $username,
            'mac' => $mac
        ]);

        try {
            // VALIDASI: jika type PPPoE, username wajib; jika DHCP, mac wajib
            if ($type === 'pppoe') {
                if (empty($username)) {
                    $this->logImportError('Username is required for PPPoE', 'MISSING_USERNAME');
                    return;
                }
            } else {
                if (empty($mac)) {
                    $this->logImportError('Mac address is required for DHCP', 'MISSING_MAC_ADDRESS');
                    return;
                }
            }

            // Assign final values
            $username = trim((string)$username);
            $mac = trim((string)$mac);
            $password = trim((string)($row[3] ?? ''));

            // Validasi password (jika PPPoE)
            if ($type === 'pppoe' && empty($password)) {
                $errors[] = 'Password is empty';
            }

            // PROFILE di kolom yang semestinya: cek index yang benar (misal col 4)
            $profileName = trim((string)($row[4] ?? ''));
            if (empty($profileName)) {
                $this->logImportError('Profile name is required', 'MISSING_PROFILE', $username);
                return;
            }

            $profile = Profiles::where('name', $profileName)
                ->where('group_id', $this->group_id)
                ->first();

            if (!$profile) {
                $this->logImportError("Profile not found: {$profileName}", 'PROFILE_NOT_FOUND', $username);
                return;
            }

            // Area / Optical / NAS mapping: pastikan kolom index sesuai struktur excel yang kamu pakai.
            // Saya gunakan index 5 (area), 6 (optical), 7 (nas) seperti versi sebelumnya but
            // kalau struktur excel berbeda, sesuaikan index berikut.
            $area = null;
            if (!empty($row[5])) {
                $areaName = $row[5];
                $area = Area::where('name', $areaName)
                    ->where('group_id', $this->group_id)
                    ->first();

                if (!$area) {
                    $errors[] = "Area not found: {$areaName}";
                }
            }

            $optical = null;
            if (!empty($row[6])) {
                $odpName = $row[6];
                $optical = OpticalDist::where('name', $odpName)
                    ->where('group_id', $this->group_id)
                    ->first();

                if (!$optical) {
                    $errors[] = "ODP not found: {$odpName}";
                }
            }

            $nasIdFromExcel = null;
            if (isset($row[7]) && $row[7] !== '') {
                $nasIdFromExcel = (int)$row[7];
                if ($nasIdFromExcel <= 0) {
                    $errors[] = "Invalid NAS ID: {$row[7]}";
                    $nasIdFromExcel = null;
                }
            }

            // Member data
            $memberName = trim((string)($row[8] ?? ''));
            $phoneNumber = isset($row[9]) ? trim((string)$row[9]) : '';
            $email = isset($row[10]) ? trim((string)$row[10]) : '';
            $idCard = isset($row[11]) ? trim((string)$row[11]) : '';
            $address = isset($row[12]) ? trim((string)$row[12]) : '';

            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format: {$email}";
                $email = '';
            }

            if (!empty($phoneNumber) && !preg_match('/^[0-9+\-\s()]+$/', $phoneNumber)) {
                $errors[] = "Invalid phone number format: {$phoneNumber}";
            }

            // Billing
            $billingRaw = $row[13] ?? '';
            $hasBilling = $this->toBool($billingRaw);

            // Active date
            $activeDate = null;
            if (isset($row[14]) && $row[14] !== '') {
                try {
                    if (is_numeric($row[14])) {
                        $activeDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$row[14])
                            ->format('Y-m-d');
                    } else {
                        $activeDate = date('Y-m-d', strtotime($row[14]));
                    }
                } catch (\Exception $e) {
                    $errors[] = "Invalid active date format: {$row[14]}";
                    $activeDate = now()->format('Y-m-d');
                }
            } else {
                $activeDate = now()->format('Y-m-d');
            }

            // Check username uniqueness only when type == pppoe and username not empty
            if ($type === 'pppoe') {
                $exists = Connection::where('username', $username)
                    ->where('group_id', $this->group_id)
                    ->exists();

                if ($exists) {
                    $this->logImportError('Username already exists', 'DUPLICATE_USERNAME', $username, ['warnings' => $errors]);
                    return;
                }
            }

            // Prepare payload for service
            $data = [
                'group_id'    => $this->group_id,
                'type'        => $type === 'pppoe' ? 'pppoe' : 'dhcp',
                'username'    => $username ?: null,
                'password'    => $password ?: null,
                'profile_id'  => $profile->id,
                'isolir'      => false,
                'active_date' => $activeDate,
                'nas_id'      => $nasIdFromExcel,
                'area_id'     => optional($area)->id,
                'optical_id'  => optional($optical)->id,
                'mac_address' => $mac ?: null,
            ];

            if (!empty($memberName)) {
                $data['fullname'] = $memberName;
                $data['phone_number'] = $phoneNumber;
                $data['email'] = $email;
                $data['id_card'] = $idCard;
                $data['address'] = $address;
                $data['billing'] = $hasBilling;
            }

            if ($hasBilling) {
                $rawType = strtolower(trim((string)($row[15] ?? 'pascabayar')));
                $paymentType = in_array($rawType, ['prabayar', 'pascabayar']) ? $rawType : 'pascabayar';

                $rawPeriod = strtolower(trim((string)($row[16] ?? 'renewal')));
                $billingPeriod = in_array($rawPeriod, ['renewal', 'fixed']) ? $rawPeriod : 'renewal';

                $ppn = isset($row[17]) && $row[17] !== '' ? (float)$row[17] : 0;
                $discount = isset($row[18]) && $row[18] !== '' ? (float)$row[18] : 0;
                $amount = $profile->price ?? 0;

                $data = array_merge($data, [
                    'payment_type' => $paymentType,
                    'billing_period' => $billingPeriod,
                    'amount' => $amount,
                    'discount' => $discount,
                    'ppn' => $ppn,
                ]);

                if ($billingPeriod === 'renewal') {
                    try {
                        $dt = new DateTime($activeDate);
                        $data['next_invoice'] = $dt->add(new DateInterval('P1M'))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Failed to calculate next invoice date";
                    }
                }
            }

            // DEBUG: tampilkan data yang akan dikirim ke service
            Log::debug('Import -> calling ConnectionService with data', ['data' => $data, 'batch' => $this->importBatchId]);

            DB::beginTransaction();

            $service = new ConnectionService();
            $result = $service->createOrUpdateMemberConnectionPaymentDetail($data);

            if (empty($result['success'])) {
                DB::rollBack();
                $errorMessage = $result['message'] ?? 'Service failed';
                $this->logImportError($errorMessage, 'SERVICE_ERROR', $username, ['warnings' => $errors, 'service_result' => $result]);
                return;
            }

            DB::commit();

            if (!empty($errors)) {
                $this->logImportSuccess($username, $errors);
            } else {
                Log::info('Import success without warnings', [
                    'username' => $username,
                    'row_number' => $this->rowNumber,
                    'connection_id' => $result['connection']->id ?? null
                ]);
                $this->logImportSuccess($username, []);
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logImportError($e->getMessage(), 'EXCEPTION', $username, [
                'warnings' => $errors,
                'exception_class' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);
            throw $e;
        }
    }

    /**
     * Log import error to database and activity log
     */
    protected function logImportError($message, $errorType, $username = null, $additionalData = [])
    {

        $errorData = [
            'import_batch_id' => $this->importBatchId,
            'row_number' => $this->rowNumber,
            'username' => $username,
            'error_type' => $errorType,
            'error_message' => $message,
            'row_data' => $this->row,
            'group_id' => $this->group_id,
            'additional_data' => $additionalData,
            'created_at' => now()
        ];

        // Save to import error logs table
        try {
            ImportErrorLog::create($errorData);
            // Log to activity log
        } catch (\Exception $e) {
            Log::error('Failed to save import error log', [
                'error' => $e->getMessage(),
                'data' => $errorData
            ]);
        }
        ActivityLogController::logImportF($errorData, 'connections', $this->username, $this->userRole);
        // Log to file
        Log::error('Import row failed', $errorData);
    }

    /**
     * Log successful import with warnings
     */
    protected function logImportSuccess($username, $warnings)
    {

        $data = [
            'import_batch_id' => $this->importBatchId,
            'row_number' => $this->rowNumber,
            'username' => $username,
            'warnings' => $warnings,
            'group_id' => $this->group_id
        ];
        if (!empty($warnings)) {
            ActivityLogController::logImportSuccesswithWarnings($data, 'connections', $this->username, $this->userRole);
            Log::warning('Import succeeded with warnings', $data);
        } else {
            ActivityLogController::logImportSuccess($data, 'connections', $this->username, $this->userRole);
            Log::info('Import succeeded without warnings', $data);
        }
    }

    /**
     * Convert various truthy forms to bool
     */
    protected function toBool($val): bool
    {
        if (is_bool($val)) return $val;
        $s = strtolower(trim((string)$val));
        if ($s === '') return false;
        if (is_numeric($s)) return ((int)$s) === 1;
        return in_array($s, ['ya', 'yes', 'true', 'aktif', 'active', 'on', '1'], true);
    }

    /**
     * Handle job failure
     */
    public function failed(\Throwable $exception)
    {
        $this->logImportError(
            'Job failed after all retries: ' . $exception->getMessage(),
            'JOB_FAILED',
            isset($this->row[0]) ? trim((string)$this->row[0]) : null,
            [
                'attempts' => $this->attempts(),
                'exception' => $exception->getMessage()
            ]
        );
    }
}
