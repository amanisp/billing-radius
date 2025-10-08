<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up()
    {
        Schema::create('whatsapp_webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('phone_number')->nullable();
            $table->enum('status', ['online', 'offline', 'connecting', 'disconnected'])->default('offline');
            $table->json('device_info')->nullable(); // Info device dari webhook
            $table->json('message_logs')->nullable(); // Recent messages dari webhook
            $table->timestamp('last_updated')->nullable();
            $table->timestamps();

            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->unique('group_id'); // Satu record per group
        });
    }

    public function down()
    {
        Schema::dropIfExists('whatsapp_webhook_logs');
    }
};
