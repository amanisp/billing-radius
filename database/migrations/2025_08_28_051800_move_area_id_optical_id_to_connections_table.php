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
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropForeign(['optical_id']);

            $table->dropColumn(['area_id', 'optical_id']);
        });
        Schema::table('connections', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('optical_id')->nullable();

            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');
            $table->foreign('optical_id')->references('id')->on('optical_dists')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('optical_id')->nullable();

            $table->foreign('area_id')->references('id')->on('areas')->onDelete('set null');
            $table->foreign('optical_id')->references('id')->on('optical_dists')->onDelete('set null');
        });
        Schema::table('connections', function (Blueprint $table) {
            $table->dropForeign(['area_id']);
            $table->dropForeign(['optical_id']);
            $table->dropColumn(['area_id', 'optical_id']);
        });
    }
};
