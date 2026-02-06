<?php

namespace App\Http\Controllers;

use App\Jobs\SendWhatsAppMessageJob;
use App\Models\Area;
use App\Models\Groups;
use App\Models\Member;
use App\Models\GlobalSettings;
use App\Models\WhatsappMessageLog;
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

        // Load areas untuk broadcast modal
        $data = Area::where('group_id', $user->group_id)
            ->withCount(['connection as member_count' => function ($query) {
                $query->whereHas('member', function ($q) {
                    $q->whereNotNull('phone_number')
                        ->where('phone_number', '!=', '')
                        ->where('phone_number', '!=', '0');
                });
            }])
            ->orderBy('name')
            ->get();

        return view('pages.whatsapp.index', compact('group', 'apiKey', 'data'));
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


        return response()->json([
            'success' => false,
            'message' => $result['error'] ?? 'Failed to send test message',
        ], 500);
    }

    public function getBroadcastCount(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'area_id' => 'required',
                'recipients' => 'required|in:all,active,suspended'
            ]);

            $areaId = $request->area_id;
            $recipientType = $request->recipients;

            // Base query untuk member dengan phone number valid
            $query = Member::where('group_id', $user->group_id)
                ->whereNotNull('phone_number')
                ->where('phone_number', '!=', '')
                ->where('phone_number', '!=', '0')
                ->whereHas('connection');

            // Filter by area - langsung dari database
            if ($areaId !== 'all') {
                $query->whereHas('connection', function ($q) use ($areaId) {
                    $q->where('area_id', $areaId);
                });
            }

            // Filter by status - langsung dari connection database
            if ($recipientType === 'active') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', false));
            } elseif ($recipientType === 'suspended') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', true));
            }

            $count = $query->count();

            // Get area name dari database (bukan API)
            $areaName = null;
            if ($areaId !== 'all') {
                $area = Area::where('id', $areaId)
                    ->where('group_id', $user->group_id)
                    ->first();
                $areaName = $area ? $area->name : 'Unknown Area';
            }

            return response()->json([
                'success' => true,
                'count' => $count,
                'area_name' => $areaName
            ]);
        } catch (\Exception $e) {
            Log::error('Error getting broadcast count: ' . $e->getMessage());
            return response()->json([
                'success' => false,
                'message' => 'Error calculating recipient count'
            ], 500);
        }
    }



    public function sendBroadcast(Request $request)
    {
        $user   = Auth::user();
        $apiKey = $this->getApiKey($user->group_id);

        try {
            $request->validate([
                'area_id' => 'required',
                'recipients' => 'required|in:all,active,suspended',
                'subject' => 'required|string|max:255',
                'message' => 'required|string',
            ]);

            if (!$apiKey) {
                return back()->with('error', 'WhatsApp API not configured');
            }

            $areaId = $request->area_id;
            $recipientType = $request->recipients;

            // Build query untuk ambil members - langsung dari database
            $query = Member::with(['connection', 'connection.profile'])
                ->where('group_id', $user->group_id)
                ->whereNotNull('phone_number')
                ->where('phone_number', '!=', '')
                ->where('phone_number', '!=', '0')
                ->whereHas('connection');

            // Filter by area - langsung dari database
            if ($areaId !== 'all') {
                $query->whereHas('connection', function ($q) use ($areaId) {
                    $q->where('area_id', $areaId);
                });
            }

            // Filter by status - langsung dari connection database
            if ($recipientType === 'active') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', false));
            } elseif ($recipientType === 'suspended') {
                $query->whereHas('connection', fn($q) => $q->where('isolir', true));
            }

            $members = $query->get();

            if ($members->isEmpty()) {
                return back()->with('error', 'No recipients found with the selected filters.');
            }

            // Generate broadcast session ID
            $broadcastSessionId = 'broadcast_' . date('YmdHis') . '_' . \Illuminate\Support\Str::random(8);

            $count = 0;
            foreach ($members as $member) {
                $message = "Salam Bpk/Ibu {$member->fullname},\n\n" . $request->message;

                $broadcast = WhatsappMessageLog::create([
                    'group_id' => $member->group_id,
                    'phone'     => $member->phone_number, // Langsung dari database Member
                    'subject'   => $request->subject,
                    'message'   => $message,
                    'session_id' => $broadcastSessionId,
                    'status'    => 'pending',
                ]);

                // Calculate delay (15 seconds per batch of 10 messages)
                $delay = floor($count / 10) * 15;
            }

            // Get area name dari database (bukan API)
            $areaName = 'all areas';
            if ($areaId !== 'all') {
                $area = Area::where('id', $areaId)
                    ->where('group_id', $user->group_id)
                    ->first();
                $areaName = $area ? "area {$area->name}" : 'selected area';
            }

            $statusText = $recipientType === 'active' ? 'active' : ($recipientType === 'suspended' ? 'suspended' : 'all');

            return back()->with(
                'success',
                "Broadcast queued to {$count} {$statusText} members in {$areaName}. Messages will be sent gradually."
            );
        } catch (\Throwable $th) {
            Log::error('Broadcast error: ' . $th->getMessage());
            return back()->with('error', 'Failed to queue broadcast: ' . $th->getMessage());
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



}
