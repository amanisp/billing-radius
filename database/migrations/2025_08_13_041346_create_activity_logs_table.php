<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->timestamp('time')->useCurrent(); // Waktu kejadian
            $table->string('operation'); // Insert, Update, Delete, dll
            $table->string('table_name'); // Nama tabel yang diubah
            $table->string('username'); // User yang melakukan
            $table->string('role'); // Role user
            $table->ipAddress('ip_address')->nullable(); // IP address
            $table->string('session_id')->nullable(); // Session ID
            $table->text('details')->nullable(); // Detail perubahan (bisa JSON atau text)
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_logs');
    }
};
