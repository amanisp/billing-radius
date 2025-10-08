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
        Schema::table('invoice_homepasses', function (Blueprint $table) {
            $table->unsignedBigInteger('payer_id')->nullable()->after('id')->comment('User yang membayar invoice');
            $table->foreign('payer_id')
                ->references('id')
                ->on('users')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('invoice_homepasses', function (Blueprint $table) {
            $table->dropForeign(['payer_id']);
            $table->dropColumn('payer_id');
        });
    }
};
