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
        Schema::create('cash_flows', function (Blueprint $table) {
            $table->id();

            // 1. JENIS TRANSAKSI (Masuk / Keluar)
            $table->enum('type', ['in', 'out'])
                ->comment('in = Pemasukan, out = Pengeluaran');

            // 2. SUMBER TRANSAKSI (Untuk melacak asal usul uang)
            // Misalnya: 'setor_admin', 'pembayaran_invoice', 'pengeluaran_umum'
            $table->string('source_type')->default('umum');

            // Jika ini dari setoran admin, catat ID admin-nya di sini
            $table->unsignedBigInteger('admin_id')->nullable()
                ->comment('Hanya diisi jika source_type adalah setor_admin');

            // 3. DETAIL KEUANGAN
            $table->bigInteger('amount'); // Nominal
            $table->string('category'); // Kategori (Sewa Tempat, Gaji, Setoran, dll)
            $table->text('description')->nullable(); // Keterangan tambahan
            $table->date('transaction_date'); // Tanggal transaksi

            // 4. RELASI SISTEM
            $table->unsignedBigInteger('user_id')->nullable() // Siapa yang menginput di sistem
                ->comment('Pencatat transaksi');
            $table->unsignedBigInteger('group_id')->nullable();

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('cash_flows');
    }
};
