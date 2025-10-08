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
            $table->string('internet_number');
            $table->boolean('billing_active')->default(false)->after('internet_number');
            $table->boolean('isolir')->default(false)->after('billing_active');
            $table->dateTime('active_date')->after('nas_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('connections', function (Blueprint $table) {
            $table->dropColumn(['internet_number', 'billing_active', 'isolir', 'active_date']);
        });
    }
};
