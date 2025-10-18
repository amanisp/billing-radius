<?php

namespace App\Http\Controllers;

use App\Events\ActivityLogged;
use App\Models\Area as ModelArea;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class AreaController extends Controller
{
    /**
     * Ensure we always have a User model instance from the current auth.
     * This handles cases where Auth::user() may not be an Eloquent model.
     */
    public function getAreaList()
    {
        $user = Auth::user();
        $query = ModelArea::select('id', 'name');

        if ($user->role === 'mitra') {
            $query->where('group_id', $user->group_id);
        }

        return response()->json($query->get());
    }

    private function getAuthUser()
    {
        $user = Auth::user();

        if ($user instanceof User) {
            return $user;
        }

        // Fallback: try to resolve by id
        $id = Auth::id();
        if ($id) {
            return User::find($id);
        }

        return null;
    }

   public function index()
{
    $user = $this->getAuthUser();

    if (!$user) {
        return redirect()->route('login')->with('error', 'Session expired. Please login again.');
    }

    if ($user->role === 'teknisi') {
        // fetch assigned areas via relation if available, otherwise fallback to pivot table
        if (method_exists($user, 'assignedAreas')) {
            $assignedAreaIds = $user->assignedAreas()->pluck('areas.id')->toArray();
        } else {
            $assignedAreaIds = DB::table('technician_areas')
                ->where('user_id', $user->id)
                ->pluck('area_id')
                ->toArray();
        }

        if (empty($assignedAreaIds)) {
            $data = collect(); // Empty collection
        } else {
            $data = ModelArea::whereIn('id', $assignedAreaIds)
                ->with(['opticals', 'connection'])
                ->get();
        }
    } else {
        $data = ModelArea::where('group_id', $user->group_id)
            ->with(['opticals', 'connection'])
            ->get();
    }

    $technicians = User::where('group_id', $user->group_id)
        ->where('role', 'teknisi')
        ->get();

    return view('pages.area', compact('data', 'user', 'technicians'));
}
    public function store(Request $request)
    {
        try {
            $user = $this->getAuthUser();

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

    public function assignTechnician(Request $request)
    {
        try {
            $request->validate([
                'area_id' => 'required|exists:areas,id',
                'technician_ids' => 'required|array',
                'technician_ids.*' => 'exists:users,id'
            ]);

            $user = $this->getAuthUser();

            $area = ModelArea::findOrFail($request->area_id);

            // Verify area belongs to same group as authenticated user
            if ($area->group_id !== $user->group_id) {
                return back()->with('error', 'Area tidak ditemukan!');
            }

            // Sync technicians (will remove old assignments and add new ones)
            $area->assignedTechnicians()->sync($request->technician_ids);

            ActivityLogged::dispatch('UPDATE', null, [
                'action' => 'assign_technicians',
                'area' => $area->name,
                'technicians' => User::whereIn('id', $request->technician_ids)->pluck('name')->toArray()
            ]);

            return back()->with('success', 'Teknisi berhasil di-assign ke area!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
        }
    }

    public function unassignTechnician(Request $request)
    {
        try {
            $request->validate([
                'area_id' => 'required|exists:areas,id',
                'technician_id' => 'required|exists:users,id'
            ]);

            $user = $this->getAuthUser();

            $area = ModelArea::findOrFail($request->area_id);

            if ($area->group_id !== $user->group_id) {
                return back()->with('error', 'Area tidak ditemukan!');
            }

            $area->assignedTechnicians()->detach($request->technician_id);

            return back()->with('success', 'Teknisi berhasil dihapus dari area!');
        } catch (\Throwable $th) {
            return back()->with('error', $th->getMessage());
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
