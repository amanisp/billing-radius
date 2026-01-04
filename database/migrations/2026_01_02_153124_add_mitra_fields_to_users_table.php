<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Data lengkap Mitra
            $table->string('nip', 20)->nullable()->after('phone_number');

            // Customer number (manual input)
            $table->string('customer_number', 50)->nullable()->unique()->after('nip');

            // Area assignment untuk mitra
            $table->unsignedBigInteger('area_id')->nullable()->after('customer_number');

            // Register date & payment method
            $table->date('register')->nullable()->after('area_id');
            $table->string('payment')->nullable()->after('register');

            // Foreign key
            $table->foreign('area_id')
                  ->references('id')
                  ->on('areas')
                  ->onDelete('set null');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropColumn([
                'nik',
                'npwp',
                'nip',
                'customer_number',
                'area_id',
                'register',
                'payment'
            ]);
        });
    }
};
