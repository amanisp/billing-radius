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
        Schema::create('payment_details', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_id')->constrained('groups')->onDelete('cascade');
            $table->enum('payment_type', ['prabayar', 'pasca_bayar']);
            $table->enum('billing_period', ['fixed', 'renewal']);
            $table->date('active_date');
            $table->unsignedBigInteger('amount')->default(0);
            $table->integer('discount')->default(0);
            $table->integer('ppn')->default(0);
            $table->date('last_invoice')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('payment_details');
    }
};
