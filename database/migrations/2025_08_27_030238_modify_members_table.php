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
            $table->string('phone_number')->nullable()->change();
            $table->string('id_card')->nullable()->change();
            $table->string('address')->nullable()->change();
            $table->string('email')->nullable()->change();

            // Drop foreign keys
            $table->dropForeign(['area_id']);
            $table->dropForeign(['optical_id']);

            // Change existing columns to non-nullable
            $table->unsignedBigInteger('area_id')->nullable(false)->change();
            $table->unsignedBigInteger('optical_id')->nullable(false)->change();

            // Re-add foreign keys
            $table->foreign('area_id')->references('id')->on('areas');
            $table->foreign('optical_id')->references('id')->on('optical_dists');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('phone_number')->change();
            $table->string('id_card')->change();
            $table->string('address')->change();
            $table->string('email')->change();

            $table->dropForeign(['area_id']);
            $table->dropForeign(['optical_id']);

            $table->unsignedBigInteger('area_id')->nullable()->change();
            $table->unsignedBigInteger('optical_id')->nullable()->change();

            $table->foreign('area_id')->references('id')->on('areas');
            $table->foreign('optical_id')->references('id')->on('optical_dists');
        });
    }
};
