<?php

namespace App\Http\Controllers;

use App\Models\GlobalSettings;
use App\Models\Payout;
use DateTime;
use DateTimeZone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;
use Xendit\Configuration;
use Xendit\Payout\CreatePayoutRequest;
use Xendit\Payout\PayoutApi;
use Yajra\DataTables\Facades\DataTables;

class TransactionController extends Controller
{
    public function __construct()
    {
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
    }

    public function settings()
    {
        $user = Auth::user();
        $globalSet = GlobalSettings::where('group_id', $user->group_id)->first();
        // dd("settings", $user, $globalSet);   
        return view('pages.billing.setting', compact('user', 'globalSet'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();

        $query = Payout::where('group_id', $user->group_id)->get();

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('id', fn($payout) => $payout->external_id)
            ->addColumn('email', fn($payout) => $payout->email)
            ->addColumn('nominal', fn($payout) => 'Rp ' . number_format($payout->amount, 0, ',', '.'))
            ->addColumn('exp', function ($payout) {
                if (!$payout->exp_link) return '-';

                $date = new DateTime($payout->exp_link, new DateTimeZone("UTC"));
                $date->setTimezone(new DateTimeZone("Asia/Jakarta"));

                return $date->format('Y-m-d H:i:s');
            })
            ->addColumn('status', function ($payout) {
                if ($payout->status === 'PENDING') {
                    $badgeColor = 'bg-warning';
                } elseif ($payout->status === 'COMPLETED') {
                    $badgeColor = 'bg-success';
                } else {
                    $badgeColor = 'bg-danger';
                }

                return '<span class="badge ' . $badgeColor . '">' . $payout->status . '</span>';
            })
            ->addColumn('action', function ($payout) {
                return '<div class="d-flex gap-2">
                            <a href="' . $payout->payout_url . '" target="_blank" class="btn btn-primary btn-sm">
                                Klaim Pembayaran
                            </a>
                        </div>';
            })
            ->rawColumns(['action', 'status', 'exp'])
            ->make(true);
    }


    public function payout(Request $request)
    {
        $request->validate([
            'id'    => 'required',
            'email' => 'required',
            'amount' => 'required',
        ]);
        $id = bin2hex(random_bytes(10)); // Reference unik

        try {
            $response = Http::withBasicAuth(env('XENDIT_SECRET_KEY'), '')
                ->asForm() // karena -d di curl = form
                ->post('https://api.xendit.co/payouts', [
                    'external_id' => $id . '_' . $request->id,
                    'amount' => $request->amount,
                    'email' => $request->email,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                Payout::create([
                    'payout_url' => $data['payout_url'],
                    'email' => $data['email'],
                    'exp_link' => $data['expiration_timestamp'],
                    'external_id' => $data['external_id'],
                    'amount' => $data['amount'],
                    'status' => $data['status'],
                    'group_id' => $request->id,
                ]);

                return response()->json([
                    'status' => 'success',
                    'data' => $data,
                ]);
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => $response->body(),
                ], $response->status());
            }
        } catch (\Throwable $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}
