<?php

namespace App\Http\Controllers;

use App\Models\OpticalDist;
use Illuminate\Support\Facades\Auth;

class MapsController extends Controller
{
    public function index()
    {
        $user = Auth::user();

        // Ambil OpticalDist yang group_id-nya NULL atau sesuai dengan company (jika dibutuhkan)
        $optical = OpticalDist::where('group_id', $user->group_id)->get();

        return view('pages.maps', [
            'optical' => $optical
        ]);
    }
}
