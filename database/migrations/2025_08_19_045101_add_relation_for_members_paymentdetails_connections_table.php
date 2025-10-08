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
            $table->foreign('connection_id')
                ->references('id')
                ->on('connections')
                ->onDelete('set null');
            $table->foreign('payment_detail_id')
                ->references('id')
                ->on('payment_details')
                ->onDelete('set null');
            $table->foreign('invoice_id')
                ->references('id')
                ->on('invoices')
                ->onDelete('set null');
            $table->foreign('area_id')
                ->references('id')
                ->on('areas')
                ->onDelete('set null');
            $table->foreign('optical_id')
                ->references('id')
                ->on('optical_dists')
                ->onDelete('set null');
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->unsignedBigInteger('profile_id')->nullable()->change();
            $table->foreign('profile_id')
                ->references('id')
                ->on('profiles')
                ->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->dropForeign(['connection_id']);
            $table->dropForeign(['payment_detail_id']);
            $table->dropForeign(['invoice_id']);
            $table->dropForeign(['area_id']);
            $table->dropForeign(['optical_id']);
        });

        Schema::table('connections', function (Blueprint $table) {
            $table->dropForeign(['profile_id']);
        });
    }
};
