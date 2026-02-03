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
        Schema::create('admin_deposits', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('admin_id'); // yg menyetor
            $table->unsignedBigInteger('created_by'); // superadmin / owner
            $table->bigInteger('amount');
            $table->text('note')->nullable();

            $table->foreign('admin_id')->references('id')->on('users');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('admin_deposits');
    }
};
