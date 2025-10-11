<?php

namespace App\Http\Controllers;

use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
        $query = Member::where('group_id', $user->group_id)
            ->with(['paymentDetail', 'connection'])
            ->orderBy('created_at', 'desc') // urutkan terbaru dulu
            ->get();

        return DataTables::of($query)
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
                data-fullname="' . $account->fullname . '" 
                data-internet="' . $account->connection->internet_number . '" 
                data-username="' . $account->connection->username . '" 
                data-price="' . $account->connection->profile->price . '"
                data-item="' . $account->connection->profile->name . '"
                data-vats="' . $account->paymentDetail->ppn . '"
                data-discounts="' . $account->paymentDetail->discount . '"
                data-active="' . $account->paymentDetail->active_date . '" 
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
