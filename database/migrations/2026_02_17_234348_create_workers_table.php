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
        Schema::create('workers', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('group_id')->nullable();
            $table->string('fullname');
            $table->enum('position', ['Direktur Utama', 'Direktur', 'Komisaris Utama', 'Komisaris', 'Staff of Administration', 'NOC', 'CS', 'Finance', 'TOS', 'EOS']); // Tambahkan kolom
            $table->string('subdistrict');
            $table->string('district');
            $table->string('nip');
            $table->string('link_documents');
            $table->string('phone_number');
            $table->boolean('ktp')->default(false);
            $table->boolean('kk')->default(false);
            $table->boolean('pict')->default(false);
            $table->boolean('npwp')->default(false);
            $table->boolean('idcard')->default(false);
            $table->boolean('bpjs')->default(false);
            $table->boolean('banner')->default(false);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('workers');
    }
};
