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
        Schema::table('users', function (Blueprint $table) {
            $table->enum('role', [
                'superadmin',
                'mitra',
                'kasir',
                'teknisi',
                'admin',
                'customer'
            ])->change();

            $table->string('expo_push_token')->nullable()->after('remember_token');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // 1. Hapus kolom expo_push_token
            $table->dropColumn('expo_push_token');

            // 2. Kembalikan enum role ke nilai sebelumnya
            // PENTING: Ganti isi array ini dengan daftar role lama kamu sebelum migration ini dibuat.
            // Contoh di bawah ini mengasumsikan role lamanya hanya superadmin, admin, dan customer.
            $table->enum('role', [
                'superadmin',
                'mitra',
                'kasir',
                'teknisi',
                'admin',
            ])->change();
        });
    }
};
