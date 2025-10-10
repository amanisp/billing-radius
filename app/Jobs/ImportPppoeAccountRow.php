<?php

namespace App\Jobs;

use App\Models\Area;
use App\Models\Member;
use App\Models\OpticalDist;
use App\Models\Connection;
use App\Models\Profiles;
use App\Models\ImportErrorLog;
use App\Services\ConnectionService;
use App\Http\Controllers\ActivityLogController;
use DateTime;
use DateInterval;
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
    protected $userRole;

    public $timeout = 120;
    public $tries = 3;

    public function __construct(array $row, $group_id, $rowNumber = null, $importBatchId = null, $username = null, $userRole = null)
    {
        $this->row = $row;
        $this->group_id = $group_id;
        $this->rowNumber = $rowNumber;
        $this->importBatchId = $importBatchId;
        $this->username = $username;
        $this->userRole = $userRole;
    }

    public function handle()
    {
        $errors = [];
        $row = array_map(fn($v) => is_null($v) ? '' : trim((string)$v), $this->row);
        $typeRaw = strtolower(trim($row[0] ?? ''));

        // --- Deteksi tipe koneksi ---
        if (in_array($typeRaw, ['pppoe', 'ppp'])) {
            $type = 'pppoe';
        } elseif (in_array($typeRaw, ['static/dhcp', 'dhcp', 'static'])) {
            $type = 'dhcp';
        } else {
            $this->logImportError("Unknown connection type: {$typeRaw}", 'UNKNOWN_TYPE');
            return;
        }

        // --- Parsing username, password, MAC sesuai tipe ---
        $username = null;
        $password = null;
        $mac = null;

        if ($type === 'pppoe') {
            $username = trim($row[2] ?? '');
            $password = trim($row[3] ?? '');
            $mac = null; // PPPoE tidak butuh MAC
        } elseif ($type === 'dhcp') {
            $mac = trim($row[1] ?? '');
            $username = null;
            $password = null;
        }

        // --- Validasi dasar ---
        if ($type === 'pppoe' && empty($username)) {
            $this->logImportError('Username is required for PPPoE', 'MISSING_USERNAME');
            return;
        }

        if ($type === 'pppoe' && empty($password)) {
            $this->logImportError('Password is required for PPPoE', 'MISSING_PASSWORD', $username);
            return;
        }

        if ($type === 'dhcp' && empty($mac)) {
            $this->logImportError('MAC Address is required for DHCP', 'MISSING_MAC');
            return;
        }

        // --- Profile ---
        $profileName = trim($row[4] ?? '');
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

        // --- Area & ODP ---
        $area = !empty($row[5]) ? Area::where('name', $row[5])->where('group_id', $this->group_id)->first() : null;
        if (!empty($row[5]) && !$area) $errors[] = "Area not found: {$row[5]}";

        $optical = !empty($row[6]) ? OpticalDist::where('name', $row[6])->where('group_id', $this->group_id)->first() : null;
        if (!empty($row[6]) && !$optical) $errors[] = "ODP not found: {$row[6]}";

        // --- NAS ID ---
        $nasIdFromExcel = null;
        if (!empty($row[7]) && is_numeric($row[7])) {
            $nasIdFromExcel = (int)$row[7];
        }

        // --- Member data ---
        $memberName = trim($row[8] ?? '');
        $phoneNumber = trim($row[9] ?? '');
        $email = trim($row[10] ?? '');
        $idCard = trim($row[11] ?? '');
        $address = trim($row[12] ?? '');

        if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors[] = "Invalid email format: {$email}";
            $email = '';
        }

        if (!empty($phoneNumber) && !preg_match('/^[0-9+\-\s()]+$/', $phoneNumber)) {
            $errors[] = "Invalid phone number format: {$phoneNumber}";
        }

        // --- Billing ---
        $hasBilling = $this->toBool($row[13] ?? '');
        $activeDate = $this->parseExcelDate($row[14] ?? null);

        if ($type === 'pppoe') {
            $exists = Connection::where('username', $username)
                ->where('group_id', $this->group_id)
                ->exists();

            if ($exists) {
                $this->logImportError('Username already exists', 'DUPLICATE_USERNAME', $username);
                return;
            }
        }

        $data = [
            'group_id' => $this->group_id,
            'type' => $type,
            'username' => $username,
            'password' => $password,
            'profile_id' => $profile->id,
            'isolir' => false,
            'active_date' => $activeDate,
            'nas_id' => $nasIdFromExcel,
            'area_id' => optional($area)->id,
            'optical_id' => optional($optical)->id,
            'mac_address' => $mac,
        ];

        if (!empty($memberName)) {
            $data = array_merge($data, [
                'fullname' => $memberName,
                'phone_number' => $phoneNumber,
                'email' => $email,
                'id_card' => $idCard,
                'address' => $address,
                'billing' => $hasBilling,
            ]);
        }

        if ($hasBilling) {
            $paymentType = strtolower(trim($row[15] ?? 'pascabayar'));
            $paymentType = in_array($paymentType, ['prabayar', 'pascabayar']) ? $paymentType : 'pascabayar';

            $billingPeriod = strtolower(trim($row[16] ?? 'renewal'));
            $billingPeriod = in_array($billingPeriod, ['fixed', 'renewal']) ? $billingPeriod : 'renewal';

            $ppn = (float)($row[17] ?? 0);
            $discount = (float)($row[18] ?? 0);
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

        Log::debug('📦 [Import] Ready to send data to ConnectionService', [
            'data' => $data,
            'batch' => $this->importBatchId,
            'row_number' => $this->rowNumber,
        ]);

        try {
            DB::beginTransaction();
            $service = new ConnectionService();
            $result = $service->createOrUpdateMemberConnectionPaymentDetail($data);

            if (empty($result['success'])) {
                DB::rollBack();
                $this->logImportError($result['message'] ?? 'Service failed', 'SERVICE_ERROR', $username, ['service' => $result]);
                return;
            }

            DB::commit();
            $this->logImportSuccess($username, $errors);
        } catch (\Throwable $e) {
            DB::rollBack();
            $this->logImportError($e->getMessage(), 'EXCEPTION', $username, [
                'trace' => $e->getTraceAsString(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
            ]);
            throw $e;
        }
    }

    protected function parseExcelDate($value)
    {
        if (empty($value)) return now()->format('Y-m-d');
        try {
            if (is_numeric($value)) {
                return \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$value)
                    ->format('Y-m-d');
            }
            return date('Y-m-d', strtotime($value));
        } catch (\Exception $e) {
            return now()->format('Y-m-d');
        }
    }

    protected function toBool($val): bool
    {
        $s = strtolower(trim((string)$val));
        if ($s === '') return false;
        if (is_numeric($s)) return ((int)$s) === 1;
        return in_array($s, ['ya', 'yes', 'true', 'aktif', 'active', 'on'], true);
    }

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

        try {
            ImportErrorLog::create($errorData);
        } catch (\Exception $e) {
            Log::error('Failed to save import error log', ['error' => $e->getMessage(), 'data' => $errorData]);
        }

        ActivityLogController::logImportF($errorData, 'connections', $this->username, $this->userRole);
        Log::error('❌ Import row failed', $errorData);
    }

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
            Log::warning('⚠️ Import succeeded with warnings', $data);
        } else {
            ActivityLogController::logImportSuccess($data, 'connections', $this->username, $this->userRole);
            Log::info('✅ Import succeeded without warnings', $data);
        }
    }

    public function failed(\Throwable $exception)
    {
        $this->logImportError(
            'Job failed after all retries: ' . $exception->getMessage(),
            'JOB_FAILED',
            $this->row[0] ?? null,
            ['attempts' => $this->attempts(), 'exception' => $exception->getMessage()]
        );
    }
}
