<?php

namespace App\Http\Controllers\Api;

use App\Helpers\ResponseFormatter;
use App\Http\Controllers\Controller;
use App\Models\Connection;
use App\Models\Member;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

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
                return ResponseFormatter::error(null, 'Data connection tidak ditemukan', 404);
            }

            // Cari member
            $member = Member::where('connection_id', $connection->id)->first();

            if (!$member) {
                return ResponseFormatter::error(null, 'Member tidak ditemukan', 404);
            }

            // Buat JWT token untuk member
            $token = JWTAuth::fromUser($member);

            // Return response
            $responseData = [
                'token' => $token,
                'token_type' => 'bearer',
            ];

            return ResponseFormatter::success($responseData, 'Authentication Success');
        } catch (\Throwable $th) {
            return ResponseFormatter::error(null, $th->getMessage(), 500);
        }
    }
}
