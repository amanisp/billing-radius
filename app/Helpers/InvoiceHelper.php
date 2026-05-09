<?php

namespace App\Helpers;

use App\Models\Invoice;
use App\Models\InvoiceSequence;
use Carbon\Carbon;

class InvoiceHelper
{
    public static function generateInvoiceNumber(
        $groupId,
        $areaId,
        $type,
        $modelClass = null,
        $periodStart = null
    ) {

        if (!$modelClass) {
            $modelClass = Invoice::class;
        }

        if (!$areaId) {
            throw new \Exception('Area invoice tidak valid.');
        }

        $period = $periodStart instanceof Carbon
            ? $periodStart->copy()
            : ($periodStart
                ? Carbon::parse($periodStart)
                : now());

        $yearMonth = $period->format('ym');

        /**
         * =========================================
         * LOCK SEQUENCE ROW
         * =========================================
         */
        $sequence = InvoiceSequence::where([
            'group_id'   => $groupId,
            'area_id'    => $areaId,
            'type'       => $type,
            'year_month' => $yearMonth,
        ])
            ->lockForUpdate()
            ->first();

        /**
         * kalau belum ada
         */
        if (!$sequence) {

            $sequence = InvoiceSequence::create([
                'group_id'   => $groupId,
                'area_id'    => $areaId,
                'type'       => $type,
                'year_month' => $yearMonth,
                'last_number' => 0,
            ]);

            /**
             * lock ulang
             */
            $sequence = InvoiceSequence::where('id', $sequence->id)
                ->lockForUpdate()
                ->first();
        }

        /**
         * increment atomic
         */
        $sequence->increment('last_number');

        /**
         * refresh value terbaru
         */
        $sequence->refresh();

        $nextNumber = $sequence->last_number;

        $numberPart = str_pad(
            $nextNumber,
            3,
            '0',
            STR_PAD_LEFT
        );

        return sprintf(
            'INV%s%s-%s%s',
            $type,
            $areaId,
            $yearMonth,
            $numberPart
        );
    }
}
