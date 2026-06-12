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
        Schema::table('global_settings', function (Blueprint $table) {
            $table->enum('acs_mode', ['default', 'mandiri'])
                ->default('default')
                ->after('group_id');

            $table->string('acs_url')
                ->nullable()
                ->after('acs_mode');

            $table->string('acs_port')
                ->nullable()
                ->after('acs_url');

            $table->string('acs_username')
                ->nullable()
                ->after('acs_port');

            $table->string('acs_password')
                ->nullable()
                ->after('acs_username');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('global_settings', function (Blueprint $table) {
            $table->dropColumn(['acs_mode', 'acs_url', 'acs_username', 'acs_password']);
        });
    }
};
