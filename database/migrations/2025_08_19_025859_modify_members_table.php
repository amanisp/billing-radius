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
            //add and rename columns
            $table->dropColumn('nik');
            $table->string('id_card', 16)->after('email');
            $table->foreignId('connection_id')->nullable()->after('id_card');
            $table->boolean('billing')->default(false)->after('connection_id');
            $table->foreignId('payment_detail_id')->nullable()->after('billing');
            $table->foreignId('invoice_id')->nullable()->after('payment_detail_id');
            $table->foreignId('area_id')->nullable()->after('invoice_id');
            $table->foreignId('optical_id')->nullable()->after('area_id');
            $table->renameColumn('name', 'fullname');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('members', function (Blueprint $table) {
            $table->string('nik', 16)->after('email');
            $table->dropColumn('id_card');
            $table->dropForeign(['connection_id']);
            $table->dropColumn('connection_id');
            $table->dropColumn('billing');
            $table->dropForeign(['payment_detail_id']);
            $table->dropColumn('payment_detail_id');
            $table->dropForeign(['invoice_id']);
            $table->dropColumn('invoice_id');
            $table->dropForeign(['area_id']);
            $table->dropColumn('area_id');
            $table->dropForeign(['optical_id']);
            $table->dropColumn('optical_id');
            $table->renameColumn('fullname', 'name');
        });
    }
};
