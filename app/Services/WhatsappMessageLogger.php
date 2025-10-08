<?php

namespace App\Services;

use App\Models\WhatsappMessageLog;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class WhatsappMessageLogger
{
    /**
     * Log message before sending (creates pending log entry).
     */
    public function logMessageStart(array $params)
    {
        $sessionId = $this->generateSessionId();

        Log::info('WhatsApp message log started', [
            'session_id'   => $sessionId,
            'phone'        => $params['phone'],
            'subject'      => $params['subject'] ?? null,
            'message'      => Str::limit($params['message'], 100),
            'message_type' => $params['message_type'] ?? 'individual',
            'group_id'     => $params['group_id'] ?? null,
            'scheduled_at' => $params['scheduled_at'] ?? null,
            'metadata'     => $params['metadata'] ?? null,
        ]);

        try {
            $messageLog = WhatsappMessageLog::create([
                'group_id'     => $params['group_id'] ?? null,
                'phone'        => $params['phone'],
                'subject'      => $params['subject'] ?? null,
                'message'      => $params['message'],
                'message_type' => $params['message_type'] ?? 'individual',
                'session_id'   => $sessionId,
                'status'       => 'pending',
                'scheduled_at' => $params['scheduled_at'] ?? null,
                'metadata'     => is_array($params['metadata'] ?? null)
                    ? json_encode($params['metadata'])
                    : $params['metadata'],
                'request_data' => json_encode([
                    'original_phone' => $params['original_phone'] ?? $params['phone'],
                    'api_key_prefix' => isset($params['api_key'])
                        ? substr($params['api_key'], 0, 8) . '...'
                        : null,
                    'created_at'     => now()->toISOString(),
                ]),
            ]);

            return [
                'success'     => true,
                'message_log' => $messageLog,
                'session_id'  => $sessionId,
            ];
        } catch (\Exception $e) {
            Log::error('Failed to create message log', [
                'error'  => $e->getMessage(),
                'params' => $params,
            ]);

            return [
                'success' => false,
                'error'   => $e->getMessage(),
            ];
        }
    }

    /**
     * Update log after sending attempt (success or failure).
     */
    public function logMessageResult($messageLogId, $sendResult, $apiResponse = null)
    {
        try {
            $messageLog = WhatsappMessageLog::find($messageLogId);

            if (!$messageLog) {
                Log::warning('Message log not found for update', ['id' => $messageLogId]);
                return false;
            }

            if ($sendResult['success']) {
                $this->updateMessageLogSuccess($messageLog, $sendResult, $apiResponse);
            } else {
                $this->updateMessageLogFailure($messageLog, $sendResult, $apiResponse);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update message log', [
                'message_log_id' => $messageLogId,
                'error'          => $e->getMessage(),
            ]);

            return false;
        }
    }

    /**
     * Complete logging process in one call (for backward compatibility).
     */
    public function logMessage(array $params, array $sendResult, $apiResponse = null)
    {
        $startResult = $this->logMessageStart($params);

        if (!$startResult['success']) {
            return $startResult;
        }

        $this->logMessageResult($startResult['message_log']->id, $sendResult, $apiResponse);

        return [
            'success'        => true,
            'message_log_id' => $startResult['message_log']->id,
            'session_id'     => $startResult['session_id'],
        ];
    }

    /**
     * Send bulk messages with logging.
     */
    public function sendBulkMessages(array $params, $whatsAppService)
    {
        $apiKey      = $params['api_key'] ?? null;
        $recipients  = $params['recipients'] ?? [];
        $message     = $params['message'] ?? null;
        $subject     = $params['subject'] ?? null;
        $groupId     = $params['group_id'] ?? null;
        $messageType = $params['message_type'] ?? 'broadcast';
        $delayBetween = $params['delay_between'] ?? 2;
        $batchSize   = $params['batch_size'] ?? 10;

        if (!$apiKey || empty($recipients) || !$message) {
            return [
                'success' => false,
                'error'   => 'Missing required parameters for bulk messaging',
            ];
        }

        $sessionId = $this->generateSessionId();
        $results = [
            'session_id' => $sessionId,
            'total'      => count($recipients),
            'sent'       => 0,
            'failed'     => 0,
            'details'    => [],
        ];

        $batches = array_chunk($recipients, $batchSize);

        foreach ($batches as $batchIndex => $batch) {
            Log::info("Processing batch " . ($batchIndex + 1) . " of " . count($batches));

            foreach ($batch as $recipient) {
                $phone = is_array($recipient) ? $recipient['phone'] : $recipient;
                $recipientName = is_array($recipient) ? ($recipient['name'] ?? null) : null;

                $personalizedMessage = $recipientName
                    ? str_replace(['[NAME]', '{name}'], $recipientName, $message)
                    : $message;

                $result = $whatsAppService->sendTextMessage($apiKey, $phone, $personalizedMessage, $subject);

                if ($result['success']) {
                    $results['sent']++;
                } else {
                    $results['failed']++;
                }

                $results['details'][] = [
                    'phone'   => $phone,
                    'name'    => $recipientName,
                    'success' => $result['success'],
                    'error'   => $result['error'] ?? null,
                ];

                if ($delayBetween > 0) {
                    sleep($delayBetween);
                }
            }

            if ($batchIndex < count($batches) - 1) {
                sleep($delayBetween * 3);
            }
        }

        return [
            'success' => $results['sent'] > 0,
            'results' => $results,
        ];
    }

    /**
     * Update message log on successful send.
     */
    private function updateMessageLogSuccess(WhatsappMessageLog $messageLog, array $sendResult, $apiResponse = null)
    {
        $updateData = [
            'status'              => 'sent',
            'whatsapp_message_id' => $sendResult['message_id'] ?? null,
            'sent_at'             => now(),
            'delivery_attempts'   => ($messageLog->delivery_attempts ?? 0) + 1,
        ];

        if ($apiResponse || isset($sendResult['data'])) {
            $updateData['response_data'] = json_encode($apiResponse ?? $sendResult['data']);
        }

        $messageLog->update($updateData);

        Log::info('WhatsApp message sent successfully', [
            'message_log_id'      => $messageLog->id,
            'session_id'          => $messageLog->session_id,
            'phone'               => $messageLog->phone,
            'whatsapp_message_id' => $sendResult['message_id'] ?? null,
        ]);
    }

    /**
     * Update message log on failed send.
     */
    private function updateMessageLogFailure(WhatsappMessageLog $messageLog, array $sendResult, $apiResponse = null)
    {
        $updateData = [
            'status'            => 'failed',
            'error_message'     => $sendResult['error'] ?? 'Unknown error',
            'sent_at'           => now(),
            'delivery_attempts' => ($messageLog->delivery_attempts ?? 0) + 1,
        ];

        if ($apiResponse || isset($sendResult['data'])) {
            $updateData['response_data'] = json_encode($apiResponse ?? $sendResult['data']);
        }

        $messageLog->update($updateData);

        Log::warning('WhatsApp message failed to send', [
            'message_log_id' => $messageLog->id,
            'session_id'     => $messageLog->session_id,
            'phone'          => $messageLog->phone,
            'error'          => $sendResult['error'] ?? 'Unknown error',
        ]);
    }

    /**
     * Get message logs with filters.
     */
    public function getMessageLogs(array $filters = [])
    {
        $query = WhatsappMessageLog::query();

        if (isset($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
        if (isset($filters['status'])) {
            $query->where('status', $filters['status']);
        }
        if (isset($filters['message_type'])) {
            $query->where('message_type', $filters['message_type']);
        }
        if (isset($filters['phone'])) {
            $query->where('phone', 'LIKE', '%' . $filters['phone'] . '%');
        }
        if (isset($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $query->orderBy('created_at', 'desc');

        $perPage = $filters['per_page'] ?? 50;

        return $query->with(['group'])->paginate($perPage);
    }

    /**
     * Get message statistics.
     */
    public function getMessageStats(array $filters = [])
    {
        $query = WhatsappMessageLog::query();

        if (isset($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
        if (isset($filters['date_from'])) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if (isset($filters['date_to'])) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }

        $total  = $query->count();
        $sent   = (clone $query)->where('status', 'sent')->count();
        $failed = (clone $query)->where('status', 'failed')->count();
        $pending = (clone $query)->where('status', 'pending')->count();

        return [
            'total'        => $total,
            'sent'         => $sent,
            'failed'       => $failed,
            'pending'      => $pending,
            'success_rate' => $total > 0 ? round(($sent / $total) * 100, 2) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 2) : 0,
        ];
    }

    /**
     * Retry failed messages.
     */
    public function retryFailedMessages($whatsAppService, $apiKey, array $filters = [])
    {
        $query = WhatsappMessageLog::where('status', 'failed');

        if (isset($filters['group_id'])) {
            $query->where('group_id', $filters['group_id']);
        }
        if (isset($filters['session_id'])) {
            $query->where('session_id', $filters['session_id']);
        }
        if (isset($filters['max_attempts'])) {
            $query->where('delivery_attempts', '<', $filters['max_attempts']);
        }

        $failedMessages = $query->get();

        $retryResults = [
            'total_retried'      => 0,
            'successful_retries' => 0,
            'failed_retries'     => 0,
            'details'            => [],
        ];

        foreach ($failedMessages as $messageLog) {
            $sendResult = $whatsAppService->sendTextMessage(
                $apiKey,
                $messageLog->phone,
                $messageLog->message,
                $messageLog->subject
            );

            $retryResults['total_retried']++;

            if ($sendResult['success']) {
                $retryResults['successful_retries']++;
            } else {
                $retryResults['failed_retries']++;
            }

            $retryResults['details'][] = [
                'original_message_log_id' => $messageLog->id,
                'phone'                   => $messageLog->phone,
                'success'                 => $sendResult['success'],
                'error'                   => $sendResult['error'] ?? null,
            ];
        }

        return $retryResults;
    }

    /**
     * Generate unique session ID for tracking.
     */
    private function generateSessionId()
    {
        return 'wa_' . date('YmdHis') . '_' . Str::random(8);
    }

    /**
     * Clean old message logs (for maintenance).
     */
    public function cleanOldLogs($daysToKeep = 90)
    {
        $cutoffDate = Carbon::now()->subDays($daysToKeep);

        $deletedCount = WhatsappMessageLog::where('created_at', '<', $cutoffDate)->delete();

        Log::info('Cleaned old WhatsApp message logs', [
            'deleted_count' => $deletedCount,
            'cutoff_date'   => $cutoffDate->toDateString(),
        ]);

        return $deletedCount;
    }

    /**
     * Export message logs to CSV.
     */
    public function exportToCsv(array $filters = [])
    {
        $logs = $this->getMessageLogs(array_merge($filters, ['per_page' => 10000]));

        $csvData = [];
        $csvData[] = [
            'ID', 'Group', 'Phone', 'Subject', 'Message', 'Type', 'Status',
            'Session ID', 'Sent At', 'Created At', 'Attempts',
        ];

        foreach ($logs as $log) {
            $csvData[] = [
                $log->id,
                $log->group->name ?? 'N/A',
                $log->formatted_phone,
                $log->subject ?? '',
                Str::limit($log->message, 100),
                ucfirst($log->message_type),
                ucfirst($log->status),
                $log->session_id,
                $log->sent_at ? $log->sent_at->format('Y-m-d H:i:s') : '',
                $log->created_at->format('Y-m-d H:i:s'),
                $log->delivery_attempts ?? 0,
            ];
        }

        return $csvData;
    }
}
