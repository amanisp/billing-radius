<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            /**
             * hapus foreign lama
             */
            $table->dropForeign(['payer_id']);

            /**
             * foreign baru ke users
             */
            $table->foreign('payer_id')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('invoices', function (Blueprint $table) {

            /**
             * hapus foreign users
             */
            $table->dropForeign(['payer_id']);

            /**
             * balikin ke mitras
             */
            $table->foreign('payer_id')
                ->references('id')
                ->on('mitras')
                ->nullOnDelete();
        });
    }
};
