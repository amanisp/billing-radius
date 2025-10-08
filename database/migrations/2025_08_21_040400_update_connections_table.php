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
        Schema::table('connections', function (Blueprint $table) {
            $table->string('username')->nullable()->change();
            $table->string('password')->nullable()->change();
            $table->string('mac_address')->nullable()->change();
            $table->unsignedBigInteger('nas_id')->nullable()->after('profile_id');
            $table->foreign('nas_id')->references('id')->on('nas')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->string('username')->change();
            $table->string('password')->change();
            $table->string('mac_address')->change();
            $table->dropForeign(['nas_id']);
            $table->dropColumn('nas_id');
        });
    }
};
