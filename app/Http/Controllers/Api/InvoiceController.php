<?php

namespace App\Http\Controllers\Api;

use App\Models\InvoiceHomepass;
use App\Models\Member;
use App\Models\PaymentDetail;
use App\Models\User;
use App\Jobs\SendInvoicePaymentNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class InvoiceController
{
    /**
     * Get authenticated user
     */
    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) {
            return $user;
        }

        $id = Auth::id();
        if ($id) {
            return User::find($id);
        }

        return null;
    }

    /**
     * Get API Key dari group
     */
    private function getApiKey($groupId)
    {
        $settings = DB::table('global_settings')
            ->where('group_id', $groupId)
            ->first();

        return $settings->whatsapp_api_key ?? null;
    }

    /**
     * LIST ALL INVOICES
     * GET /api/v1/invoice
     */
    public function index(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Build base query
            $query = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection.profile',
                'member.connection.area',
                'payer'
            ])->where('group_id', $user->group_id);

            // Status filter
            if ($request->filled('status')) {
                $query->where('status', $request->status);
            }

            // Month/Year filter
            if ($request->filled('month') && $request->filled('year')) {
                $month = $request->month;
                $year = $request->year;
                $startOfMonth = Carbon::createFromDate($year, $month, 1)->startOfMonth();
                $endOfMonth = Carbon::createFromDate($year, $month, 1)->endOfMonth();

                $query->whereBetween('start_date', [
                    $startOfMonth,
                    $endOfMonth
                ]);
            }

            // Search
            if ($request->filled('search')) {
                $search = $request->search;
                $query->where(function ($q) use ($search) {
                    $q->where('inv_number', 'like', "%$search%")
                        ->orWhereHas('member', function ($q2) use ($search) {
                            $q2->where('full_name', 'like', "%$search%");
                        });
                });
            }

            // Sorting & Pagination
            $invoices = $query
                ->orderBy($request->get('sort_field', 'created_at'), $request->get('sort_direction', 'desc'))
                ->paginate($request->get('per_page', 15));

            return response()->json([
                'status' => 'success',
                'data' => $invoices->items(),
                'meta' => [
                    'current_page' => $invoices->currentPage(),
                    'per_page' => $invoices->perPage(),
                    'total' => $invoices->total(),
                    'last_page' => $invoices->lastPage(),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET INVOICE STATISTICS
     * GET /api/v1/invoice/stats
     */
    public function stats(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $now = Carbon::now();
            $currentMonth = $now->month;
            $currentYear = $now->year;

            // Base query
            $baseQuery = InvoiceHomepass::where('group_id', $user->group_id);

            // Statistics
            $stats = [
                'total_invoices' => (clone $baseQuery)->count(),
                'total_amount' => (clone $baseQuery)->sum('amount') ?? 0,
                'paid' => [
                    'count' => (clone $baseQuery)->where('status', 'paid')->count(),
                    'amount' => (clone $baseQuery)->where('status', 'paid')->sum('amount') ?? 0,
                ],
                'unpaid' => [
                    'count' => (clone $baseQuery)->where('status', 'unpaid')->count(),
                    'amount' => (clone $baseQuery)->where('status', 'unpaid')->sum('amount') ?? 0,
                ],
                'overdue' => [
                    'count' => (clone $baseQuery)->where('status', 'unpaid')
                        ->where('due_date', '<', $now)->count(),
                    'amount' => (clone $baseQuery)->where('status', 'unpaid')
                        ->where('due_date', '<', $now)->sum('amount') ?? 0,
                ],
                'whatsapp_status' => [
                    'sent' => (clone $baseQuery)->where('wa_status', 'sent')->count(),
                    'pending' => (clone $baseQuery)->where('wa_status', 'pending')->count(),
                    'failed' => (clone $baseQuery)->where('wa_status', 'failed')->count(),
                    'failed_permanently' => (clone $baseQuery)->where('wa_status', 'failed_permanently')->count(),
                ],
            ];

            return response()->json([
                'status' => 'success',
                'data' => $stats
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * GET SINGLE INVOICE
     * GET /api/v1/invoice/{id}
     */
    public function show($id, Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $invoice = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection.profile',
                'payer'
            ])->where('group_id', $user->group_id)
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => $invoice
            ], 200);

        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Invoice not found'
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * PAY INVOICE + AUTO SEND WHATSAPP
     * POST /api/v1/invoice/pay
     *
     * Request body:
     * {
     *   "invoice_id": 2,
     *   "payment_method": "bank_transfer"
     * }
     */
    public function payInvoice(Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Validate input
            $validated = $request->validate([
                'invoice_id' => 'required|integer|exists:invoice_homepasses,id',
                'payment_method' => 'required|in:bank_transfer,cash,e_wallet',
            ]);

            DB::beginTransaction();

            // Get invoice
            $invoice = InvoiceHomepass::with([
                'member.paymentDetail',
                'member.connection'
            ])->where('group_id', $user->group_id)
                ->findOrFail($validated['invoice_id']);

            // Check if already paid
            if ($invoice->status === 'paid') {
                DB::rollBack();
                return response()->json([
                    'status' => 'error',
                    'message' => 'Invoice sudah dibayar'
                ], 400);
            }

            // Update invoice
            $invoice->update([
                'status' => 'paid',
                'payment_method' => $validated['payment_method'],
                'payer_id' => $user->id,
                'paid_at' => Carbon::now(),
                'wa_status' => 'pending', // Mark for WhatsApp sending
            ]);

            // Update payment detail
            if ($invoice->member && $invoice->member->paymentDetail) {
                $invoice->member->paymentDetail->update([
                    'last_invoice' => $invoice->due_date,
                ]);
            }

            DB::commit();

            // Queue WhatsApp notification (async, non-blocking)
            $apiKey = $this->getApiKey($user->group_id);
            if ($apiKey) {
                SendInvoicePaymentNotification::dispatch($invoice, $apiKey);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Invoice dibayar, notifikasi WhatsApp sedang diproses',
                'data' => $invoice->refresh(),
            ], 200);

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => 'Validation error',
                'errors' => $e->errors()
            ], 422);
        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * RESEND WHATSAPP NOTIFICATION
     * POST /api/v1/invoice/{id}/resend-wa
     */
    public function resendWhatsappNotification($id, Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $invoice = InvoiceHomepass::where('group_id', $user->group_id)
                ->findOrFail($id);

            // Reset status
            $invoice->update([
                'wa_status' => 'pending',
                'wa_error_message' => null,
            ]);

            // Requeue
            $apiKey = $this->getApiKey($user->group_id);
            if ($apiKey) {
                SendInvoicePaymentNotification::dispatch($invoice, $apiKey);
            }

            return response()->json([
                'status' => 'success',
                'message' => 'Notifikasi WhatsApp sedang dikirim ulang',
                'data' => $invoice->refresh(),
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * CHECK WHATSAPP STATUS
     * GET /api/v1/invoice/{id}/wa-status
     */
    public function checkWhatsappStatus($id, Request $request)
    {
        try {
            $user = $this->getAuthUser();
            if (!$user) {
                return response()->json([
                    'message' => 'Unauthorized'
                ], 401);
            }

            $invoice = InvoiceHomepass::where('group_id', $user->group_id)
                ->findOrFail($id);

            return response()->json([
                'status' => 'success',
                'data' => [
                    'wa_status' => $invoice->wa_status,
                    'wa_sent_at' => $invoice->wa_sent_at,
                    'wa_error_message' => $invoice->wa_error_message,
                    'is_sent' => $invoice->wa_status === 'sent',
                    'is_failed' => in_array($invoice->wa_status, ['failed', 'failed_permanently']),
                ]
            ], 200);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => $e->getMessage()
            ], 500);
        }
    }
}
