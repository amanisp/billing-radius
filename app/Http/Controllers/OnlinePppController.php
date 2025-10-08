<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Yajra\DataTables\DataTables;

class onlinepppController extends Controller
{
    public function index()
    {
        return view('pages.ppp.online');
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $query = DB::connection('radius')->table('radacct as ra')
            ->join('radusergroup as rug', 'ra.username', '=', 'rug.username')
            ->where('rug.groupname', 'mitra_' . $user->group_id) // Filter sesuai mitra
            ->whereNull('ra.acctstoptime') // Hanya user yang masih online
            ->where('ra.acctupdatetime', '>=', now()->subMinutes(120))
            ->select([
                'ra.acctsessionid as session_id',
                'ra.username',
                'ra.acctstarttime as login_time',
                'ra.acctupdatetime as last_update',
                'ra.framedipaddress as ip_address',
                'ra.callingstationid as mac_address',
                'ra.acctinputoctets as upload',
                'ra.acctoutputoctets as download',
                'ra.acctsessiontime as uptime'
            ])
            ->orderBy('ra.acctstarttime', 'desc'); // Urutkan dari login terbaru


        return DataTables::of($query)
            ->addIndexColumn() // Menambahkan nomor urut otomatis
            ->addColumn('session', function ($row) {
                return "<span class='badge bg-primary bg-gradient'><small>{$row->session_id}</small></span>";
            })
            ->addColumn('login_time', function ($row) {
                return "<small>{$row->login_time}</small>";
            })
            ->addColumn('last_update', function ($row) {
                return "<small>{$row->last_update}</small>";
            })
            ->addColumn('ip_mac', function ($row) {
                return "<small>{$row->ip_address}<br>{$row->mac_address}</small>";
            })
            ->addColumn('upload', function ($row) {
                return "<small>" . formatBytes($row->upload) . "</small>";
            })
            ->addColumn('download', function ($row) {
                return "<small>" . formatBytes($row->download) . "</small>";
            })
            ->addColumn('uptime', function ($row) {
                $seconds = $row->uptime;
                $days = floor($seconds / 86400); // 86400 detik = 1 hari
                $time = gmdate("H:i:s", $seconds % 86400); // Sisa waktu dalam format H:i:s

                return "<small>" . ($days > 0 ? "{$days}d " : "") . $time . "</small>";
            })
            ->rawColumns(['session', 'login_time', 'last_update', 'ip_mac', 'upload', 'download', 'uptime']) // Agar HTML bisa dirender di DataTables
            ->make(true);
    }
}


// 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