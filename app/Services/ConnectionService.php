<?php

namespace App\Services;

use App\Models\Member;
use App\Models\Connection;
use App\Models\PaymentDetail;
use App\Http\Controllers\ActivityLogController;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class ConnectionService
{
    /**
     * Create or update member and connection (PPPoE account).
     *
     * @param array $data
     * @return array
     * @throws \Exception
     */
    public function createOrUpdateMemberConnectionPaymentDetail(array $data)
    {
        return DB::transaction(function () use ($data) {
            try {
                // FIX: Use group_id from data (for job context) or Auth (for web context)
                $groupId = $data['group_id'] ?? Auth::user()->group_id ?? null;

                if (!$groupId) {
                    throw new \Exception('Group ID is required');
                }

                Log::info('Creating connection', [
                    'username' => $data['username'] ?? $data['mac_address'],
                    'group_id' => $groupId,
                    'type' => $data['type']
                ]);

                // FIX: Add missing billing_active field
                $connection = Connection::create([
                    'username' => $data['username'] ?? null,
                    'type' => $data['type'],
                    'password' => $data['password'] ?? null,
                    'mac_address' => $data['mac_address'] ?? null,
                    'profile_id' => $data['profile_id'],
                    'group_id' => $groupId,
                    'internet_number' => Connection::generateNomorLayanan($groupId),
                    'billing_active' => $data['billing_active'] ?? false, // FIX: Add this field
                    'isolir' => $data['isolir'] ?? false,
                    'nas_id' => $data['nas_id'] ?? null,
                    'area_id' => $data['area_id'] ?? null,
                    'optical_id' => $data['optical_id'] ?? null,
                    'active_date' => $data['active_date'] ?? now(),
                ]);

                Log::info('Connection created', ['connection_id' => $connection->id]);

                // Create radius records only if username exists (PPPoE type)
                if ($connection->username && $connection->type === 'pppoe') {
                    $this->createRadiusRecords($connection, $groupId);
                    Log::info('Radius records created', ['username' => $connection->username]);
                }

                // FIX: Better member logic
                $member = null;

                // If member_id provided, try to find existing
                if (!empty($data['member_id'])) {
                    $member = Member::where('id', $data['member_id'])
                        ->where('group_id', $groupId)
                        ->first();
                }

                // If no existing member found, try to find by fullname
                if (!$member && !empty($data['fullname'])) {
                    $member = Member::where('fullname', $data['fullname'])
                        ->where('group_id', $groupId)
                        ->first();
                }

                // Create or update member
                if ($member) {
                    // Update existing member
                    $member->update([
                        'fullname' => $data['fullname'] ?? $member->fullname,
                        'phone_number' => $data['phone_number'] ?? $member->phone_number,
                        'email' => $data['email'] ?? $member->email,
                        'id_card' => $data['id_card'] ?? $member->id_card,
                        'address' => $data['address'] ?? $member->address,
                        'connection_id' => $connection->id,
                        'billing' => $data['billing'] ?? $member->billing,
                    ]);
                    Log::info('Member updated', ['member_id' => $member->id]);
                } else {
                    // Create new member
                    $member = Member::create([
                        'fullname' => $data['fullname'] ?? 'Unknown Member',
                        'phone_number' => $data['phone_number'] ?? null,
                        'email' => $data['email'] ?? null,
                        'id_card' => $data['id_card'] ?? null,
                        'address' => $data['address'] ?? null,
                        'group_id' => $groupId,
                        'connection_id' => $connection->id,
                        'billing' => $data['billing'] ?? false,
                    ]);
                    Log::info('Member created', ['member_id' => $member->id]);
                }

                // Create payment detail if billing is active
                $paymentDetail = null;
                if ($data['billing'] ?? false) {
                    $paymentDetail = PaymentDetail::create([
                        'group_id' => $groupId,
                        'payment_type' => $data['payment_type'] ?? 'pascabayar',
                        'billing_period' => $data['billing_period'] ?? 'renewal',
                        'amount' => $data['amount'] ?? 0,
                        'discount' => $data['discount'] ?? 0,
                        'ppn' => $data['ppn'] ?? 0,
                        'active_date' => $data['active_date'] ?? now(),
                        'next_invoice' => $data['next_invoice'] ?? null,
                    ]);

                    // Update member with payment_detail_id
                    $member->update(['payment_detail_id' => $paymentDetail->id]);

                    Log::info('Payment detail created', ['payment_detail_id' => $paymentDetail->id]);
                }

                // Log the activity
                ActivityLogController::logCreate($connection, 'connections');

                return [
                    'success' => true,
                    'message' => 'Data berhasil disimpan!',
                    'connection' => $connection,
                    'member' => $member,
                    'paymentDetail' => $paymentDetail,
                ];
            } catch (\Exception $e) {
                Log::error('ConnectionService error', [
                    'message' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                    'data' => $data
                ]);

                ActivityLogController::logCreateF(['ConnectionService Error: ' . $e->getMessage()]);

                return [
                    'success' => false,
                    'message' => 'Error: ' . $e->getMessage(),
                    'debug_data' => $data
                ];
            }
        });
    }

    /**
     * Create radius records for PPPoE connection
     *
     * @param Connection $connection
     * @param int $groupId
     * @return void
     */
    private function createRadiusRecords(Connection $connection, int $groupId)
    {
        try {
            $username = $connection->username;
            $password = $connection->password ?? '';

            // Check if radius connection exists
            if (!DB::connection('radius')) {
                Log::warning('Radius connection not configured');
                return;
            }

            // Create record in radius.radcheck table
            DB::connection('radius')->table('radcheck')->insert([
                'username' => $username,
                'attribute' => 'Cleartext-Password',
                'op' => ':=',
                'value' => $password,
                'group_id' => $groupId
            ]);

            // Get profile details and create radius.radreply record
            $profile = DB::table('profiles')->find($connection->profile_id);
            if ($profile) {
                $rateLimit = implode('/', [
                    $profile->rate_rx ?? '0',
                    $profile->rate_tx ?? '0',
                    $profile->burst_rx ?? '0',
                    $profile->burst_tx ?? '0',
                    $profile->threshold_rx ?? '0',
                    $profile->threshold_tx ?? '0',
                    $profile->time_rx ?? '0',
                    $profile->time_tx ?? '0',
                    $profile->priority ?? '8'
                ]);


                DB::connection('radius')->table('radusergroup')->insert([
                    'username' => $username,
                    'groupname' => $profile->name . '-' . $groupId,
                    'priority' => 1,
                    'group_id' => $groupId
                ]);
            }

            // Create record in radius.radusergroup table
            DB::connection('radius')->table('radusergroup')->insert([
                'username' => $username,
                'groupname' => 'mitra_' . $groupId,
                'priority' => 1,
                'group_id' => $groupId
            ]);

            Log::info('Radius records created successfully', ['username' => $username]);
        } catch (\Exception $e) {
            Log::error('Failed to create radius records', [
                'username' => $connection->username ?? 'unknown',
                'error' => $e->getMessage()
            ]);
            // Don't throw exception, just log - radius failure shouldn't stop connection creation
        }
    }
}
