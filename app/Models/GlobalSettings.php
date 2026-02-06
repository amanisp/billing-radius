<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;


class GlobalSettings extends Model
{
    protected $fillable = [
        'isolir_mode',
        'xendit_balance',
        'isolir_time',
        'invoice_generate_days',
        'notification_days',
        'isolir_after_exp',
        'due_date_pascabayar',
        'footer',
        'group_id',
        'whatsapp_api_key', // account token for WhatsApp API
    ];

    /**
     * Relationship to Groups
     */
    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    /**
     * Get WhatsApp API key for specific group
     */
    public static function getWhatsAppApiKey($groupId)
    {
        $settings = static::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }

    /**
     * Check if WhatsApp is configured
     */
    public function isWhatsAppConfigured()
    {
        return !empty($this->whatsapp_api_key);
    }

    /**
     * Get WhatsApp status from cache (diupdate via webhook)
     */
    public static function getWhatsAppStatus($groupId)
    {
        return Cache::get("whatsapp_status_{$groupId}", [
            'status' => 'offline',
            'phone_number' => null,
            'device_info' => null,
            'last_seen' => null,
            'message_count' => 0
        ]);
    }

    /**
     * Get default WhatsApp templates
     */
    public static function getDefaultTemplates()
    {
        return [
            'invoice' => "Dear [username],\n\nYour invoice #[no_invoice] for amount [amount] has been generated.\nService: [service_name]\nBandwidth: [bandwidth]\nDue date: [due_date]\n\nPlease complete the payment before the due date to avoid service interruption.\n\nThank you for using our service.\n\nBest regards,\n[company_name]",

            'payment_reminder' => "Dear [username],\n\nThis is a reminder for your pending payment.\nInvoice: #[no_invoice]\nAmount: [amount]\nDue date: [due_date]\n\nPlease make the payment immediately to continue enjoying our service.\n\nContact us if you have any questions.\n\nThank you.\n[company_name]",

            'welcome' => "Welcome [username]!\n\nThank you for choosing our ISP service.\nYour service [service_name] with bandwidth [bandwidth] is now active.\n\nIf you have any questions, please contact our customer service.\n\nBest regards,\n[company_name] Team",

            'suspension' => "Dear [username],\n\nYour service has been temporarily suspended due to unpaid invoice #[no_invoice] with amount [amount].\n\nPlease make the payment immediately to reactivate your service.\n\nContact our customer service for assistance.\n\nThank you.\n[company_name]",

            'reactivation' => "Dear [username],\n\nGreat news! Your service has been reactivated.\nService: [service_name]\nBandwidth: [bandwidth]\n\nThank you for your payment. You can now enjoy our service again.\n\nBest regards,\n[company_name] Team",

            'overdue_final' => "FINAL NOTICE\n\nDear [username],\n\nYour invoice #[no_invoice] with amount [amount] is severely overdue (Due: [due_date]).\n\nThis is your FINAL NOTICE. Your service will be permanently terminated if payment is not received within 3 days.\n\nPay immediately to avoid service termination.\n\nContact: [support_phone]\n[company_name]"
        ];
    }

    /**
     * Get template content from cache or default
     */
    public static function getWhatsAppTemplate($groupId, $type)
    {
        $templates = Cache::get("whatsapp_templates_{$groupId}");

        if (!$templates) {
            $templates = static::getDefaultTemplates();
            Cache::put("whatsapp_templates_{$groupId}", $templates, 3600); // Cache 1 hour
        }

        return $templates[$type] ?? null;
    }

    /**
     * Update template in cache
     */
    public static function updateWhatsAppTemplate($groupId, $type, $content)
    {
        $templates = Cache::get("whatsapp_templates_{$groupId}", static::getDefaultTemplates());
        $templates[$type] = $content;

        Cache::put("whatsapp_templates_{$groupId}", $templates, 3600);
        return true;
    }

    /**
     * Get message logs from cache (diupdate via webhook)
     */
    public static function getWhatsAppMessageLogs($groupId, $limit = 50)
    {
        return Cache::get("whatsapp_messages_{$groupId}", []);
    }
}
