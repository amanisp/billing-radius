<?php

namespace App\Services;

use App\Jobs\GenerateInvoiceJob;
use App\Models\Mitra;

class GenerateInvMitra
{

    public function __invoke()
    {
        Mitra::chunk(100, function ($customers) {
            foreach ($customers as $customer) {
                dispatch(new GenerateInvoiceJob($customer));
            }
        });
    }
}
