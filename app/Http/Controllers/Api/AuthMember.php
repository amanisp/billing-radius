<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Http\Controllers\ActivityLogController;
use App\Models\Connection;
use App\Models\Member;
use Illuminate\Http\Request;

use function Pest\Laravel\json;

class AuthMember extends Controller
{
    public function signIn(Request $request)
    {
        try {
            $request->validate([
                'internet_number' => 'required|string'
            ]);

            $internetNumber = $request->internet_number;

            // Cari connection
            $connection = Connection::where('internet_number', $internetNumber)->first();

            if (!$connection) {
                ActivityLogController::logCreateF([
                    'internet_number' => $internetNumber,
                    'action' => 'member_signin',
                    'error' => 'Data connection tidak ditemukan'
                ], 'members');
                return ResponseFormatter::error(null, 'Data connection tidak ditemukan', 404);
            }

            // Cari member
            $member = Member::where('connection_id', $connection->id)->first();

            if (!$member) {
                ActivityLogController::logCreateF([
                    'internet_number' => $internetNumber,
                    'connection_id' => $connection->id,
                    'action' => 'member_signin',
                    'error' => 'Member tidak ditemukan'
                ], 'members');
                return ResponseFormatter::error(null, 'Member tidak ditemukan', 404);
            }

            ActivityLogController::logCreate([
                'internet_number' => $internetNumber,
                'member_id' => $member->id,
                'action' => 'member_signin',
                'status' => 'success'
            ], 'members');

            // Return response
            $responseData = [
                'token_type' => 'bearer',
            ];

            return ResponseFormatter::success($responseData, 'Authentication Success');
        } catch (\Throwable $th) {
            ActivityLogController::logCreateF([
                'internet_number' => $request->internet_number ?? null,
                'action' => 'member_signin',
                'error' => $th->getMessage()
            ], 'members');
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
