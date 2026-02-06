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
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->after('id');
            $table->string('recipient')->nullable();
            $table->text('message')->nullable();
            $table->string('status')->default('pending');
            $table->string('type')->default('single');
            $table->timestamp('sent_at')->nullable();
            $table->json('response_data')->nullable();

            $table->index('group_id');
            $table->index(['status', 'sent_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('whatsapp_message_logs', function (Blueprint $table) {
            //
        });
    }
};
