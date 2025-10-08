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
        Schema::create('profiles', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id');
            $table->string('name');
            $table->integer('price');
            $table->string('rate_rx')->nullable()->default('0');
            $table->string('rate_tx')->nullable()->default('0');
            $table->string('burst_rx')->nullable()->default('0');
            $table->string('burst_tx')->nullable()->default('0');
            $table->string('threshold_rx')->nullable()->default('0');
            $table->string('threshold_tx')->nullable()->default('0');
            $table->string('time_rx')->nullable()->default('0');
            $table->string('time_tx')->nullable()->default('0');
            $table->string('priority');
            $table->timestamps();

            // Foreign key ke tabel mitras, bisa NULL untuk data milik ISP
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('profiles');
    }
};
