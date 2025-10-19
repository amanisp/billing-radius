<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class MemberController extends Controller
{
    public function index()
    {
        return view('pages.member.index');
    }

    public function getData()
    {
        $user = Auth::user();

        // Base query
        $query = Member::with(['paymentDetail', 'connection.profile', 'connection.area'])
            ->orderBy('created_at', 'desc');

        // 🔹 Filter berdasarkan role
        if (in_array($user->role, ['mitra', 'kasir'])) {
            // Mitra & kasir → lihat semua member dalam grup yang sama
            $query->where('group_id', $user->group_id);
        } elseif ($user->role === 'teknisi') {
            // Teknisi → hanya lihat member dari area yang di-assign ke dia
            $assignedAreaIds = DB::table('technician_areas')
                ->where('user_id', $user->id)
                ->pluck('area_id')
                ->toArray();

            if (empty($assignedAreaIds)) {
                // Tidak punya area → jangan tampilkan data
                $query->whereRaw('1 = 0');
            } else {
                $query->whereHas('connection', function ($q) use ($assignedAreaIds) {
                    $q->whereIn('area_id', $assignedAreaIds);
                });
            }
        } else {
            // Role lain (superadmin, admin, dll) → default filter group_id
            $query->where('group_id', $user->group_id);
        }

        // 🔹 Eksekusi query
        $data = $query->get();

        // 🔹 Return ke DataTables
        return DataTables::of($data)
            ->addIndexColumn()
            ->addColumn('action', function ($account) {
                return '
                <div class="btn-group">
                    <button type="button" class="btn btn-outline-warning btn-edit btn-sm ms-1"
                        data-id="' . $account->id . '"
                        data-fullname="' . e($account->fullname) . '"
                        data-phone_number="' . e($account->phone_number) . '"
                        data-email="' . e($account->email) . '"
                        data-id_card="' . e($account->id_card) . '"
                        data-address="' . e($account->address) . '"
                    ><i class="fa-solid fa-pencil"></i></button>
                </div>
            ';
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
            >
                Create Invoice
            </button>';
            })
            ->addColumn('service_active', function ($account) {
                return $account->serviceActive()->count();
            })
            ->rawColumns(['action', 'actionCreate'])
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
}
