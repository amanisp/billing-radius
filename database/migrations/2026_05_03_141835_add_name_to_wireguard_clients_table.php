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
        Schema::table('wireguard_clients', function (Blueprint $table) {
            // Ini yang akan dieksekusi saat di-migrate
            $table->string('name')->nullable()->after('group_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('wireguard_clients', function (Blueprint $table) {
            // Ini yang akan dieksekusi saat di-rollback
            if (Schema::hasColumn('wireguard_clients', 'name')) {
                $table->dropColumn('name');
            }
        });
    }
};
