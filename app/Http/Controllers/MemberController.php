<?php

namespace App\Http\Controllers;

use App\Models\Member;
use App\Models\PaymentDetail;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;

class MemberController extends Controller
{
    public function index()
    {
        return view('pages.member.index');
    }

    public function getData()
    {
        $user = Auth::user();

        $query = Member::with(['paymentDetail', 'connection.profile', 'connection.area'])
            ->orderBy('created_at', 'desc');

        // Filter berdasarkan role
        if (in_array($user->role, ['mitra', 'kasir'])) {
            $query->where('group_id', $user->group_id);
        } elseif ($user->role === 'teknisi') {
            $assignedAreaIds = DB::table('technician_areas')
                ->where('user_id', $user->id)
                ->pluck('area_id')
                ->toArray();

            if (empty($assignedAreaIds)) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('connection', function ($q) use ($assignedAreaIds) {
                    $q->whereIn('area_id', $assignedAreaIds);
                });
            }
        } else {
            $query->where('group_id', $user->group_id);
        }

        $data = $query->get();

        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('area_name', function ($account) {
                // Ambil area name dari relasi connection
                return $account->connection && $account->connection->area
                    ? e($account->connection->area->name)
                    : '<span class="text-muted">-</span>';
            })
            ->addColumn('action', function ($account) {
                // Safely extract payment detail values
                $pd = $account->paymentDetail;

                $activeDate = $pd && $pd->active_date
                    ? Carbon::parse($pd->active_date)->format('Y-m-d')
                    : '';

                $lastInvoice = $pd && $pd->last_invoice
                    ? Carbon::parse($pd->last_invoice)->format('Y-m-d')
                    : '';

                $editBtn = '
                <button type="button" class="btn btn-outline-warning btn-edit btn-sm ms-1"
                    data-id="' . $account->id . '"
                    data-fullname="' . e($account->fullname) . '"
                    data-phone_number="' . e($account->phone_number) . '"
                    data-email="' . e($account->email) . '"
                    data-id_card="' . e($account->id_card) . '"
                    data-address="' . e($account->address) . '"
                ><i class="fa-solid fa-pencil"></i></button>';

                $paymentBtn = '
                <button type="button" class="btn btn-outline-info btn-payment btn-sm ms-1"
                    data-id="' . $account->id . '"
                    data-fullname="' . e($account->fullname) . '"
                    data-payment-id="' . e(optional($pd)->id) . '"
                    data-payment-type="' . e(optional($pd)->payment_type) . '"
                    data-billing-period="' . e(optional($pd)->billing_period ?? 1) . '"
                    data-active-date="' . e($activeDate) . '"
                    data-amount="' . e(optional($pd)->amount ?? 0) . '"
                    data-discount="' . e(optional($pd)->discount ?? 0) . '"
                    data-ppn="' . e(optional($pd)->ppn ?? 0) . '"
                    data-last-invoice="' . e($lastInvoice) . '"
                ><i class="fa-solid fa-money-bill"></i></button>';

                return '<div class="btn-group">' . $editBtn . $paymentBtn . '</div>';
            })
            ->addColumn('actionCreate', function ($account) {
                return '<button
                class="btn btn-sm btn-outline-primary btnCreateInvoice"
                data-id="' . $account->id . '"
                data-fullname="' . e($account->fullname) . '"
                data-internet="' . e(optional($account->connection)->internet_number) . '"
                data-username="' . e(optional($account->connection)->username) . '"
                data-price="' . e(optional(optional($account->connection)->profile)->price) . '"
                data-item="' . e(optional(optional($account->connection)->profile)->name) . '"
                data-vats="' . e(optional($account->paymentDetail)->ppn) . '"
                data-discounts="' . e(optional($account->paymentDetail)->discount) . '"
                data-active="' . e(optional($account->paymentDetail)->active_date) . '"
            >Create Invoice</button>';
            })
            ->addColumn('service_active', function ($account) {
                return $account->serviceActive()->count();
            })
            ->rawColumns(['action', 'actionCreate', 'area_name'])
            ->make(true);
    }


    public function update(Request $request, $id)
    {
        $user = Auth::user();
        $member = Member::where('id', $id)->firstOrFail();
        $oldData = $member->toArray();
        $request->validate([
            'fullname' => 'required',
            'phone_number' => 'nullable|string|min:9',
            'email' => 'nullable|string',
            'id_card' => 'nullable|string',
            'address' => 'nullable|string',
        ]);
        $member->update($request->only(['fullname', 'phone_number', 'email', 'id_card', 'address']));
        // Logging
        ActivityLogController::logUpdate(
            $oldData,
            'members',
            $member
        );

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Diedit!',
        ]);
    }
    public function updatePaymentDetail(Request $request, $id)
    {
        $validated = $request->validate([
            'payment_type'   => 'required|in:prabayar,pascabayar',
            'billing_period' => 'required|integer|min:1',
            'active_date'    => 'required|date',
            'amount'         => 'required|numeric|min:0',
            'discount'       => 'nullable|numeric|min:0|max:100',
            'ppn'            => 'nullable|numeric|min:0|max:100',
        ]);

        $member = Member::findOrFail($id);

        $payload = [
            'group_id'       => Auth::user()->group_id,
            'payment_type'   => $validated['payment_type'],
            'billing_period' => (int) $validated['billing_period'],
            'active_date'    => $validated['active_date'],
            'amount'         => (float) $validated['amount'],
            'discount'       => isset($validated['discount']) ? (float) $validated['discount'] : 0,
            'ppn'            => isset($validated['ppn']) ? (float) $validated['ppn'] : 0,
        ];

        if ($member->paymentDetail) {
            $oldData = $member->paymentDetail->toArray();
            $member->paymentDetail->update($payload);

            ActivityLogController::logUpdate(
                $oldData,
                'payment_details',
                $member->paymentDetail
            );
        } else {
            $paymentDetail = PaymentDetail::create($payload);
            // RELASI: simpan ke member
            $member->update(['payment_detail_id' => $paymentDetail->id]);

            ActivityLogController::logCreate('payment_details', $paymentDetail);
        }

        return response()->json([
            'success' => true,
            'message' => 'Payment Detail Berhasil Disimpan!',
        ]);
    }
}
