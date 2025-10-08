<?php

namespace App\Http\Controllers;

use App\Models\Profiles;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Yajra\DataTables\Facades\DataTables;

class PProfileController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        return view('pages.ppp.profile', compact('user'));
    }

    public function getData(Request $request)
    {
        $user = Auth::user();
        $profiles = Profiles::where('group_id', $user->group_id)->select(['*']);

        return DataTables::of($profiles)
            ->addIndexColumn()
            ->editColumn('price', function($profile) {
                return 'Rp ' . number_format($profile->price, 0, ',', '.');
            })
            ->addColumn('rate_limit', function($profile) {
                return $profile->rate_rx . '/' . $profile->rate_tx . ' ' .
                       $profile->burst_rx . '/' . $profile->burst_tx . ' ' .
                       $profile->threshold_rx . '/' . $profile->threshold_tx . ' ' .
                       $profile->time_rx . '/' . $profile->time_tx . ' ' . $profile->priority;
            })
            ->addColumn('action', function($profile) {
                return '<div class="btn-group">
                            <button id="btn-edit"
                                    data-id="' . $profile->id . '"
                                    data-name="' . $profile->name . '"
                                    data-price="' . $profile->price . '"
                                    data-raterx="' . $profile->rate_rx . '"
                                    data-ratetx="' . $profile->rate_tx . '"
                                    data-burstrx="' . $profile->burst_rx . '"
                                    data-bursttx="' . $profile->burst_tx . '"
                                    data-thresholdrx="' . $profile->threshold_rx . '"
                                    data-thresholdtx="' . $profile->threshold_tx . '"
                                    data-timerx="' . $profile->time_rx . '"
                                    data-timetx="' . $profile->time_tx . '"
                                    data-priority="' . $profile->priority . '"
                                    class="btn btn-outline-warning btn-sm">
                                <i class="fa-solid fa-pencil"></i>
                            </button>
                            <button id="btn-delete"
                                    data-id="' . $profile->id . '"
                                    data-name="' . $profile->name . '"
                                    class="btn btn-outline-danger btn-sm ms-1">
                                <i class="fa-solid fa-trash"></i>
                            </button>
                        </div>';
            })
            ->rawColumns(['action'])
            ->make(true);
    }

    public function store(Request $request)
    {
        try {
            $user = Auth::user();

            $request->validate([
                'name' => [
                    'required',
                    'string',
                    'max:255',
                    Rule::unique('profiles')->where(fn($query) => $query->where('group_id', $user->group_id))
                ],
                'rate_rx' => 'nullable|string',
                'rate_tx' => 'nullable|string',
                'burst_rx' => 'nullable|string',
                'burst_tx' => 'nullable|string',
                'threshold_rx' => 'nullable|string',
                'threshold_tx' => 'nullable|string',
                'time_rx' => 'nullable|string',
                'time_tx' => 'nullable|string',
                'priority' => 'nullable|string',
            ]);

            $ppoe_profiles = Profiles::create([
                'name' => $request->name,
                'group_id' => $user->group_id,
                "price" => (int) str_replace('.', '', $request->price),
                "rate_rx" => $request->rate_rx,
                "rate_tx" => $request->rate_tx,
                "burst_rx" => $request->burst_rx ? $request->burst_rx : '0',
                "burst_tx" => $request->burst_tx ? $request->burst_tx : '0',
                "threshold_rx" => $request->threshold_rx ? $request->threshold_rx : '0',
                "threshold_tx" => $request->threshold_tx ? $request->threshold_tx : '0',
                "time_rx" => $request->time_rx ? $request->time_rx : '0',
                "time_tx" => $request->time_tx ? $request->time_tx : '0',
                "priority" => $request->priority
            ]);

            DB::connection('radius')->table('radgroupreply')->insert([
                'groupname'  => $request->name . '-' . $user->group_id,
                'attribute' => 'Mikrotik-Rate-Limit',
                'op'        => ':=',
                'value'     => "{$ppoe_profiles->rate_rx}/{$ppoe_profiles->rate_tx} {$ppoe_profiles->burst_rx}/{$ppoe_profiles->burst_tx} {$ppoe_profiles->threshold_rx}/{$ppoe_profiles->threshold_tx} {$ppoe_profiles->time_rx}/{$ppoe_profiles->time_tx} {$ppoe_profiles->priority}",
                'group_id'  => $user->group_id
            ]);

            ActivityLogController::logCreate($ppoe_profiles);

            return response()->json([
                'success' => true,
                'message' => 'Data Profile berhasil disimpan!'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function update(Request $request, $id)
    {
        try {
            $user = Auth::user();
            $profiles = Profiles::where('id', $id)->firstOrFail();
            $oldData = $profiles->toArray();

            $request->validate([
                'name'        => 'required',
                'rate_rx' => 'nullable|string',
                'rate_tx' => 'nullable|string',
                'burst_rx' => 'nullable|string',
                'burst_tx' => 'nullable|string',
                'threshold_rx' => 'nullable|string',
                'threshold_tx' => 'nullable|string',
                'time_rx' => 'nullable|string',
                'time_tx' => 'nullable|string',
                'priority' => 'nullable|string',
            ]);

            $profiles->update([
                'name'          => $request->name,
                'price'         => (int) str_replace('.', '', $request->price),
                'rate_rx'       => $request->rate_rx,
                'rate_tx'       => $request->rate_tx,
                'burst_rx'      => $request->burst_rx ?? '0',
                'burst_tx'      => $request->burst_tx ?? '0',
                'threshold_rx'  => $request->threshold_rx ?? '0',
                'threshold_tx'  => $request->threshold_tx ?? '0',
                'time_rx'       => $request->time_rx ?? '0',
                'time_tx'       => $request->time_tx ?? '0',
                'priority'      => $request->priority,
            ]);

            DB::connection('radius')->table('radgroupreply')
                ->where('groupname', $oldData['name'] . '-' . $user->group_id)
                ->where('group_id', $user->group_id)
                ->update([
                    'groupname' => $request->name . '-' . $user->group_id,
                    'attribute' => 'Mikrotik-Rate-Limit',
                    'op'        => ':=',
                    'value'     => "{$request->rate_rx}/{$request->rate_tx} {$request->burst_rx}/{$request->burst_tx} {$request->threshold_rx}/{$request->threshold_tx} {$request->time_rx}/{$request->time_tx} {$request->priority}",
                ]);

            ActivityLogController::logUpdate($oldData, 'profiles', $profiles);

            return response()->json([
                'success' => true,
                'message' => 'Data Profile berhasil diperbarui.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'success' => false,
                'errors' => $e->errors()
            ], 422);
        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }

    public function destroy($id)
    {
        try {
            $profile = Profiles::where('id', $id)->firstOrFail();

            DB::connection('radius')->table('radgroupreply')
                ->where('groupname', $profile->name . '-' . $profile->group_id)
                ->where('group_id', $profile->group_id)
                ->delete();

            $profile->delete();

            ActivityLogController::logDelete($profile);

            return response()->json([
                'success' => true,
                'message' => 'Data Profile berhasil dihapus.'
            ]);

        } catch (\Throwable $th) {
            return response()->json([
                'success' => false,
                'message' => $th->getMessage()
            ], 500);
        }
    }
}
