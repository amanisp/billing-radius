<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\Invoice;
use App\Services\InvoiceService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class InvoiceController extends Controller
{
    protected $invoiceService;

    public function __construct(InvoiceService $invoiceService)
    {
        $this->invoiceService = $invoiceService;
    }

    // GET /api/invoices
    public function index(Request $request)
    {
        try {
            $user = Auth::user();
            $query = Invoice::with(['member', 'connection'])
                ->where('group_id', $user->group_id);

            // 🔍 Search (by invoice number or member name)
            if ($search = $request->get('search')) {
                $query->where(function ($q) use ($search) {
                    $q->where('inv_number', 'like', "%{$search}%")
                        ->orWhereHas('member', function ($m) use ($search) {
                            $m->where('fullname', 'like', "%{$search}%");
                        });
                });
            }

            // 🔄 Sort
            $sortField = $request->get('sort_field', 'created_at');
            $sortDirection = $request->get('sort_direction', 'desc');
            $query->orderBy($sortField, $sortDirection);

            // 📄 Pagination
            $perPage = $request->get('per_page', 10);
            $invoices = $query->paginate($perPage);

            return ResponseFormatter::success($invoices, 'Data invoice berhasil dimuat');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    // POST /api/invoices
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'member_id'           => 'required|exists:members,id',
                'amount'              => 'required|numeric',
                'subscription_period' => 'required|integer|min:1',
                'due_date'            => 'nullable|date',
            ]);

            $invoices = $this->invoiceService->createManualInvoice($validated);

            if (empty($invoices)) {
                throw new \Exception("Invoice gagal dibuat.");
            }

            // Ambil invoice pertama untuk log
            $firstInvoice = $invoices[0];

            ActivityLogController::logCreate([
                'action' => 'create_invoice',
                'inv_number' => $firstInvoice->inv_number,
                'status' => 'success'
            ], 'invoices');

            return ResponseFormatter::success($invoices, 'Invoice berhasil dibuat', 201);
        } catch (\Exception $e) {

            ActivityLogController::logCreateF([
                'action' => 'create_invoice',
                'error' => $e->getMessage()
            ], 'invoices');

            return ResponseFormatter::error(null, $e->getMessage(), 400);
        } catch (\Throwable $th) {

            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }

    // DELETE /api/invoices/{id}
    public function destroy($id)
    {
        try {
            $invoice = Invoice::findOrFail($id);
            $invoice->delete();

            return ResponseFormatter::success(null, 'Invoice berhasil dihapus');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
