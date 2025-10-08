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
            $table->dropForeign(['member_id']);
            $table->dropColumn('member_id');
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign((['connection_id']));
            $table->foreign('connection_id')
                ->references('id')
                ->on('connections')
                ->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->unsignedBigInteger('member_id')->nullable()->after('profile_id');
            $table->foreign('member_id')
                ->references('id')
                ->on('members')
                ->onDelete('set null');
        });
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->foreign('connection_id')
                ->references('id')
                ->on('connections')
                ->onDelete('set null');
        });
    }
};
