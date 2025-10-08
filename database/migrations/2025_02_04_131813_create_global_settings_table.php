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
        Schema::create('global_settings', function (Blueprint $table) {
            $table->id();
            $table->boolean('isolir_mode')->default(false);
            $table->decimal('xendit_balance', 16, 2)->default(0);
            $table->time('isolir_time')->default('00:00:00')->nullable();
            $table->integer('invoice_generate_days')->default(7)->nullable(); // Maks 7 hari sebelum jatuh tempo
            $table->integer('notification_days')->default(3)->nullable(); // Default notifikasi 3 hari sebelum jatuh tempo
            $table->integer('isolir_after_exp')->default(1)->nullable(); // Default notifikasi 3 hari sebelum jatuh tempo
            $table->integer('due_date_pascabayar')->nullable();
            $table->text('footer')->nullable();
            $table->unsignedBigInteger('group_id')->nullable(); // Hanya user selain superadmin yang memiliki mitra
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('global_settings');
    }
};
