<?php

namespace App\Http\Controllers;

use App\Helpers\InvoiceHelper;
use App\Jobs\GenerateAllInvoiceJob;
use App\Models\AccountingTransaction;
use App\Models\Connection;
use App\Models\GlobalSettings;
use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
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
        $year = (int) $request->year;
        $month = (int) $request->month;

        // Ambil member dengan payment detail
        $member = Member::with('paymentDetail')->find($request->member_id);

        $nextInvoiceDate = null;

        if ($member && $member->paymentDetail) {
            // PERBAIKAN: Ambil invoice terakhir yang pernah dibuat (tidak peduli paid/unpaid)
            $lastCreatedInvoice = InvoiceHomepass::where('member_id', $member->id)
                ->orderByDesc('due_date')
                ->first();

            if ($lastCreatedInvoice) {
                $lastInvoice = $member->paymentDetail->last_invoice;
                $nextInvoiceDate = \Carbon\Carbon::parse($lastInvoice)->addMonth()->toDateString();
            } else {
                // Jika benar-benar invoice pertama kali, gunakan active_date
                $nextInvoiceDate = $member->paymentDetail->active_date;
            }
        }

        return response()->json([
            'invoice' => $lastCreatedInvoice,
            'year' => $year,
            'month' => $month,
            'id' => $request->member_id,
            'next_inv_date' => $nextInvoiceDate,
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


    public function getDateRangeStats(Request $request)
    {
        $user = Auth::user();
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        // Build base query with role-based scoping
        $baseQuery = InvoiceHomepass::query();

        /** @var \App\Models\User $user */
        if ($user->role === 'teknisi') {
            if (method_exists($user, 'assignedAreas')) {
                $assignedAreaIds = $user->assignedAreas()->pluck('areas.id')->toArray();
            } else {
                $assignedAreaIds = DB::table('technician_areas')
                    ->where('user_id', $user->id)
                    ->pluck('area_id')
                    ->toArray();
            }

            if (empty($assignedAreaIds)) {
                $baseQuery->whereRaw('1 = 0');
            } else {
                $baseQuery->whereHas('member.connection', function ($q) use ($assignedAreaIds) {
                    $q->whereIn('area_id', $assignedAreaIds);
                });
            }
        } else {
            $baseQuery->where('group_id', $user->group_id);
        }

        // Apply date range filter
        if ($dateFrom && $dateTo) {
            $baseQuery->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ]);
        }

        // Calculate statistics
        $totalInvoices = (clone $baseQuery)->count();
        $totalAmount = (clone $baseQuery)->sum('amount');

        $paidCount = (clone $baseQuery)
            ->where('status', 'paid')
            ->count();
        $paidAmount = (clone $baseQuery)
            ->where('status', 'paid')
            ->sum('amount');

        $unpaidCount = (clone $baseQuery)
            ->where('status', 'unpaid')
            ->count();
        $unpaidAmount = (clone $baseQuery)
            ->where('status', 'unpaid')
            ->sum('amount');

        // Overdue: unpaid invoices with due_date in the past
        $overdueCount = (clone $baseQuery)
            ->where('status', 'unpaid')
            ->where('due_date', '<', Carbon::now())
            ->count();
        $overdueAmount = (clone $baseQuery)
            ->where('status', 'unpaid')
            ->where('due_date', '<', Carbon::now())
            ->sum('amount');

        return response()->json([
            'total_invoices' => $totalInvoices,
            'total_amount' => $totalAmount,
            'paid_count' => $paidCount,
            'paid_amount' => $paidAmount,
            'unpaid_count' => $unpaidCount,
            'unpaid_amount' => $unpaidAmount,
            'overdue_count' => $overdueCount,
            'overdue_amount' => $overdueAmount,
        ]);
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $status = $request->query('status');
        $type = $request->query('type');
        $payer = $request->query('payer');
        $area = $request->query('area');
        $dateFrom = $request->query('date_from');
        $dateTo = $request->query('date_to');

        $template = WhatsappTemplate::where('template_type', 'invoice_terbit')
            ->where('group_id', $user->group_id)
            ->first();

        // Base query with all necessary relations
        $query = InvoiceHomepass::with([
            'member.paymentDetail',
            'member.connection.profile',
            'member.connection.area',
            'payer'
        ])->latest();

        // Role-based filtering
        /** @var \App\Models\User $user */
        if ($user->role === 'teknisi') {
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
            $query->where('group_id', $user->group_id);
        } else {
            $query->where('group_id', $user->group_id);
        }

        // Apply filters
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

        // Date Range Filter (NEW)
        if ($dateFrom && $dateTo) {
            $query->whereBetween('created_at', [
                Carbon::parse($dateFrom)->startOfDay(),
                Carbon::parse($dateTo)->endOfDay()
            ]);
        } elseif ($dateFrom) {
            $query->where('created_at', '>=', Carbon::parse($dateFrom)->startOfDay());
        } elseif ($dateTo) {
            $query->where('created_at', '<=', Carbon::parse($dateTo)->endOfDay());
        }

        return DataTables::of($query)
            ->addIndexColumn()
            ->addColumn('name', function ($invoice) {
                return $invoice->member ? $invoice->member->fullname : '-';
            })
            ->addColumn('area', function ($invoice) {
                return $invoice->member &&
                    $invoice->member->connection &&
                    $invoice->member->connection->area
                    ? $invoice->member->connection->area->name
                    : '-';
            })
            ->addColumn('inv_number', function ($invoice) {
                return $invoice->inv_number ?? '-';
            })
            ->addColumn('invoice_date', function ($invoice) {
                return $invoice->start_date
                    ? Carbon::parse($invoice->start_date)->format('d/m/Y')
                    : '-';
            })
            ->addColumn('payer', function ($invoice) {
                return $invoice->payer ? $invoice->payer->name : 'System';
            })
            ->addColumn('due_date', function ($invoice) {
                return $invoice->due_date
                    ? Carbon::parse($invoice->due_date)->format('d/m/Y')
                    : '-';
            })
            ->addColumn('paid_at', function ($invoice) {
                return $invoice->paid_at
                    ? Carbon::parse($invoice->paid_at)->format('d/m/Y')
                    : '-';
            })
            ->addColumn('payment_method', function ($invoice) {
                if (!$invoice->payment_method) return '-';

                return match ($invoice->payment_method) {
                    'payment_gateway' => 'Payment Gateway',
                    'cash' => 'Cash',
                    'bank_transfer' => 'Bank Transfer',
                    default => ucwords(str_replace('_', ' ', $invoice->payment_method))
                };
            })
            ->addColumn('total', function ($invoice) {
                return 'Rp ' . number_format($invoice->amount ?? 0, 0, ',', '.');
            })
            ->addColumn('status', function ($invoice) {
                $badgeColor = $invoice->status === 'paid' ? 'text-success' : 'text-danger';
                $statusText = $invoice->status === 'paid' ? 'Paid' : 'Unpaid';
                return '<span class="' . $badgeColor . '">' . $statusText . '</span>';
            })
            ->addColumn('type', function ($invoice) {
                if (!$invoice->member || !$invoice->member->paymentDetail) {
                    return '-';
                }
                return ucwords($invoice->member->paymentDetail->payment_type);
            })
            ->addColumn('action', function ($invoice) use ($template, $user) {
                if (!$invoice->member) return '';

                // Generate WhatsApp message
                $message = $template ? str_replace(
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
                        $invoice->connection->internet_number ?? '-',
                        $invoice->inv_number ?? '-',
                        number_format($invoice->amount ?? 0, 0, ',', '.'),
                        number_format($invoice->ppn ?? 0, 0, ',', '.'),
                        number_format($invoice->discount ?? 0, 0, ',', '.'),
                        number_format($invoice->total ?? $invoice->amount, 0, ',', '.'),
                        $invoice->connection->username ?? '-',
                        $invoice->connection->profile->name ?? '-',
                        $invoice->due_date ?? '-',
                        $invoice->subscription_period ?? '-',
                        $invoice->payment_url ?? '-',
                        'PT. Anugerah Media Data Nusantara'
                    ],
                    $template->content
                ) : '';

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
                    if ($invoice->payment_method !== 'payment_gateway' && $user->role === 'mitra') {
                        $buttons = '<div class="btn-group gap-1">';

                        $buttons .= '
            <button id="payment-cancel" data-inv="' . $invoice->inv_number . '"
                data-name="' . $invoice->member->name . '"
                data-id="' . $invoice->id . '"
                class="btn btn-outline-warning btn-sm">
                <i class="fa-solid fa-rotate-right"></i>
            </button>';

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
                }

                return '';
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

        DB::beginTransaction();
        try {
            // Cari invoice
            $invoice = InvoiceHomepass::with(['member.paymentDetail', 'connection.profile'])->findOrFail($request->id);

            // Update status invoice menjadi "paid"
            $invoice->update([
                'status' => 'paid',
                'payer_id' => $user->id,
                'payment_method' => $request->payment_method,
                'paid_at' => now()
            ]);

            AccountingTransaction::create([
                'group_id' => $invoice->group_id,
                'transaction_type' => 'income',
                'category' => 'subscription_payment',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->inv_number,
                'member_name' => $invoice->member->fullname,
                'amount' => $invoice->amount,
                'payment_method' => $request->payment_method,
                'received_by' => $user->id,
                'transaction_date' => now(),
                'description' => 'Pembayaran invoice ' . $invoice->inv_number,
                'notes' => 'Pembayaran invoice periode ' . $invoice->subscription_period,
            ]);

            // Update last_invoice
            if ($invoice->member && $invoice->member->paymentDetail) {
                $dueDate = Carbon::parse($invoice->due_date);
                PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                    'last_invoice' => $dueDate->format('Y-m-d'),
                ]);
            }

            DB::commit();

            // Send WhatsApp notification
            $apiKey = $this->getApiKey($user->group_id);
            $methodMap = [
                'cash'            => 'Cash',
                'bank_transfer'   => 'Bank Transfer',
                'payment_gateway' => 'Payment Gateway',
            ];

            if (isset($apiKey)) {
                $footer = GlobalSettings::where('group_id', $user->group_id)->value('footer');

                $this->whatsappService->sendFromTemplate(
                    $apiKey,
                    $invoice->member->phone_number,
                    'payment_paid',
                    [
                        'full_name'   => $invoice->member->fullname,
                        'no_invoice'  => $invoice->inv_number,
                        'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                        'pppoe_user' => $invoice->connection->username ?? '-',
                        'pppoe_profile' => $invoice->connection->profile->name ?? '-',
                        'period'    => $invoice->subscription_period,
                        'payment_gateway' => $methodMap[$request->payment_method] ?? ucfirst($request->payment_method),
                        'footer' => $footer
                    ],
                    ['group_id' => $invoice->group_id]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Pembayaran berhasil!',
                'invoice' => $invoice
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
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

        DB::beginTransaction();
        try {
            $invoice = InvoiceHomepass::with(['member.paymentDetail'])->findOrFail($request->id);

            AccountingTransaction::where('invoice_id', $invoice->id)
                ->where('transaction_type', 'income')
                ->delete(); // soft delete karena pakai SoftDeletes

            // Rollback last_invoice
            if ($invoice->member && $invoice->member->paymentDetail) {
                $previousPaidInvoice = InvoiceHomepass::where('member_id', $invoice->member_id)
                    ->where('status', 'paid')
                    ->where('id', '!=', $invoice->id)
                    ->orderByDesc('due_date')
                    ->first();

                if ($previousPaidInvoice) {
                    PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                        'last_invoice' => $previousPaidInvoice->due_date,
                    ]);
                } else {
                    PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                        'last_invoice' => null,
                    ]);
                }
            }

            // Update invoice menjadi unpaid
            $invoice->update([
                'status' => 'unpaid',
                'payer_id' => null,
                'payment_method' => null,
                'paid_at' => null
            ]);

            DB::commit();

            // Send WhatsApp notification
            $apiKey = $this->getApiKey($user->group_id);
            if (isset($apiKey)) {
                $footer = GlobalSettings::where('group_id', $user->group_id)->value('footer');

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
                    ['group_id' => $invoice->group_id]
                );
            }

            return response()->json([
                'success' => true,
                'message' => 'Cancel berhasil!',
                'invoice' => $invoice
            ]);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Terjadi kesalahan: ' . $e->getMessage()
            ], 500);
        }
    }

    public function xenditCallback(Request $request)
{
    $callbackToken = $request->header('X-CALLBACK-TOKEN');

    if ($callbackToken !== env('XENDIT_CALLBACK_TOKEN')) {
        Log::warning('Invalid Xendit callback token');
        return response()->json(['message' => 'Invalid callback token'], 401);
    }

    DB::beginTransaction();
    try {
        $externalId = $request->input('external_id'); // invoice number
        $status = $request->input('status'); // PAID, EXPIRED, etc
        $paidAmount = $request->input('paid_amount');
        $paidAt = $request->input('paid_at'); // ISO 8601 datetime

        Log::info('Xendit Callback Received', [
            'external_id' => $externalId,
            'status' => $status,
            'paid_amount' => $paidAmount
        ]);

        if ($status === 'PAID') {
            $invoice = InvoiceHomepass::with(['member.paymentDetail', 'connection.profile'])
                ->where('inv_number', $externalId)
                ->firstOrFail();

            // Update invoice
            $invoice->update([
                'status' => 'paid',
                'payment_method' => 'payment_gateway',
                'paid_at' => $paidAt ? Carbon::parse($paidAt) : now()
            ]);

            AccountingTransaction::create([
                'group_id' => $invoice->group_id,
                'transaction_type' => 'income',
                'category' => 'subscription_payment',
                'invoice_id' => $invoice->id,
                'invoice_number' => $invoice->inv_number,
                'member_name' => $invoice->member->fullname,
                'amount' => $paidAmount,
                'payment_method' => 'payment_gateway',
                'received_by' => null, // sistem otomatis
                'transaction_date' => $paidAt ? Carbon::parse($paidAt) : now(),
                'description' => 'Pembayaran invoice via Payment Gateway',
                'notes' => 'Pembayaran otomatis via Xendit - ' . $invoice->subscription_period,
            ]);

            // Update last_invoice
            if ($invoice->member && $invoice->member->paymentDetail) {
                $dueDate = Carbon::parse($invoice->due_date);
                PaymentDetail::where('id', $invoice->member->payment_detail_id)->update([
                    'last_invoice' => $dueDate->format('Y-m-d'),
                ]);
            }

            DB::commit();

            // Send notification
            $apiKey = $this->getApiKey($invoice->group_id);
            if ($apiKey) {
                $footer = GlobalSettings::where('group_id', $invoice->group_id)->value('footer');

                $this->whatsappService->sendFromTemplate(
                    $apiKey,
                    $invoice->member->phone_number,
                    'payment_paid',
                    [
                        'full_name'   => $invoice->member->fullname,
                        'no_invoice'  => $invoice->inv_number,
                        'total' => 'Rp ' . number_format($invoice->amount, 0, ',', '.'),
                        'pppoe_user' => $invoice->connection->username ?? '-',
                        'pppoe_profile' => $invoice->connection->profile->name ?? '-',
                        'period'    => $invoice->subscription_period,
                        'payment_gateway' => 'Payment Gateway',
                        'footer' => $footer
                    ],
                    ['group_id' => $invoice->group_id]
                );
            }

            Log::info('Xendit Callback Processed Successfully', ['invoice' => $externalId]);
        }

        return response()->json(['message' => 'Callback processed'], 200);

    } catch (\Exception $e) {
        DB::rollBack();
        Log::error('Xendit callback error: ' . $e->getMessage(), [
            'trace' => $e->getTraceAsString()
        ]);
        return response()->json(['message' => 'Error processing callback'], 500);
    }
}

    public function generateAll(Request $request)
    {
        $user = Auth::user();
        $groupId = $user->group_id;
        $now = Carbon::now()->startOfMonth();

        // Ambil semua member aktif billing
        $members = Member::with(['paymentDetail', 'connection.profile'])
            ->where('group_id', $groupId)
            ->where('billing', 1)
            ->get();

        $membersToGenerate = collect();

        foreach ($members as $member) {
            $pd = $member->paymentDetail;
            if (!$pd) continue;

            // Ambil invoice terakhir
            $lastInvoice = InvoiceHomepass::where('member_id', $member->id)
                ->orderByDesc('due_date')
                ->first();

            // Jika belum ada invoice sama sekali → gunakan active_date
            if (!$lastInvoice) {
                $activeDate = $pd->active_date ? Carbon::parse($pd->active_date)->startOfMonth() : $now;
                // Jika active_date <= bulan ini, generate 1 invoice bulan ini
                if ($activeDate->lte($now)) {
                    $membersToGenerate->push($member);
                }
                continue;
            }

            // Sudah ada invoice → cek apakah sudah sampai bulan ini atau ke depan
            $hasFutureInvoice = InvoiceHomepass::where('member_id', $member->id)
                ->whereDate('due_date', '>=', $now)
                ->exists();

            if ($hasFutureInvoice) {
                // Sudah ada invoice bulan ini atau bulan depan → skip
                continue;
            }

            // Jika belum ada invoice dari bulan lalu sampai sekarang, generate
            $lastDue = Carbon::parse($lastInvoice->due_date)->startOfMonth();
            if ($lastDue->lt($now)) {
                $monthsDiff = $lastDue->diffInMonths($now);
                if ($monthsDiff >= 1) {
                    $membersToGenerate->push($member);
                }
            }
        }

        if ($membersToGenerate->isEmpty()) {
            return response()->json([
                'status'  => 'info',
                'message' => 'Semua pelanggan sudah memiliki invoice sampai bulan ini atau ke depan.'
            ]);
        }

        foreach ($membersToGenerate as $member) {
            GenerateAllInvoiceJob::dispatch($member)->onQueue('invoices');
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Proses generate invoice sedang berjalan di background (' . $membersToGenerate->count() . ' pelanggan).'
        ]);
    }


    public function createInv(Request $request)
    {
        try {
            $apiInstance = new InvoiceApi();

            $request->validate([
                'member_id'   => 'required|string|max:255',
                'subsperiode' => 'required|integer|min:1', // jumlah bulan
                'duedate'     => 'required',               // tetap pakai untuk tampilan
                'periode'     => 'required',
                'item'        => 'required',
                'amount'      => 'required',
            ]);

            $member = Member::with(['paymentDetail', 'connection.profile'])
                ->where('id', $request->member_id)
                ->where('billing', 1)
                ->firstOrFail();

            $pd = $member->paymentDetail;

            $price    =  $pd->amount;
            $vat      =  $pd->ppn;
            $discount =  $pd->discount;
            $periode  = (int) $request->subsperiode; // jumlah bulan dibayar

            // Hitung total
            $total_amount = (($price + ($price * $vat / 100)) - $discount) * $periode;

            // Generate invoice number
            $invNumber = InvoiceHelper::generateInvoiceNumber(
                $member->connection->area_id ? $member->connection->area_id : 1,
                'H'
            );

            $duration = InvoiceHelper::invoiceDurationThisMonth();

            $dueDate = Carbon::parse($request->duedate)->startOfDay();
            $lastInvoice = $dueDate->copy()->addMonths($request->subsperiode - 1)->toDateString();
            PaymentDetail::where('id', $member->payment_detail_id)
                ->update(['last_invoice' => $lastInvoice]);


            // === Xendit invoice ===
            $create_invoice_request = new CreateInvoiceRequest([
                'external_id'      => $invNumber,
                'description'      => 'Tagihan nomor internet ' . $member->connection->internet_number .
                    'Periode: ' . $request->periode,
                'amount'           => intval($total_amount),
                'invoice_duration' => $duration,
                'currency'         => 'IDR',
                'payer_email'      => $member->email ?: 'customer@amanisp.net.id',
                'reminder_time'    => 1
            ]);

            $generateInvoice = $apiInstance->createInvoice($create_invoice_request);

            // === Simpan ke database ===
            $data = [
                'connection_id'        => $member->connection->id,
                'member_id'            => $member->id,
                'invoice_type'         => 'H',
                'start_date'           => now()->toDateString(), // Hanya tanggal, tanpa jam
                'due_date'             => $request->duedate,     // Hanya tanggal, tanpa jam
                'subscription_period'  => $request->periode,
                'inv_number'           => $invNumber,
                'amount'               => $total_amount,
                'status'               => 'unpaid',
                'group_id'             => $member->group_id,
                'payment_url'          => $generateInvoice['invoice_url'],
            ];

            InvoiceHomepass::create($data);

            return redirect()->route('billing.invoice')
                ->with('success', 'Invoice berhasil dibuat');
        } catch (\Throwable $th) {
            return redirect()->route('billing.invoice')
                ->with('error', $th->getMessage());
        }
    }


    public function destroy($id)
    {
        $invoice = InvoiceHomepass::findOrFail($id);
        $member = Member::findOrFail($invoice->member_id);

        // Hapus invoice dulu
        $invoice->delete();

        // Cek apakah masih ada invoice lain untuk member tersebut
        $lastInvoice = InvoiceHomepass::where('member_id', $member->id)
            ->orderByDesc('due_date')
            ->first();

        // Jika masih ada invoice lain, update last_invoice pakai due_date terakhir
        // Jika tidak ada, set null
        PaymentDetail::where('id', $member->payment_detail_id)
            ->update([
                'last_invoice' => $lastInvoice ? $lastInvoice->due_date : null,
            ]);

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Dihapus dan last_invoice diperbarui!',
        ]);
    }
}
