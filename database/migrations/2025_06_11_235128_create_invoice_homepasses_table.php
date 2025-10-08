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
        Schema::create('invoice_homepasses', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->enum('payment_method', ['bank_transfer', 'cash', 'payment_gateway'])->nullable();
            $table->unsignedBigInteger('pppoe_id')->nullable();
            $table->enum('invoice_type', ['C', 'P', 'H']);
            $table->date('start_date');
            $table->date('due_date');
            $table->date('next_inv_date');
            $table->date('paid_at')->nullable();
            $table->string('subscription_period')->nullable();
            $table->string('inv_number');
            $table->string('payment_url');
            $table->integer('amount');
            $table->enum('status', ['paid', 'unpaid', 'pending'])->nullable();
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
            $table->foreign('member_id')->references('id')->on('members')->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('invoice_homepasses');
    }
};
