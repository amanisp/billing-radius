<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\Area as ModelArea;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        // $isSuperadmin = is_null($user->group_id);
        $data = ModelArea::where('group_id', $user->group_id)->get();

        return view('pages.area', compact('data', 'user'));
    }

    public function store(Request $request)
    {
        try {
            // dd("store area", $request->all());
            $user = Auth::user();


            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('areas')->where(fn($query) => $query->where('group_id', $user->group_id))
                ],
                'area_code' => 'required'
            ]);

            $newArea = ModelArea::create([
                'group_id' => $user->group_id,
                'name' => $request->name,
                'area_code' => $request->area_code
            ]);

            ActivityLogged::dispatch('CREATE', null, $newArea);

            return redirect()->route('area.index')->with('success', 'Data area berhasil disimpan!');
        } catch (\Throwable $th) {
            return redirect()->route('area.index')->with('error', $th->getMessage());
        }
    }

    public function destroy($id)
    {
        $area = ModelArea::where('id', $id)->firstOrFail();
        $deletedData = $area;
        
        $area->delete();

        ActivityLogged::dispatch('DELETE', null, $deletedData);
        return redirect()->route('area.index')->with('success', 'Data area berhasil dihapus!');
    }
}
