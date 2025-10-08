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
        Schema::create('optical_dists', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id'); // NULL = milik ISP, ada = milik Mitra
            $table->unsignedBigInteger('area_id'); // Relasi ke Area
            $table->string('name');
            $table->string('ip_public')->nullable();
            $table->string('capacity')->nullable();
            $table->string('device_name')->nullable();
            $table->string('lat')->nullable();
            $table->string('lng')->nullable();
            $table->enum('type', ['ODP', 'ODC', 'POP']);
            $table->timestamps();

            // Foreign key ke tabel mitras, bisa NULL untuk data milik ISP
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');

            // Foreign key ke tabel area
            $table->foreign('area_id')->references('id')->on('areas')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('optical_dists');
    }
};
