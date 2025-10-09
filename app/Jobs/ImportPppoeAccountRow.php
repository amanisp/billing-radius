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

    public function __construct(array $row, $group_id, $rowNumber = null, $importBatchId = null, $username = null, $mac = null, $userRole = null)
    {
        $this->row = $row;
        $this->group_id = $group_id;
        $this->rowNumber = $rowNumber;
        $this->importBatchId = $importBatchId;
        $this->username = $username;
        $this->mac = $mac;
        $this->userRole = $userRole;
    }

    public function handle()
    {
        $errors = [];
        $username = null;
        $mac = null;

        try {
            // Validasi username (wajib ada)
            if (isset($this->row[0]) || trim((string)$this->row[0]) === 'PPPoE') {
                if (!isset($this->row[2]) || trim((string)$this->row[2]) === '') {

                    $this->logImportError('Username is required', 'MISSING_USERNAME');
                    return;
                }
            } else {
                if (!isset($this->row[1]) || trim((string)$this->row[1]) === '') {

                    $this->logImportError('Mac address is required', 'MISSING_MAC_ADDRESS');
                    return;
                }
            }

            $username = trim((string)$this->row[2]);
            $mac = trim((string)$this->row[1]);
            $password = trim((string)($this->row[3] ?? ''));

            // Validasi password
            if (empty($password)) {
                $errors[] = 'Password is empty';
            }

            // Profile validation
            $profileName = trim((string)($this->row[4] ?? ''));
            if (empty($profileName)) {
                $this->logImportError('Profile name is required', 'MISSING_PROFILE', $username);
                return;
            }

            $profile = Profiles::where('name', $profileName)
                ->where('group_id', $this->group_id)
                ->first();

            if (!$profile) {
                $this->logImportError(
                    "Profile not found: {$profileName}",
                    'PROFILE_NOT_FOUND',
                    $username
                );
                return;
            }

            // Area validation (optional but log if not found)
            $area = null;
            if (!empty($this->row[5])) {
                $areaName = trim((string)$this->row[3]);
                $area = Area::where('name', $areaName)
                    ->where('group_id', $this->group_id)
                    ->first();

                if (!$area) {
                    $errors[] = "Area not found: {$areaName}";
                }
            }

            // ODP validation (optional but log if not found)
            $optical = null;
            if (!empty($this->row[6])) {
                $odpName = trim((string)$this->row[4]);
                $optical = OpticalDist::where('name', $odpName)
                    ->where('group_id', $this->group_id)
                    ->first();

                if (!$optical) {
                    $errors[] = "ODP not found: {$odpName}";
                }
            }

            // NAS ID validation
            $nasIdFromExcel = null;
            if (isset($this->row[7]) && !empty(trim((string)$this->row[5]))) {
                $nasIdFromExcel = (int)$this->row[5];
                if ($nasIdFromExcel <= 0) {
                    $errors[] = "Invalid NAS ID: {$this->row[5]}";
                    $nasIdFromExcel = null;
                }
            }

            // Member data validation
            $memberName = trim((string)($this->row[8] ?? ''));
            $phoneNumber = isset($this->row[9]) ? trim((string)$this->row[9]) : '';
            $email = isset($this->row[10]) ? trim((string)$this->row[10]) : '';
            $idCard = isset($this->row[11]) ? trim((string)$this->row[11]) : '';
            $address = isset($this->row[12]) ? trim((string)$this->row[12]) : '';

            // Email validation if provided
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors[] = "Invalid email format: {$email}";
                $email = ''; // Reset invalid email
            }

            // Phone number validation if provided
            if (!empty($phoneNumber) && !preg_match('/^[0-9+\-\s()]+$/', $phoneNumber)) {
                $errors[] = "Invalid phone number format: {$phoneNumber}";
            }

            // Billing handling
            $billingRaw = $this->row[13] ?? '';
            $hasBilling = $this->toBool($billingRaw);

            // Active date validation
            $activeDate = null;
            if (isset($this->row[14])) {
                try {
                    if (is_numeric($this->row[14])) {
                        $activeDate = \PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float)$this->row[14])
                            ->format('Y-m-d');
                    } else {
                        $activeDate = date('Y-m-d', strtotime($this->row[14]));
                    }
                } catch (\Exception $e) {
                    $errors[] = "Invalid active date format: {$this->row[14]}";
                    $activeDate = now()->format('Y-m-d');
                }
            } else {
                $activeDate = now()->format('Y-m-d');
            }

            // Check username uniqueness
            $exists = Connection::where('username', $username)
                ->where('group_id', $this->group_id)
                ->exists();

            if ($exists) {
                $this->logImportError(
                    'Username already exists',
                    'DUPLICATE_USERNAME',
                    $username,
                    ['warnings' => $errors]
                );
                return;
            }

            // Base data
            $data = [
                'group_id'        => $this->group_id,
                'type'            => 'pppoe',
                'username'        => $username,
                'password'        => $password,
                'profile_id'      => $profile->id,
                'isolir'          => false,
                'active_date'     => $activeDate,
                'nas_id'          => $nasIdFromExcel,
                'area_id'         => optional($area)->id,
                'optical_id'      => optional($optical)->id,
                'mac_address'     => $mac,
                'type'            => $this->row[0] == 'PPPoE' ? 'PPPoE' : 'DHCP'
            ];

            // Add member data if available
            if (!empty($memberName)) {
                $data['fullname'] = $memberName;
                $data['phone_number'] = $phoneNumber;
                $data['email'] = $email;
                $data['id_card'] = $idCard;
                $data['address'] = $address;
                $data['billing'] = $hasBilling;
            }

            // Billing data processing
            if ($hasBilling) {
                // Payment type validation
                $rawType = strtolower(trim((string)($this->row[15] ?? 'pascabayar')));
                $paymentType = match ($rawType) {
                    'prabayar' => 'prabayar',
                    'pascabayar' => 'pascabayar',
                    default => 'pascabayar'
                };

                if (!in_array($rawType, ['prabayar', 'pascabayar', ''])) {
                    $errors[] = "Invalid payment type: {$rawType}, using default: pascabayar";
                }

                // Billing period validation
                $rawPeriod = strtolower(trim((string)($this->row[16] ?? 'renewal')));
                $billingPeriod = in_array($rawPeriod, ['renewal', 'fixed'], true) ? $rawPeriod : 'renewal';

                if (!in_array($rawPeriod, ['renewal', 'fixed', ''])) {
                    $errors[] = "Invalid billing period: {$rawPeriod}, using default: renewal";
                }

                // Financial data validation
                $ppn = 0;
                if (isset($this->row[17])) {
                    $ppn = (float)$this->row[17];
                    if ($ppn < 0 || $ppn > 100) {
                        $errors[] = "Invalid PPN value: {$ppn}, should be between 0-100";
                        $ppn = 0;
                    }
                }

                $discount = 0;
                if (isset($this->row[18])) {
                    $discount = (float)$this->row[18];
                    if ($discount < 0) {
                        $errors[] = "Invalid discount value: {$discount}, cannot be negative";
                        $discount = 0;
                    }
                }

                $data = array_merge($data, [
                    'payment_type'    => $paymentType,
                    'billing_period'  => $billingPeriod,
                    'amount'          => $profile->price,
                    'discount'        => $discount,
                    'ppn'             => $ppn,
                ]);

                // Calculate next invoice for renewal
                if ($billingPeriod === 'renewal') {
                    try {
                        $dt = new DateTime($activeDate);
                        $data['next_invoice'] = $dt->add(new DateInterval('P1M'))->format('Y-m-d');
                    } catch (\Exception $e) {
                        $errors[] = "Failed to calculate next invoice date";
                    }
                }
            } else {
                // Set billing to false if no billing
                if (!empty($memberName)) {
                    $data['billing'] = false;
                }
            }

            // Execute service
            DB::beginTransaction();

            $service = new ConnectionService();
            $result = $service->createOrUpdateMemberConnectionPaymentDetail($data);

            if (empty($result['success'])) {
                DB::rollBack();

                $errorMessage = $result['message'] ?? 'Service failed';
                $this->logImportError(
                    $errorMessage,
                    'SERVICE_ERROR',
                    $username,
                    [
                        'warnings' => $errors,
                        'service_result' => $result
                    ]
                );
                return;
            }

            DB::commit();

            // Log success with warnings if any
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

            $this->logImportError(
                $e->getMessage(),
                'EXCEPTION',
                $username,
                [
                    'warnings' => $errors,
                    'exception_class' => get_class($e),
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'trace' => $e->getTraceAsString()
                ]
            );

            // Re-throw for retry mechanism
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
