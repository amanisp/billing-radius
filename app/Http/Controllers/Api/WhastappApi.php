<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Groups;
use App\Models\User;
use App\Services\WhatsappService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class WhastappApi extends Controller
{
    protected $wa;

    public function __construct(WhatsappService $wa)
    {
        $this->wa = $wa;
    }

    private function getAuthUser()
    {
        $user = Auth::user();
        if ($user instanceof User) return $user;

        $id = Auth::id();
        if ($id) return User::find($id);

        return null;
    }

    public function index($sessionId)
    {
        try {
            $user = $this->getAuthUser();
            $groups = Groups::find($user->group_id);

            if (!$user) {
                return response()->json(['message' => 'Unauthorized'], 401);
            }

            $result = $this->wa->checkSession($sessionId);
            $data = [$user, $groups];
            return ResponseFormatter::success($data, 'Signup Berhasil', 200);
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 200);
        }
    }
}
