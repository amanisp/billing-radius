<?php

namespace App\Http\Controllers;

use App\Helpers\InvoiceHelper;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Xendit\Configuration;
use Xendit\Invoice\CreateInvoiceRequest;
use Xendit\Invoice\InvoiceApi;
use Yajra\DataTables\DataTables;


class InvoiceController extends Controller
{
    protected $whatsappService;
    public function __construct(WhatsappService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
        Configuration::setXenditKey(env('XENDIT_SECRET_KEY'));
    }

    private function getApiKey($groupId)
    {
        $settings = GlobalSettings::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }


    public function checkInvoice(Request $request)
    {
        // cek apakah sudah ada invoice bulan ini
        $year = (int) $request->year;
        $month = (int) $request->month;

        $exists = InvoiceHomepass::where('member_id', $request->member_id)
            ->whereYear('due_date', $year)
            ->whereMonth('due_date', $month)
            ->exists();

        // cari invoice terakhir
        $lastInvoice = InvoiceHomepass::where('member_id', $request->member_id)
            ->orderByDesc('due_date')
            ->first();

        return response()->json([
            'year' => $request->year,
            'month' => $request->month,
            'id' => $request->member_id,
            'exists' => $exists,
            'next_inv_date' => $lastInvoice?->next_inv_date,
            'last_due_date' => $lastInvoice?->due_date,
            'last_period'   => $lastInvoice?->subscription_period,
        ]);
    }


    public function invoice()
    {
        $user = Auth::user();
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();
        $startOfThisMonth = Carbon::now()->startOfMonth();

        // Build customer query with role-based scoping
        $customerQuery = Connection::with(['member.paymentDetail', 'profile', 'group', 'latestInvoice'])
            ->whereHas('member', function ($query) {
                $query->where('billing', 1);
            });

        /** @var \App\Models\User $user */
        if ($user->role === 'teknisi') {
            // fetch assigned areas via User relation if available, otherwise fallback to pivot table
            if (method_exists($user, 'assignedAreas')) {
                $assignedAreaIds = $user->assignedAreas()->pluck('areas.id')->toArray();
            } else {
                $assignedAreaIds = DB::table('technician_areas')->where('user_id', $user->id)->pluck('area_id')->toArray();
            }
            if (empty($assignedAreaIds)) {
                $customerQuery->whereRaw('1 = 0');
            } else {
                $customerQuery->whereIn('area_id', $assignedAreaIds);
            }
        } else {
            // default: show only group members
            $customerQuery->where('group_id', $user->group_id);
        }

        $customer = $customerQuery->get();

        // Build base invoice query with role-based scoping
        $baseInvoiceQuery = InvoiceHomepass::query();
        /** @var \App\Models\User $user */
        if ($user->role === 'teknisi') {
            // fetch assigned areas via User relation if available, otherwise fallback to pivot table
            if (method_exists($user, 'assignedAreas')) {
                $assignedAreaIds = $user->assignedAreas()->pluck('areas.id')->toArray();
            } else {
                $assignedAreaIds = DB::table('technician_areas')->where('user_id', $user->id)->pluck('area_id')->toArray();
            }
            if (empty($assignedAreaIds)) {
                $baseInvoiceQuery->whereRaw('1 = 0');
            } else {
                $baseInvoiceQuery->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                    $q->whereIn('area_id', $assignedAreaIds);
                });
            }
        } else {
            $baseInvoiceQuery->where('group_id', $user->group_id);
        }

        $unpaidCount = (clone $baseInvoiceQuery)
            ->where('status', 'unpaid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();
        $unpaidTotal = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'unpaid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        $paidCount = (clone $baseInvoiceQuery)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->count();

        $paidTotal = (clone $baseInvoiceQuery)
            ->where('status', 'paid')
            ->whereMonth('paid_at', $now->month)
            ->whereYear('paid_at', $now->year)
            ->sum('amount');

        $overdueCount = (clone $baseInvoiceQuery)
            ->where('status', '!=', 'paid')
            ->where('created_at', '<', $startOfThisMonth)
            ->count();

        $overdueTotal = (clone $baseInvoiceQuery)
            ->where('status', '!=', 'paid')
            ->where('created_at', '<', $startOfThisMonth)
            ->sum('amount');

        $invoiceCount = (clone $baseInvoiceQuery)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->count();

        $invoiceTotal = (clone $baseInvoiceQuery)
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        return view('pages.billing.invoices.index', compact('customer', 'unpaidCount', 'unpaidTotal', 'overdueCount', 'overdueTotal', 'invoiceCount', 'invoiceTotal', 'paidCount', 'paidTotal'));
    }

    public function transaction()
    {
        $user = Auth::user();
        $now = Carbon::now();
        $lastMonth = $now->copy()->subMonth();

        $monthly_earning = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'paid')
            ->whereMonth('created_at', $now->month)
            ->whereYear('created_at', $now->year)
            ->sum('amount');

        $monthly_earning_last_month = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'paid')
            ->whereMonth('created_at', $now->subMonth()->month)
            ->whereYear('created_at', $now->subMonth()->year)
            ->sum('amount');

        $totalUnpaidLastMonth = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', '!=', 'paid')
            ->whereMonth('created_at', $lastMonth->month)
            ->whereYear('created_at', $lastMonth->year)
            ->sum('amount');

        $unpaidTotal = InvoiceHomepass::where('group_id', $user->group_id)
            ->where('status', 'unpaid')
            ->sum('amount');
        // dd($totalUnpaidLastMonth, $monthly_earning_last_month);
        return view('pages.billing.transaction', compact('unpaidTotal', 'monthly_earning_last_month', 'totalUnpaidLastMonth', 'monthly_earning'));
    }


    public function getData(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status');
        $type = $request->query('type');
        $payer = $request->query('payer');
        $area = $request->query('area');

        // Base query: include relations used in datatable
        $query = InvoiceHomepass::with(['member.paymentDetail', 'payer', 'member.connection.profile', 'member.connection.area'])->latest();

        // Role-based filtering: teknisi only sees invoices for assigned area(s)
        /** @var \App\Models\User $user */
        if ($user->role === 'teknisi') {
            // relation + fallback
            if (method_exists($user, 'assignedAreas')) {
                $assignedAreaIds = $user->assignedAreas()->pluck('areas.id')->toArray();
            } else {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();
            }

            if (empty($assignedAreaIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                    $q->whereIn('area_id', $assignedAreaIds);
                });
            }
        } elseif (in_array($user->role, ['mitra', 'kasir'])) {
            // Mitra and kasir can only see invoices in their group
            $query->where('group_id', $user->group_id);
        } else {
            // Default to group scoping for other roles as a safe fallback
            $query->where('group_id', $user->group_id);
        }

        // Apply filters from request
        if ($status) {
            $query->where('status', $status);
        }

        if ($payer) {
            if ($payer === 'kasir') {
                $query->whereHas('payer', function ($q2) {
                    $q2->where('role', 'kasir');
                });
            } elseif ($payer === 'admin') {
                $query->whereHas('payer', function ($q2) {
                    $q2->where('role', 'mitra');
                });
            } elseif ($payer === 'teknisi') {
                $query->whereHas('payer', function ($q2) {
                    $q2->where('role', 'teknisi');
                });
            }
        }

        if ($type) {
            $query->whereHas('member.paymentDetail', function ($q2) use ($type) {
                $q2->where('payment_type', strtolower($type));
            });
        }

        if ($area) {
            $query->whereHas('member.connection', function ($q2) use ($area) {
                $q2->where('area_id', $area);
            });
        }

        // return dd($query);

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('name', fn($invoice) => $invoice->member->fullname)
            ->addColumn('area', function ($invoice) {
                return $invoice->member->connection->area->name ?? '-';
            })
            ->addColumn('inv_number', fn($invoice) => $invoice->inv_number)
            ->addColumn('invoice_date', fn($invoice) => $invoice->start_date)
            ->addColumn('payer', fn($invoice) => $invoice->payer->name ?? 'System')
            ->addColumn('due_date', fn($invoice) => $invoice->due_date)
            ->addColumn('paid_at', fn($invoice) => $invoice->paid_at)
            ->addColumn('payment_method', function ($invoice) {
                return match ($invoice->payment_method) {
                    'payment_gateway' => 'Payment Gateway',
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    default => ucwords(str_replace('_', ' ', $invoice->payment_method))
                };
            })
            ->addColumn('total', fn($invoice) => 'Rp ' . number_format($invoice->amount, 0, ',', '.'))
            ->addColumn('status', function ($invoice) {
                $badgeColor = $invoice->status === 'paid' ? 'text-success' : 'text-danger';
                $statusText = $invoice->status === 'paid' ? 'Paid' : 'Unpaid';
                return '<span class="' . $badgeColor . '">' . $statusText . '</span>';
            })
            ->addColumn('type', fn($invoice) => ucwords($invoice->member->paymentDetail->payment_type) ?? '-')
            ->addColumn('action', function ($invoice) use ($template) {
                // Ganti placeholder dengan data sebenarnya
                $message = str_replace(
                    [
                        '[full_name]',
                        '[uid]',
                        '[no_invoice]',
                        '[amount]',
                        '[ppn]',
                        '[discount]',
                        '[total]',
                        '[pppoe_user]',
                        '[pppoe_profile]',
                        '[due_date]',
                        '[period]',
                        '[payment_url]',
                        '[footer]'
                    ],
                    [
                        $invoice->member->fullname ?? '-',
                        $invoice->member->uid ?? '-',
                        $invoice->inv_number ?? '-',
                        number_format($invoice->amount, 0, ',', '.'),
                        number_format($invoice->ppn ?? 0, 0, ',', '.'),
                        number_format($invoice->discount ?? 0, 0, ',', '.'),
                        number_format($invoice->total ?? $invoice->amount, 0, ',', '.'),
                        $invoice->member->pppoe_user ?? '-',
                        $invoice->member->pppoe_profile ?? '-',
                        $invoice->due_date ?? '-',
                        $invoice->period ?? '-',
                        $invoice->payment_url ?? '-',
                        'PT. Anugerah Media Nusantara' // footer default
                    ],
                    $template
                );

                // Encode untuk URL WhatsApp
                $waMessage = urlencode($message);
                $waUrl = 'https://wa.me/' . $invoice->member->phone_number . '?text=' . $waMessage;

                if ($invoice->status === 'unpaid') {
                    return '<div class="btn-group gap-1">
                <button id="btn-pay" class="btn btn-outline-primary btn-sm"
                    data-inv="' . $invoice->inv_number . '"
                    data-name="' . $invoice->member->name . '"
                    data-id="' . $invoice->id . '">
                   PAY
                </button>
                <a href="' . $waUrl . '" target="_blank" class="btn btn-outline-success btn-sm" id="btn-send-notif">
                    <i class="fa-brands fa-whatsapp"></i>
                </a>
                <a href="' . $invoice->payment_url . '" target="_blank" class="btn btn-outline-info btn-sm">
                    <i class="fa-solid fa-file-invoice-dollar"></i>
                </a>
                <button data-inv="' . $invoice->inv_number . '" id="btn-delete"
                    data-name="' . $invoice->member->name . '"
                    data-id="' . $invoice->id . '"
                    class="btn btn-outline-danger btn-sm">
                    <i class="fa-solid fa-trash"></i>
                </button>
            </div>';
                } else {
                    $buttons = '<div class="btn-group gap-1">';

                    if ($invoice->payment_method !== 'payment_gateway' && Auth::user()->role === 'mitra') {
                        $buttons .= '
                <button id="payment-cancel" data-inv="' . $invoice->inv_number . '"
                    data-name="' . $invoice->member->name . '"
                    data-id="' . $invoice->id . '"
                    class="btn btn-outline-warning btn-sm">
                    <i class="fa-solid fa-rotate-right"></i>
                </button>';
                    }

                    $buttons .= '
            <button data-inv="' . $invoice->inv_number . '"
                id="btn-delete"
                data-name="' . $invoice->member->name . '"
                data-id="' . $invoice->id . '"
                class="btn btn-outline-danger btn-sm">
                <i class="fa-solid fa-trash"></i>
            </button>';

                    $buttons .= '</div>';

                    return $buttons;
                }
            })
            ->rawColumns(['action', 'total', 'status'])
            ->make(true);
    }

    public function payManual(Request $request)
    {
        $user = Auth::user();

        $request->validate([
            'id' => 'required|exists:invoice_homepasses,id',
            'payment_method' => 'required|in:cash,bank_transfer',
        ]);


        // Cari invoice berdasarkan ID
        $invoice = InvoiceHomepass::findOrFail($request->id);
        $data = $invoice->with(['member', 'connection.profile', 'group'])->first();

        // Update status invoice menjadi "paid"
        $invoice->update([
            'status' => 'paid',
            'payer_id' => $user->id,
            'payment_method' => $request->payment_method,
            'paid_at' => now() // Menyimpan waktu pembayaran
        ]);

        $apiKey = $this->getApiKey($user->group_id);
        $methodMap = [
            'cash'            => 'Cash',
            'bank_transfer'   => 'Bank Transfer',
            'payment_gateway' => 'Payment Gateway',
        ];

        if (isset($apiKey)) {
            $footer = GlobalSettings::where('group_id', $user->group_id)
                ->value('footer');

            $this->whatsappService->sendFromTemplate(
                $apiKey,
                $invoice->member->phone_number,
                'payment_paid',
                [
                    'full_name'   => $invoice->member->fullname,
                    'no_invoice'  => $invoice->inv_number,
                    'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                    'pppoe_user' => $invoice->connection->username,
                    'pppoe_profile' => $invoice->connection->profile->name,
                    'period'    => $invoice->subscription_period,
                    'payment_gateway' => $methodMap[$request->payment_method] ?? ucfirst($request->payment_method),
                    'footer' => $footer
                ],
                [
                    'group_id' => $invoice->group_id,
                ]
            );
        }

        return response()->json([
            'message' => 'Pembayaran berhasil!',
            'invoice' => $invoice
        ]);
    }

    // public function show($id)
    // {
    //     $invoice = Invoice::where('inv_number', $id)
    //         ->leftJoin('mitras', 'invoices.payer_id', '=', 'mitras.id')
    //         ->select('invoices.*', 'mitras.*')->first();
    //     // dd($invoice);
    //     $moon = Carbon::parse($invoice->due_date)->translatedFormat('F Y');
    //     $priceBandwith = 'Rp. ' . number_format($invoice->price, 0, ',', '.');




    //     $html = View::make('pages.billing.invoice', compact('invoice', 'moon', 'priceBandwith'))->render();

    //     // Buat instance mPDF
    //     $mpdf = new Mpdf();
    //     $mpdf->WriteHTML($html);

    //     return response($mpdf->Output('invoice.pdf', 'I'))->header('Content-Type', 'application/pdf');
    // }

    public function payCancel(Request $request)
    {
        $user = Auth::user();
        $request->validate([
            'id' => 'required|exists:invoice_homepasses,id',
        ]);

        // Cari invoice berdasarkan ID
        $invoice = InvoiceHomepass::findOrFail($request->id);
        $data = $invoice->with(['member', 'connection.profile', 'group'])->first();

        // Update status invoice menjadi "paid"
        $invoice->update([
            'status' => 'unpaid',
            'payer_id' => null,
            'payment_method' => null,
            'paid_at' => null
        ]);

        $apiKey = $this->getApiKey($user->group_id);

        if (isset($apiKey)) {
            $footer = GlobalSettings::where('group_id', $user->group_id)
                ->value('footer');

            $this->whatsappService->sendFromTemplate(
                $apiKey,
                $invoice->member->phone_number,
                'payment_cancel',
                [
                    'full_name'   => $invoice->member->fullname,
                    'no_invoice'  => $invoice->inv_number,
                    'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                    'invoice_date' => $invoice->start_date,
                    'due_date' => $invoice->due_date,
                    'period'    => $invoice->subscription_period,
                ],
                [
                    'group_id' => $invoice->group_id,
                ]
            );
        }

        return response()->json([
            'message' => 'Cancel berhasil!',
            'invoice' => $invoice
        ]);
    }

    public function createInv(Request $request)
    {
        try {
            $apiInstance = new InvoiceApi();
            // dd($request->all());
            $request->validate([
                'member_id'    => 'required|string|max:255',
                'subsperiode' => 'required',
                'duedate' => 'required',
                'periode' => 'required',
                'item' => 'required'
            ]);

            if (isset($request->member_id)) {
                $member = Member::where('id', $request->member_id)
                    ->with(['paymentDetail', 'connection.profile'])
                    ->where('billing', 1)
                    ->first();

                $price = (int) $member->connection->profile->price;
                $invoiceDate = Carbon::now(); // Tanggal generate invoice (hari ini)
                $periode = (int) $request->subsperiode;
                $total_amount = $price * $periode;

                // Next INV Date
                $lastInvoice = InvoiceHomepass::where('member_id', $member->id)
                    ->orderByDesc('due_date')
                    ->first();

                if ($lastInvoice) {
                    $invoiceDate = \Carbon\Carbon::parse($lastInvoice->next_inv_date);
                } else {
                    $invoiceDate = now();
                }

                $next_inv_date = $invoiceDate->copy()->addMonths($periode);
                $activeDay = \Carbon\Carbon::parse($member->paymentDetail->active_date)->day;
                $daysInMonth = $next_inv_date->daysInMonth;
                $next_inv_date->day(min($activeDay, $daysInMonth));

                // samakan tanggal dengan active_date
                $invNumber = InvoiceHelper::generateInvoiceNumber($member->connection->area_id ? $member->connection->area_id : 1, 'H');
                $duration = InvoiceHelper::invoiceDurationThisMonth();


                $create_invoice_request =  new CreateInvoiceRequest([
                    'external_id' => $invNumber,
                    'description' => 'Tagihan nomor internet ' .  $member->connection->internet_number . ' Periode: ' . $request->periode,
                    'amount' => intval($total_amount),
                    'invoice_duration' => $duration,
                    'currency' => 'IDR',
                    'payer_email' => $member->email ? $member->email : 'customer@amanisp.net.id',
                    'reminder_time' => 1
                ]);


                $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

                $data = [
                    'connection_id' => $member->connection->id,
                    'member_id' => $member->id,
                    'invoice_type' => 'H',
                    'start_date' => $invoiceDate,
                    'due_date' => $request->duedate,
                    'subscription_period' => $request->periode,
                    'inv_number' => $invNumber,
                    'amount' => $total_amount,
                    'status' => 'unpaid',
                    'group_id' => $member->group_id,
                    'next_inv_date' => $next_inv_date,
                    'payment_url' => $generateInvoice['invoice_url'],
                ];

                PaymentDetail::findOrFail($member->payment_detail_id)->update([
                    'next_invoice' => $next_inv_date,
                ]);

                InvoiceHomepass::create($data);
            }

            return redirect()->route('billing.invoice')->with('success', 'Invoice berhasil dibuat!');
        } catch (\Throwable $th) {
            return redirect()->route('billing.invoice')->with('error', $th->getMessage());
        }
    }

    public function destroy($id)
    {
        $data = InvoiceHomepass::where('id', $id)->firstOrFail();

        // Hapus data
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Dihapus!',
        ]);
    }
}
