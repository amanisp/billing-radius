<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Carbon\Carbon;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\Groups;
use App\Helpers\InvoiceHelper;
use Xendit\Invoice\InvoiceApi;
use Xendit\Invoice\CreateInvoiceRequest;

class PaymentDetail extends Model
{
    protected $fillable = [
        'group_id',
        'payment_type',
        'billing_period',
        'active_date',
        'amount',
        'discount',
        'ppn',
        'last_invoice',
    ];

    protected $casts = [
        'active_date' => 'date',
        'last_invoice' => 'date',
        'amount' => 'integer',
        'discount' => 'integer',
        'ppn' => 'integer',
    ];

    // ================================================================
    // RELATIONS
    // ================================================================

    /**
     * Relasi ke Group
     */
    public function group()
    {
        return $this->belongsTo(Groups::class, 'group_id');
    }

    /**
     * Relasi ke Member (One to One)
     */
    public function member()
    {
        return $this->hasOne(Member::class, 'payment_detail_id');
    }

    /**
     * Relasi ke Invoices (via member_id)
     */
    public function invoices()
    {
        return $this->hasMany(InvoiceHomepass::class, 'member_id', 'member_id')
            ->orderByDesc('due_date');
    }

    /**
     * Get unpaid invoices only
     */
    public function unpaidInvoices()
    {
        return $this->invoices()->where('status', 'unpaid');
    }

    // ================================================================
    // CALCULATION HELPERS
    // ================================================================

    /**
     * Calculate total amount with PPN and discount
     *
     * @param int $periode Number of months
     * @return float
     */
    public function calculateTotalAmount($periode = 1)
    {
        $baseAmount = $this->amount * $periode;
        $ppnAmount = ($baseAmount * $this->ppn) / 100;
        $discountAmount = ($baseAmount * $this->discount) / 100;

        return $baseAmount + $ppnAmount - $discountAmount;
    }

    /**
     * Get total outstanding amount (unpaid invoices)
     *
     * @return float
     */
    public function getTotalOutstandingAmount()
    {
        return $this->unpaidInvoices()->sum('amount');
    }

    // ================================================================
    // DATE HELPERS
    // ================================================================

    /**
     * Get next invoice start date based on last_invoice or active_date
     *
     * @return Carbon
     */
    public function getNextInvoiceStartDate()
    {
        if ($this->last_invoice) {
            return Carbon::parse($this->last_invoice)->addMonthNoOverflow();
        }

        if ($this->active_date) {
            return Carbon::parse($this->active_date);
        }

        return Carbon::now();
    }

    /**
     * Get base date untuk generate invoice berikutnya
     * Alias untuk getNextInvoiceStartDate() untuk backward compatibility
     *
     * @return Carbon
     */
    public function getBaseDate()
    {
        return $this->getNextInvoiceStartDate();
    }

    /**
     * Update last invoice date
     *
     * @param string|Carbon $dueDate
     * @return bool
     */
    public function updateLastInvoice($dueDate)
    {
        $date = $dueDate instanceof Carbon ? $dueDate->toDateString() : $dueDate;

        return $this->update([
            'last_invoice' => $date
        ]);
    }

    /**
     * Reset last invoice (untuk rollback)
     *
     * @return bool
     */
    public function resetLastInvoice()
    {
        return $this->update([
            'last_invoice' => null
        ]);
    }

    // ================================================================
    // INVOICE GENERATION
    // ================================================================

    /**
     * Generate single invoice untuk bulan tertentu
     *
     * @param Carbon $dueDate Tanggal jatuh tempo (end of month)
     * @param int $periode Jumlah bulan (default: 1)
     * @return InvoiceHomepass
     * @throws \Exception
     */
    public function generateInvoiceForMonth(Carbon $dueDate, $periode = 1)
    {
        $member = $this->member;

        // Validasi member
        if (!$member) {
            throw new \Exception('Payment detail tidak memiliki member terkait');
        }

        if (!$member->billing) {
            throw new \Exception('Member tidak memiliki billing aktif');
        }

        if (!$member->connection) {
            throw new \Exception('Member tidak memiliki koneksi aktif');
        }

        // Calculate amount
        $totalAmount = $this->calculateTotalAmount($periode);

        // Generate invoice number
        $invNumber = InvoiceHelper::generateInvoiceNumber(
            $member->connection->area_id ?? 1,
            'H'
        );

        // Create Xendit invoice
        $apiInstance = new InvoiceApi();
        $createInvoiceRequest = new CreateInvoiceRequest([
            'external_id' => $invNumber,
            'description' => 'Tagihan nomor internet ' .
                ($member->connection->internet_number ?? '-') .
                ' Periode: ' . $dueDate->format('F Y'),
            'amount' => (int) $totalAmount,
            'invoice_duration' => InvoiceHelper::invoiceDurationThisMonth(),
            'currency' => 'IDR',
            'payer_email' => $member->email ?? 'customer@amanisp.net.id',
            'reminder_time' => 1,
        ]);

        $xenditInvoice = $apiInstance->createInvoice($createInvoiceRequest);

        // Save to database
        $invoice = InvoiceHomepass::create([
            'connection_id' => $member->connection->id,
            'member_id' => $member->id,
            'invoice_type' => 'H',
            'start_date' => now()->toDateString(),
            'due_date' => $dueDate->toDateString(),
            'subscription_period' => $dueDate->format('M Y'),
            'inv_number' => $invNumber,
            'amount' => $totalAmount,
            'status' => 'unpaid',
            'group_id' => $this->group_id,
            'payment_url' => $xenditInvoice['invoice_url'],
        ]);

        return $invoice;
    }

    /**
     * Check apakah invoice untuk bulan tertentu sudah ada
     *
     * @param Carbon $date
     * @return bool
     */
    public function hasInvoiceForMonth(Carbon $date)
    {
        if (!$this->member) {
            return false;
        }

        return InvoiceHomepass::where('member_id', $this->member->id)
            ->whereYear('due_date', $date->year)
            ->whereMonth('due_date', $date->month)
            ->exists();
    }

    /**
     * Check apakah member perlu generate invoice
     * (dari last_invoice sampai bulan sekarang)
     *
     * @return bool
     */
    public function needsInvoiceGeneration()
    {
        $member = $this->member;

        // Validasi
        if (!$member || !$member->billing || !$member->connection) {
            return false;
        }

        $baseDate = $this->getBaseDate();
        $endOfCurrentMonth = Carbon::now()->endOfMonth();

        // Kalau base date masih di masa depan, belum perlu generate
        if ($baseDate->greaterThan($endOfCurrentMonth)) {
            return false;
        }

        // Cek apakah ada invoice yang belum dibuat sampai bulan ini
        $tempDate = $baseDate->copy();
        while ($tempDate->lessThanOrEqualTo($endOfCurrentMonth)) {
            if (!$this->hasInvoiceForMonth($tempDate)) {
                return true; // Ada bulan yang belum dibuat invoicenya
            }
            $tempDate->addMonthNoOverflow();
        }

        return false;
    }

    // ================================================================
    // PAYMENT TYPE CHECKERS
    // ================================================================

    /**
     * Check if payment type is prepaid (prabayar)
     *
     * @return bool
     */
    public function isPrepaid()
    {
        return strtolower($this->payment_type) === 'prabayar';
    }

    /**
     * Check if payment type is postpaid (pascabayar)
     *
     * @return bool
     */
    public function isPostpaid()
    {
        return strtolower($this->payment_type) === 'pascabayar';
    }

    // ================================================================
    // ACCESSORS (for display)
    // ================================================================

    /**
     * Format amount with currency
     *
     * @return string
     */
    public function getFormattedAmountAttribute()
    {
        return 'Rp ' . number_format($this->amount, 0, ',', '.');
    }

    /**
     * Format last invoice date
     *
     * @return string
     */
    public function getFormattedLastInvoiceAttribute()
    {
        if (!$this->last_invoice) {
            return '-';
        }
        return Carbon::parse($this->last_invoice)->format('d M Y');
    }

    /**
     * Format active date
     *
     * @return string
     */
    public function getFormattedActiveDateAttribute()
    {
        if (!$this->active_date) {
            return '-';
        }
        return Carbon::parse($this->active_date)->format('d M Y');
    }
}
