<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Company;
use App\Models\Invoice;
use App\Models\InvoiceHomepass;
use App\Models\Mitra;
use App\Models\OpticalDist;
use App\Models\PppoeAccount;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        $startOfThisMonth = Carbon::now()->startOfMonth();


        $opticalCount = OpticalDist::where('group_id', $user->group_id)->count();
        $areaCount = Area::where('group_id', $user->group_id)->count();
        $mitraCount = 1; // Karena Mitra hanya melihat miliknya sendiri

        $unpaidCount = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'unpaid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $unpaidTotal = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'unpaid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        $paidTotalNow = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');
        $paidTotalLast = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $lastMonth->month)
            ->whereYear('paid_at', $lastMonth->year)
            ->sum('amount');

        $invoices = InvoiceHomepass::with(['payer']) // eager load relasi payer
            ->where('group_id', $user->group_id)
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        $invOverTotal = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', '!=', 'paid')
            ->where('created_at', '<', $startOfThisMonth)
            ->sum('amount');

        $invOverCount = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', '!=', 'paid')
            ->where('created_at', '<', $startOfThisMonth)
            ->count();



        // return dd($session);
        $homepass = PppoeAccount::where('group_id', $user->group_id)->count();
        $suspend = PppoeAccount::where('group_id', $user->group_id)->where('isolir', true)->count();


        return view('pages.dashboard', compact('opticalCount', 'areaCount', 'mitraCount', 'invoices', 'homepass', 'paidTotalNow', 'paidTotalLast', 'invOverCount', 'invOverTotal', 'suspend', 'unpaidCount', 'unpaidTotal'));
    }
}
