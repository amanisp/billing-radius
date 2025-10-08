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
        Schema::create('areas', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable(); // NULL = Milik ISP, Ada = Milik Mitra
            $table->string('name'); // Nama area
            $table->string('area_code')->nullable();
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
        Schema::dropIfExists('areas');
    }
};
