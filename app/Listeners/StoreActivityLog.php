<?php
namespace App\Listeners;

use App\Models\ActivityLog;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Facades\Log;
use App\Events\LogCreated;
use App\Events\ActivityLogged;

class StoreActivityLog
{
    public function handle(ActivityLogged $event): void
    {
        Log::info('StoreActivityLog::handle called', [
            'operation' => $event->operation,
            'table_name' => $event->table_name,
            'details_type' => gettype($event->details)
        ]);

        try {
            $user = Auth::user();
            $tableName = $event->table_name;
            $details = $event->details;

            // Handle when details is an Eloquent model
            if (is_object($details) && method_exists($details, 'getTable')) {
                $tableName = $tableName ?? $details->getTable();

                // Handle different operations for model objects
                if (strtoupper($event->operation) === 'UPDATE') {
                    // For updates: get original vs current attributes
                    $originalData = $details->getOriginal();
                    $currentData = $details->getAttributes();

                    Log::info('Model update detected', [
                        'model' => get_class($details),
                        'table' => $tableName,
                        'original_keys' => array_keys($originalData),
                        'current_keys' => array_keys($currentData)
                    ]);

                    // Calculate what actually changed (excluding timestamps and metadata)
                    $details = $this->calculateDiff($originalData, $currentData, $details);

                } else {
                    // For creates/deletes: just convert model to array
                    $details = $details->toArray();
                }
            }
            // Handle when details is already an array (new format - pre-calculated changes)
            elseif (strtoupper($event->operation) === 'UPDATE' && is_array($details)) {
                // If it's already in the correct format, use it as is
                if (!isset($details['old']) || !isset($details['new'])) {
                    // It's already the changes array from controller
                    // No need to process further
                } else {
                    // Old format compatibility
                    $details = $this->calculateDiff($details['old'], $details['new']);
                }
            }

            Log::info('About to create ActivityLog', [
                'operation' => strtoupper($event->operation),
                'table_name' => $tableName,
                'changes_count' => is_array($details) ? count($details) : 'not_array'
            ]);

            // Save log
            $log = ActivityLog::create([
                'time' => now(),
                'operation' => strtoupper($event->operation),
                'table_name' => $tableName,
                'username' => $event->username ?? ($user?->name ?? 'System'),
                'role' => $event->role ?? ($user?->role ?? 'N/A'),
                'ip_address' => Request::ip(),
                'session_id' => session()->getId(),
                'details' => json_encode($details, JSON_UNESCAPED_UNICODE),
            ]);

            Log::info('✅ ActivityLog created successfully', [
                'log_id' => $log->id,
                'operation' => $log->operation,
                'table_name' => $log->table_name
            ]);

            // Broadcast log
            broadcast(new LogCreated($log))->toOthers();

        } catch (\Exception $e) {
            Log::error('❌ Error in StoreActivityLog::handle', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'event_operation' => $event->operation ?? 'unknown'
            ]);
        }
    }

    private function calculateDiff(array $oldData, array $newData, $model = null): array
    {
        $changes = [];

        // Define fields to exclude from change tracking
        $excludedFields = [
            'created_at',
            'updated_at',
            'deleted_at', // if using soft deletes
            'remember_token', // for user models
        ];

        // If we have the model, we can also exclude its timestamp fields dynamically
        if ($model && method_exists($model, 'getTimestampFields')) {
            $excludedFields = array_merge($excludedFields, $model->getTimestampFields());
        } elseif ($model) {
            // Add Laravel's default timestamp fields
            if (isset($model->timestamps) && $model->timestamps !== false) {
                $excludedFields[] = $model->getCreatedAtColumn();
                $excludedFields[] = $model->getUpdatedAtColumn();
            }
        }

        foreach ($newData as $key => $newValue) {
            // Skip excluded fields
            if (in_array($key, $excludedFields)) {
                continue;
            }

            $oldValue = $oldData[$key] ?? null;
            if ($oldValue !== $newValue) {
                $changes[$key] = [
                    'old' => $oldValue,
                    'new' => $newValue,
                ];
            }
        }

        return $changes;
    }
}
