<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Groups;
use App\Models\Member;
use App\Models\GlobalSettings;
use App\Models\WhatsappMessageLog;
use App\Models\WhatsappTemplate;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Yajra\DataTables\DataTables;

class WhatsappController extends Controller
{
    protected $whatsappService;

    public function __construct(WhatsAppService $whatsappService)
    {
        $this->whatsappService = $whatsappService;
    }

    private function getApiKey($groupId)
    {
        $settings = GlobalSettings::where('group_id', $groupId)->first();
        return $settings->whatsapp_api_key ?? null;
    }

    /**
     * WhatsApp dashboard page
     */
    public function index()
    {
        $user      = Auth::user();
        $group     = Groups::find($user->group_id);
        $apiKey    = $this->getApiKey($user->group_id);


        return view('pages.whatsapp.index', compact('group', 'apiKey'));
    }

    public function getMessageLogs(Request $request)
    {
        $query = WhatsappMessageLog::query()
            ->where('group_id', Auth::user()->group_id)
            ->orderBy('created_at', 'desc'); // urutkan terbaru dulu
        return DataTables::of($query)
            ->addColumn('status', fn($row) => '<small>' . match ($row->status) {
                'sent'    => '<i class="fa-solid text-primary fa-check-double"></i>',
                'pending' => '<i class="fa-solid fa-check"></i>',
                'failed'  => '<i class="fa-solid text-danger fa-xmark"></i>',
                default   => ''
            } . '</small>')
            ->addColumn('recipient', fn($row) => '<small>' . $row->recipient . '</small>')
            ->addColumn('message', fn($row) => '<small>' . str($row->message)->limit(25) . '</small>')
            ->editColumn('created_at', fn($row) => '<small>' . $row->created_at->format('Y-m-d H:i:s') . '</small>')
            ->rawColumns(['status', 'recipient', 'message', 'created_at'])
            ->make(true);
    }
    /**
     * Get WhatsApp status
     */
    public function getStatus()
    {
        $user   = Auth::user();
        $apiKey = $this->getApiKey($user->group_id);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'data'    => [
                    'status'       => 'Not Configured',
                    'phone_number' => '-',
                    'action'       => 'Please enter your API Key',
                    'configured'   => false,
                ],
            ]);
        }

        return response()->json([
            'success' => true,
            'data'    => [
                'status'       => 'Ready',
                'phone_number' => '-',
                'action'       => 'API Key configured',
                'configured'   => true,
            ],
        ]);
    }

    /**
     * Save API key to global settings
     */
    public function saveApiKey(Request $request)
    {
        $request->validate([
            'apikey' => 'required|string',
        ]);

        $user = Auth::user();

        try {
            // Simpan API Key
            GlobalSettings::updateOrCreate(
                ['group_id' => $user->group_id],
                ['whatsapp_api_key' => $request->apikey]
            );

            // Cek apakah sudah ada template untuk group ini
            $existingTemplates = WhatsappTemplate::where('group_id', $user->group_id)->count();

            if ($existingTemplates === 0) {
                // Default templates
                $defaultTemplates = [
                    'invoice_terbit' => "Salam [full_name]\n\nKami informasikan bahwa invoice Anda telah terbit dan dapat segera dibayarkan. Berikut rinciannya:\nID Pelanggan: [uid]\nNomor Invoice: [no_invoice]\nJumlah: Rp [amount]\nPPN: [ppn]\nDiskon: [discount]\nTotal: Rp [total]\nLayanan: Internet [pppoe_user] - [pppoe_profile]\nJatuh Tempo: [due_date]\nPeriode: [period]\nMohon segera lakukan pembayaran sebelum jatuh tempo.\n\nTerima kasih.\n[footer]\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

                    'payment_paid' => "Halo [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah berhasil diproses.\nJumlah: [total]\nLayanan: [pppoe_user] - [pppoe_profile]\nPeriode: [period]\nMetode Pembayaran: [payment_gateway]\n\nTerima kasih atas pembayaran Anda.\n\n[footer]",

                    'payment_cancel' => "Halo [full_name],\n\nPembayaran Anda untuk invoice #[no_invoice] telah dibatalkan.\nJumlah Tagihan: [total]\nTanggal Invoice: [invoice_date]\nJatuh Tempo: [due_date]\nPeriode: [period]\n\nSilakan lakukan pembayaran untuk menghindari gangguan layanan.\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

                    'account_suspend' => "Pelanggan yang Terhormat,\n\nLayanan internet Anda sementara ditangguhkan karena invoice belum dibayarkan.\n\nSilakan hubungi layanan pelanggan kami untuk bantuan.\n\n[footer]",

                    'account_active' => "Pelanggan yang Terhormat,\n\nLayanan internet Anda telah diaktifkan.\nUsername: [pppoe_user]\nProfil: [pppoe_profile]\n\nNikmati layanan kami!\n\n_Ini adalah pesan otomatis - mohon untuk tidak membalas langsung ke pesan ini_",

                    'invoice_reminder' => "Halo [full_name],\n\nIni adalah pengingat untuk pembayaran Anda yang akan datang.\nID Pelanggan: [uid]\nNomor Invoice: #[no_invoice]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSilakan lakukan pembayaran sebelum jatuh tempo.\n\n[payment_gateway]\n\n[footer]",

                    'invoice_overdue' => "Halo [full_name],\n\nInvoice Anda #[no_invoice] telah melewati jatuh tempo.\nID Pelanggan: [uid]\nJumlah: [total]\nJatuh Tempo: [due_date]\n\nSegera lakukan pembayaran untuk menghindari suspend layanan.\n\n[payment_gateway]\n\n[footer]",
                ];

                foreach ($defaultTemplates as $type => $content) {
                    WhatsappTemplate::create([
                        'group_id'      => $user->group_id,
                        'template_type' => $type,
                        'content'       => $content,
                    ]);
                }
            }

            return back()->with('success', "API Key berhasil disimpan");
        } catch (\Exception $th) {
            return redirect()->route('whatsapp.index')->with('error', $th->getMessage());
        }
    }

    /**
     * Test WhatsApp API connection
     */
    public function testConnection(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
        ]);

        $user   = Auth::user();
        $apiKey = $this->getApiKey($user->group_id);

        if (!$apiKey) {
            return response()->json([
                'success' => false,
                'message' => 'WhatsApp API not configured',
            ], 400);
        }

        $result = $this->whatsappService->sendTextMessage(
            $apiKey,
            $request->phone,
            'Test connection from ISP Management System.',
            'Test Connection',
            [
                'group_id' => $user->group_id
            ]
        );


        if ($result['success']) {
            return response()->json([
                'success' => true,
                'message' => 'Test message sent successfully!',
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send test message',
        ], 500);
    }

    public function sendBroadcast(Request $request)
    {
        $user   = Auth::user();
        $apiKey = $this->getApiKey($user->group_id);

        try {
            $request->validate([
                'recipients' => 'required',
                'subject' => 'required|string',
                'message' => 'required|string',
            ]);

            // Ambil data member sesuai filter
            $query = Member::with([
                'connection'
            ])
                ->where('group_id', $user->group_id)
                ->whereNotNull('phone_number')
                ->where('phone_number', '!=', '')
                ->where('phone_number', '!=', '0');

            if ($request->recipients === 'active') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', false));
            } elseif ($request->recipients === 'suspended') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', true));
            }

            $members = $query->get();

            $count = 0;
            foreach ($members as $member) {
                $broadcastSessionId = 'broadcast_' . date('YmdHis') . '_' . \Illuminate\Support\Str::random(8);

                $message = "Salam Bpk/Ibu {$member->fullname},\n" . $request->message;
                $broadcast = WhatsappMessageLog::create([
                    'group_id' => $member->group_id,
                    'phone'     => $member->phone_number,
                    'subject'   => $request->subject,
                    'message'   => $message,
                    'session_id' => $broadcastSessionId,
                    'status'    => 'pending',
                ]);

                // Hitung jeda tiap 10 pesan
                $delay = floor($count / 10) * 15;

                SendWhatsAppMessageJob::dispatch($apiKey, $broadcast, $member)
                    ->onQueue('broadcasts')
                    ->delay(now()->addSeconds($delay));

                $count++;
            }

            return back()->with('success', "Broadcast queued to {$members->count()} members.");
        } catch (\Throwable $th) {
            return redirect()->route('whatsapp.index')->with('error', $th->getMessage());
        }
    }

    public function send(Request $request)
    {
        try {
            $user   = Auth::user();
            $apiKey = $this->getApiKey($user->group_id);
            $request->validate([
                'recipients' => 'required|in:all,active,suspended',
                'subject'        => 'required|string|max:255',
                'message'        => 'required|string',
            ]);

            if (!$apiKey) {
                return redirect()->route('whatsapp.index')->with('error', 'WhatsApp API not configured');
            }
            $recipients      = $this->getRecipients($user->group_id, $request->recipients);
            $totalRecipients = count($recipients);

            return dd($totalRecipients);
        } catch (\Throwable $th) {
            return redirect()->route('whatsapp.index')->with('error', $th->getMessage());
        }
    }


    /**
     * Get recipients for broadcast
     */
    private function getRecipients($groupId, $recipientType)
    {
        $query = Member::with([
            'connection',
            'connection.profile',
            'invoices' => fn($q) => $q->latest()->limit(1),
        ])
            ->where('group_id', $groupId)
            ->whereNotNull('phone_number')
            ->where('phone_number', '!=', '')
            ->where('phone_number', '!=', '0');

        switch ($recipientType) {
            case 'active':
                $query->whereHas('connection', fn($q) => $q->where('isolir', false));
                break;

            case 'suspended':
                $query->whereHas('connection', fn($q) => $q->where('isolir', true));
                break;

            case 'all':
            default:
                break;
        }

        return $query->get()->map(function ($member) {
            $latestInvoice = $member->invoices()->latest()->first();
            $paymentDetail = $member->payment_detail_id
                ? \App\Models\PaymentDetail::find($member->payment_detail_id)
                : null;

            return [
                'id'              => $member->id,
                //info pelanggan
                'name'            => $member->fullname,
                'internet_number' => $member->connection->internet_number ?? 'N/A',
                'profile_name'    => $member->connection->profile->name ?? 'N/A',
                'password'        => $member->connection->password ?? 'N/A',
                'username'        => $member->connection->username ?? 'N/A',
                //invoice info
                'invoice_number'  => $latestInvoice->inv_number ?? 'N/A',
                'invoice_date'    => $this->formatDate($latestInvoice->created_at ?? 'N/A'),
                'amount'      => $latestInvoice->amount ?? ($paymentDetail->amount ?? 0),
                'ppn'             => $paymentDetail->ppn ?? 0,
                'discount'        => $paymentDetail->discount ?? 0,
                'billing_period'   => $paymentDetail->billing_period ?? 'N/A',
                'phone'           => $member->phone_number,
                'due_date'        => $latestInvoice->due_date ?? ($paymentDetail->next_invoice ?? null),
            ];
        })->toArray();
    }



    /**
     * Format date for display
     */
    private function formatDate($date)
    {
        if (!$date) {
            return 'N/A';
        }

        try {
            if ($date instanceof \Carbon\Carbon) {
                return $date->format('d/m/Y');
            }

            return \Carbon\Carbon::parse($date)->format('d/m/Y');
        } catch (\Exception $e) {
            return $date;
        }
    }

    public function saveTemplate(Request $request)
    {
        $request->validate([
            'type'    => 'required|string',
            'content' => 'required|string',
        ]);

        $template = WhatsappTemplate::updateOrCreate(
            [
                'group_id'      => Auth::user()->group_id,
                'template_type' => $request->type,
            ],
            [
                'content'       => $request->content,
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Template berhasil disimpan',
            'data'    => $template,
        ]);
    }

    /**
     * Get templates from database with fallback to defaults
     */
    public function getTemplates(Request $request)
    {
        $user = Auth::user();

        $request->validate(['type' => 'required|string']);

        $template = WhatsappTemplate::where('group_id', $user->group_id)
            ->where('template_type', $request->type)
            ->first();

        return response()->json([
            'status'  => $template ? 'success' : 'not_found',
            'content' => $template?->content ?? '',
        ]);
    }

    public function resetTemplate(Request $request)
    {
        $request->validate(['type' => 'required|string']);

        $defaults = config('whatsapp_templates');

        if (!isset($defaults[$request->type])) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Template default tidak ditemukan'
            ]);
        }

        $template = WhatsappTemplate::updateOrCreate(
            [
                'group_id'      => Auth::user()->group_id,
                'template_type' => $request->type,
            ],
            [
                'content'       => $defaults[$request->type],
            ]
        );

        return response()->json([
            'status'  => 'success',
            'message' => 'Template berhasil direset ke default',
            'data'    => $template,
        ]);
    }
}
