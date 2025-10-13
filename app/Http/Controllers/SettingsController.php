<?php

namespace App\Http\Controllers;

use App\Models\GlobalSettings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SettingsController extends Controller
{
    public function settingPPP()
    {
        $user = Auth::user();
        $isolir_mode = GlobalSettings::where('group_id', $user->group_id)->first();
        return view('pages.ppp.setting', compact('user', 'isolir_mode'));
    }

    public function UpdateSetPPP(Request $request, $id)
    {
        $data = $request->has('isolir_mode');

        globalSettings::updateOrInsert(
            ['id' => $id],
            ['isolir_mode' => $data]
        );

        return back()->with('success', 'Pengaturan diperbarui!');
    }

    public function BillingSettings(Request $request, $id)
    {
        GlobalSettings::updateOrInsert(
            ['group_id' => $id], // Search condition
            [
                'invoice_generate_days' => $request->invoice_generate_days,
                'notification_days' => $request->notification_days,
                'due_date_pascabayar' => $request->due_date_pascabayar,
                'footer' => $request->footer,
                'isolir_time' => $request->isolir_time,
            ]
        );

        return back()->with('success', 'Pengaturan diperbarui!');
    }
}
