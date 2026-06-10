<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CashFlow extends Model
{
    /** =======================================
     * KONSTANTA UNTUK MENCEGAH TYPO
     * ======================================= */

    // Tipe Transaksi
    public const TYPE_IN  = 'in';
    public const TYPE_OUT = 'out';

    // Sumber Transaksi
    public const SOURCE_UMUM       = 'umum';
    public const SOURCE_SETOR_ADMIN = 'setor_admin';

    // Kategori Default (Bisa kamu tambah sesuai kebutuhan)
    public const CAT_MODAL   = 'belanja modal & perangkat';
    public const CAT_INFRA   = 'infrastruktur & jaringan';
    public const CAT_OPS     = 'operasional lapangan';
    public const CAT_GAJI    = 'gaji karyawan';
    public const CAT_UMUM    = 'umum & administrasi';
    public const CAT_SETORAN = 'setoran kas admin';

    protected $fillable = [
        'type',
        'source_type',
        'admin_id',
        'amount',
        'category',
        'description',
        'transaction_date',
        'user_id',
        'group_id',
    ];

    protected $casts = [
        'transaction_date' => 'date',
    ];

    /** =======================================
     * RELASI DATABASE
     * ======================================= */

    // Relasi ke User yang mengetik/menginput transaksi di sistem
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    // Relasi ke Admin yang menyerahkan uang (Asumsi admin ada di tabel users)
    public function admin()
    {
        return $this->belongsTo(User::class, 'admin_id');
    }


    /** =======================================
     * LOCAL SCOPES (MEMPERMUDAH QUERY)
     * ======================================= */

    // Filter khusus Pemasukan
    public function scopeIncome($query)
    {
        return $query->where('type', self::TYPE_IN);
    }

    // Filter khusus Pengeluaran
    public function scopeExpense($query)
    {
        return $query->where('type', self::TYPE_OUT);
    }
}
