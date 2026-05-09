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
        Schema::create('invoice_sequences', function (Blueprint $table) {
            $table->id();

            $table->unsignedBigInteger('group_id')->nullable();

            $table->unsignedBigInteger('area_id');

            $table->string('type', 10);

            /**
             * format:
             * 2605
             */
            $table->string('year_month', 4);

            $table->unsignedInteger('last_number')->default(0);

            $table->timestamps();

            /**
             * UNIQUE
             * 1 area + 1 type + 1 month
             */
            $table->unique([
                'group_id',
                'area_id',
                'type',
                'year_month'
            ], 'invoice_sequence_unique');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_sequences');
    }
};
