<?php

namespace App\Http\Controllers;

use App\Mail\MailLogin;
use App\Models\Area;
use App\Models\GlobalSettings;
use App\Models\Mitra;
use App\Models\OpticalDist;
use App\Models\Transaction;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Yajra\DataTables\DataTables;

class MitraController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $area = Area::whereNull('group_id')->get();
        $pop = OpticalDist::whereNull('group_id')->get();
        return view('pages.super.mitra.index', compact('area', 'pop'));
    }

    public function getData()
    {
        $query = Mitra::with(['area', 'pop'])->get();

        return DataTables::of($query)
            ->addIndexColumn() // Menambahkan kolom index otomatis
            ->addColumn('name', function ($account) {
                return $account->name;
            })
            ->addColumn('phone', function ($account) {
                return  $account->phone_number;
            })
            ->addColumn('email', function ($account) {
                return  $account->email;
            })
            ->addColumn('area', function ($account) {
                return  $account->area->name;
            })
            ->addColumn('pop', function ($account) {
                return  $account->pop->name;
            })
            ->addColumn('segmentasi', function ($account) {
                $icon = ($account->segmentasi === 'C')
                    ? '<span><i class="fa-solid fa-building"></i> Corp</span>'
                    : '<span><i class="fa-solid fa-users"></i> Mitra</span>';

                $badgeClass = ($account->segmentasi === 'C') ? 'bg-success' : 'bg-warning';

                return '<span class="badge ' . $badgeClass . '">' . $icon . ' </span>';
            })
            ->addColumn('action', function ($account) {
                return '<div class="d-flex gap-2">
                            <a href="/mitra/show/' . $account->id . '" class="btn btn-primary btn-sm rounded-circle text-white">
                                <i class="fa-solid fa-eye"></i>
                            </a>
                                <button id="btn-delete" data-name="' . $account->name . '" data-id="' . $account->id . '" class="btn btn-danger btn-sm rounded-circle">
                                    <i class="fa-solid fa-trash"></i>
                                </button>
                        </div>';
            })
            ->rawColumns(['action', 'segmentasi']) // Agar HTML di-render di DataTables
            ->make(true);
    }


    public function show(Request $request, $id)
    {
        $data = Mitra::findOrFail($id)->first();
        $optical = OpticalDist::where('group_id', $id);
        // return dd($data);
        return view('pages.super.mitra.profile', compact(['data', 'optical']));
    }


    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name'         => 'required|string|max:255|unique:users,name|unique:mitras,name',
                'phone_number' => 'required|string|max:255|unique:users,phone_number|unique:mitras,phone_number',
                'email'        => 'required|string|max:255|unique:users,email|unique:mitras,email',
                'area_id'      => 'required|string|max:255',
                'pop_id'       => 'required|string|max:255',
                'active_date'  => 'required',
                'transmitter'  => 'required',
                'nik'     => 'required|unique:mitras,nik',
                'address'     => 'required|min:16',
                'segmentasi' => 'required'
            ]);

            $nomor_pelanggan = generateCustomerNumber($validated['segmentasi'], $validated['area_id']);


            $data = [
                'name'        => $validated['name'],
                'email'       => $validated['email'],
                'phone_number' => $validated['phone_number'],
                'area_id'     => $validated['area_id'],
                'pop_id'      => $validated['pop_id'],
                'transmitter' => $validated['transmitter'],
                'active_date' => $validated['active_date'],
                'address'     => $validated['address'],
                'nik'         => $validated['nik'],
                'segmentasi' => $validated['segmentasi'],
                'ktpImg'      => '',
                'nomor_pelanggan'  => $nomor_pelanggan,
                'capacity'    => $request->capacity ? $request->capacity : 0,
                'price'       => (int) str_replace('.', '', $request->price),
                'ppn'         => isset($request->ppn) && $request->ppn === 'on',
                'bhpuso'      => isset($request->bhpuso) && $request->bhpuso === 'on',
                'kso'         => isset($request->kso) && $request->kso === 'on',
            ];

            if ($validated['segmentasi'] === 'C') {
                Mitra::create($data);
            } else {
                $mitra = Mitra::create($data);
                $password =  substr(bin2hex(random_bytes(4)), 0, 8);

                User::create([
                    'name'        => $validated['name'],
                    'email'       => $validated['email'],
                    'phone_number' => $validated['phone_number'], // Mutator akan memformat nomor ini
                    'role'        => 'mitra',
                    'username'    => $validated['email'],
                    'password'    => Hash::make($password),
                    'group_id'  => $mitra->id,
                ]);

                $users = [
                    'name'        => $validated['name'],
                    'email'       => $validated['email'],
                    'username'    => $validated['email'],
                    'password' => $password
                ];
                Mail::to($mitra->email)->send(new MailLogin($users));

                GlobalSettings::create([
                    'xendit_balance' => 0,
                    'isolir_time' => '00:00:00',
                    'invoice_generate_days' => 7,
                    'notification_days' => 1,
                    'isolir_after_exp' => 1,
                    'due_date_pascabayar' => '20',
                    'group_id' => $mitra->id
                ]);
            }

            return back()->with('success', 'Data pelanggan berhasil disimpan!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    public function destroy($id)
    {
        $data = Mitra::where('id', $id)->firstOrFail();

        // Hapus data
        $data->delete();

        return response()->json([
            'success' => true,
            'message' => 'Data Berhasil Dihapus!',
        ]);
    }
}
