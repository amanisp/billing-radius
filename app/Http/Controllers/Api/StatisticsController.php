<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Helpers\ResponseFormatter;
use App\Models\InvoiceHomepass;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class StatisticsController extends Controller
{
    /**
     * Get monthly payment statistics
     *
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyPayments()
    {
        try {
            $user = Auth::user();
            $currentYear = date('Y');

            // Build query based on user role
            $query = InvoiceHomepass::query()
                ->where('status', 'paid')
                ->whereYear('paid_at', $currentYear);

            // Filter by group_id based on role
            if ($user->role !== 'superadmin') {
                // Mitra, Teknisi, Kasir - hanya lihat data group mereka
                $query->where('group_id', $user->group_id);
            }

            // Get monthly statistics
            $monthlyStats = $query
                ->select(
                    DB::raw('MONTH(paid_at) as month'),
                    DB::raw('COUNT(*) as total_payments'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Create array for all 12 months (initialize with 0)
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            $result = [];
            for ($i = 1; $i <= 12; $i++) {
                $result[] = [
                    'month' => $i,
                    'month_name' => $monthNames[$i],
                    'total_payments' => 0,
                    'total_amount' => 0
                ];
            }

            // Fill in actual data
            foreach ($monthlyStats as $stat) {
                $monthIndex = $stat->month - 1; // Array is 0-indexed
                $result[$monthIndex] = [
                    'month' => $stat->month,
                    'month_name' => $monthNames[$stat->month],
                    'total_payments' => (int) $stat->total_payments,
                    'total_amount' => (int) $stat->total_amount
                ];
            }

            // Calculate yearly summary
            $totalYearlyPayments = array_sum(array_column($result, 'total_payments'));
            $totalYearlyAmount = array_sum(array_column($result, 'total_amount'));

            return ResponseFormatter::success([
                'year' => (int) $currentYear,
                'monthly_stats' => $result,
                'summary' => [
                    'total_yearly_payments' => $totalYearlyPayments,
                    'total_yearly_amount' => $totalYearlyAmount
                ]
            ], 'Monthly payment statistics retrieved successfully');

        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Failed to retrieve payment statistics: ' . $e->getMessage(), 500);
        }
    }

    /**
     * Get monthly payment statistics with year parameter (for future use)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function monthlyPaymentsByYear(Request $request)
    {
        try {
            $user = Auth::user();
            $year = $request->input('year', date('Y')); // Default to current year

            // Validate year
            if (!is_numeric($year) || $year < 2000 || $year > 2100) {
                return ResponseFormatter::error(null, 'Invalid year parameter', 400);
            }

            // Build query based on user role
            $query = InvoiceHomepass::query()
                ->where('status', 'paid')
                ->whereYear('paid_at', $year);

            // Filter by group_id based on role
            if ($user->role !== 'superadmin') {
                $query->where('group_id', $user->group_id);
            }

            // Get monthly statistics
            $monthlyStats = $query
                ->select(
                    DB::raw('MONTH(paid_at) as month'),
                    DB::raw('COUNT(*) as total_payments'),
                    DB::raw('SUM(amount) as total_amount')
                )
                ->groupBy('month')
                ->orderBy('month')
                ->get();

            // Create array for all 12 months
            $monthNames = [
                1 => 'Januari', 2 => 'Februari', 3 => 'Maret', 4 => 'April',
                5 => 'Mei', 6 => 'Juni', 7 => 'Juli', 8 => 'Agustus',
                9 => 'September', 10 => 'Oktober', 11 => 'November', 12 => 'Desember'
            ];

            $result = [];
            for ($i = 1; $i <= 12; $i++) {
                $result[] = [
                    'month' => $i,
                    'month_name' => $monthNames[$i],
                    'total_payments' => 0,
                    'total_amount' => 0
                ];
            }

            // Fill in actual data
            foreach ($monthlyStats as $stat) {
                $monthIndex = $stat->month - 1;
                $result[$monthIndex] = [
                    'month' => $stat->month,
                    'month_name' => $monthNames[$stat->month],
                    'total_payments' => (int) $stat->total_payments,
                    'total_amount' => (int) $stat->total_amount
                ];
            }

            // Calculate yearly summary
            $totalYearlyPayments = array_sum(array_column($result, 'total_payments'));
            $totalYearlyAmount = array_sum(array_column($result, 'total_amount'));

            return ResponseFormatter::success([
                'year' => (int) $year,
                'monthly_stats' => $result,
                'summary' => [
                    'total_yearly_payments' => $totalYearlyPayments,
                    'total_yearly_amount' => $totalYearlyAmount
                ]
            ], 'Monthly payment statistics retrieved successfully');

        } catch (\Exception $e) {
            return ResponseFormatter::error(null, 'Failed to retrieve payment statistics: ' . $e->getMessage(), 500);
        }
    }
}
