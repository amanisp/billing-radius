<?php

namespace App\Http\Controllers;

use App\Models\Area;
use App\Models\Eos;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Yajra\DataTables\Facades\DataTables;

class eosController extends Controller
{
    public function index()
    {
        return view('pages.eos.index');
    }

    public function getData()
    {
        $query = Eos::query()->with('area')
            ->orderBy('created_at', 'desc');


        return DataTables::eloquent($query)
            ->addIndexColumn()
            ->addColumn('action', function ($account) {

                $detailUrl = route('eos.show', $account->id);

                $detailBtn = '
            <a href="' . $detailUrl . '" 
               class="btn btn-outline-primary btn-sm ms-1"
               title="Detail EOS">
               <i class="fa-solid fa-address-card"></i>
            </a>';

                $deleteBtn = '
            <button type="button"
                class="btn btn-outline-danger btn-sm ms-1 btn-delete"
                data-id="' . $account->id . '"
                title="Hapus EOS">
                <i class="fa-solid fa-trash"></i>
            </button>';

                return '<div class="btn-group">' . $detailBtn . $deleteBtn . '</div>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }


    public function create()
    {
        $area = Area::get();

        return view('pages.eos.store', compact('area'));
    }

    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'fullname'         => 'required|string|max:255|unique:engginer_on_site,fullname',
                'address' => 'required|string|max:255|unique:engginer_on_site,phone_number',
                'email'        => 'unique:engginer_on_site,email',
                'nik'      => 'required|string|max:255',
                'npwp'       => 'max:18',
                'phone_number'  => 'required',
                'customer_number'  => 'required',
                'area_id'     => 'required',
                'nip'     => 'required',
                'register' => 'required',
                'payment' => 'required'
            ]);


            $data = Eos::create($validated);
            return redirect()->route('eos.index')->with('success', 'Data EOS berhasil disimpan!');
        } catch (\Throwable $th) {
            return redirect()->back()->with('error', $th->getMessage());
        }
    }

    public function show(Eos $eos)
    {
        $areas = Area::get();

        return view('pages.eos.show', compact('eos', 'areas'));
    }
}
