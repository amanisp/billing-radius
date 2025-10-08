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
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->dropColumn(['username', 'password']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('pppoe_accounts', function (Blueprint $table) {
            $table->string('username')->after('id');
            $table->string('password')->after('username');
        });
    }
};
