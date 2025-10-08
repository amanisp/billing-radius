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
        Schema::create('pppoe_accounts', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('member_id')->nullable();
            $table->string('username');
            $table->string('password');
            $table->string('internet_number');
            $table->boolean('billing_active')->default(true);
            $table->boolean('isolir')->default(false);
            $table->enum('billing_type', ['prabayar', 'pascabayar'])->nullable();
            $table->enum('billing_period', ['fixed_date', 'renewal', 'billing_cycle'])->nullable();
            $table->date('active_date')->nullable();
            $table->date('next_inv_date')->nullable();
            $table->decimal('ppn', 5, 2)->nullable();
            $table->integer('discount')->nullable();
            $table->unsignedBigInteger('group_id');
            $table->unsignedBigInteger('profile_id');
            $table->unsignedBigInteger('area_id')->nullable();
            $table->unsignedBigInteger('optical_id')->nullable();
            $table->timestamps();

            // Foreign Keys
            $table->foreign('group_id')->references('id')->on('groups')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('pppoe_accounts');
    }
};
