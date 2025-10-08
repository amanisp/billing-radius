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
        Schema::create('customers', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('nomor_pelanggan'); // Tambahkan kolom
            $table->enum('segmentasi', ['C', 'P', 'G']);
            $table->string('email');
            $table->string('phone_number');
            $table->text('address');
            $table->string('nik')->nullable();
            $table->string('npwp')->nullable();
            $table->string('ktpImg')->nullable();
            $table->unsignedBigInteger('area_id');
            $table->unsignedBigInteger('pop_id');
            $table->enum('transmitter', ['Wireless', 'Fiber Optic', 'SFP', 'SFP+']);
            $table->date('active_date');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('customers');
    }
};
