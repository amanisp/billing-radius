<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\Member;
use App\Helpers\InvoiceHelper;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function createManualInvoice(array $data)
    {
        return DB::transaction(function () use ($data) {

            $member = Member::with(['paymentDetail'])->findOrFail($data['member_id']);
            $paymentDetail = $member->paymentDetail;

            if (!$paymentDetail) {
                throw new \Exception("Member ini tidak memiliki data Payment Detail.");
            }

            $tenor = (int) $data['subscription_period'];
            $originalDay = Carbon::parse($paymentDetail->active_date)->day;

            $currentStartDate = $paymentDetail->getNextInvoiceStartDate();

            // VALIDASI BULAN AWAL
            $existingInvoice = Invoice::where('member_id', $member->id)
                ->whereYear('start_date', $currentStartDate->year)
                ->whereMonth('start_date', $currentStartDate->month)
                ->exists();

            if ($existingInvoice) {
                throw new \Exception("Invoice untuk bulan {$currentStartDate->format('F Y')} sudah ada.");
            }

            $createdInvoices = [];

            for ($i = 0; $i < $tenor; $i++) {

                $startDate = $currentStartDate->copy();

                // VALIDASI PER BULAN
                $exists = Invoice::where('member_id', $member->id)
                    ->whereYear('start_date', $startDate->year)
                    ->whereMonth('start_date', $startDate->month)
                    ->exists();

                if ($exists) {
                    throw new \Exception("Invoice bulan {$startDate->format('F Y')} sudah ada.");
                }

                $dueDate = $startDate->copy()->addDays(7);

                // ✅ PAKAI HELPER
                $invNumber = InvoiceHelper::generateInvoiceNumber($member->group_id, 'H');

                $invoice = Invoice::create([
                    'member_id'           => $member->id,
                    'connection_id'       => $member->connection_id,
                    'payer_id'            => $member->user_id,
                    'invoice_type'        => 'H',
                    'start_date'          => $startDate->toDateString(),
                    'due_date'            => $data['due_date'] ?? $dueDate->toDateString(),
                    'subscription_period' => 1,
                    'inv_number'          => $invNumber,
                    'amount'              => $data['amount'],

                    'status'              => 'unpaid',
                    'payment_url'         => 'default',

                    'group_id'            => $member->group_id,
                ]);

                $createdInvoices[] = $invoice;

                $currentStartDate->addMonth()->day($originalDay);
            }

            $lastCreatedInvoice = end($createdInvoices);
            $paymentDetail->updateLastInvoice($lastCreatedInvoice->start_date);

            return $createdInvoices;
        });
    }
}
