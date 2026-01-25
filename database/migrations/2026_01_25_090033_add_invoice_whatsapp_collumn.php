<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('invoice_homepasses', function (Blueprint $table) {
            // Add WhatsApp tracking columns
            $table->enum('wa_status', [
                'not_sent',
                'pending',
                'sent',
                'failed',
                'failed_permanently'  
            ])->default('not_sent')->after('paid_at');

            // Waktu pengiriman WA
            $table->timestamp('wa_sent_at')
                ->nullable()
                ->after('wa_status');

            // Error message jika gagal
            $table->text('wa_error_message')
                ->nullable()
                ->after('wa_sent_at');

            // Indexes untuk query cepat
            $table->index('wa_status');
            $table->index('wa_sent_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_homepasses', function (Blueprint $table) {
            $table->dropIndex(['wa_status']);
            $table->dropIndex(['wa_sent_at']);
            $table->dropColumn([
                'wa_status',
                'wa_sent_at',
                'wa_error_message',
            ]);
        });
    }
};
