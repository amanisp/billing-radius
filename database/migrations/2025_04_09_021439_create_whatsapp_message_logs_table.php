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
        Schema::create('whatsapp_message_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable(); // NULL = Milik ISP, Ada = Milik Mitra
            $table->string('phone');
            $table->string('subject')->nullable();
            $table->text('message');
            $table->string('session_id')->nullable();
            $table->enum('status', ['sent', 'failed', 'pending'])->default('sent');
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            // Foreign key ke mitras
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_logs');
    }
};
