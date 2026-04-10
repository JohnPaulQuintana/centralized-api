<?php

namespace App\Http\Controllers\Analytic;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\Business;
use App\Models\OperatorBusDailyAnalytic;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;

class AnalyticController extends Controller
{
    public function index(Request $request)
    {
        $filter = $request->query('filter', 'today');

        // You can extend this later for real date filtering
        // For now: static counts per filter type

        if ($filter === 'today') {
            $data = [
                [
                    'name' => 'Users',
                    'value' => User::count(),
                ],
                [
                    'name' => 'Businesses',
                    'value' => Business::count(),
                ],
                [
                    'name' => 'Buses',
                    'value' => Bus::count(),
                ],
            ];
        } elseif ($filter === 'week') {
            $data = [
                [
                    'name' => 'Users',
                    'value' => User::where('created_at', '>=', now()->subDays(7))->count(),
                ],
                [
                    'name' => 'Businesses',
                    'value' => Business::where('created_at', '>=', now()->subDays(7))->count(),
                ],
                [
                    'name' => 'Buses',
                    'value' => Bus::where('created_at', '>=', now()->subDays(7))->count(),
                ],
            ];
        } else { // month
            $data = [
                [
                    'name' => 'Users',
                    'value' => User::where('created_at', '>=', now()->subDays(30))->count(),
                ],
                [
                    'name' => 'Businesses',
                    'value' => Business::where('created_at', '>=', now()->subDays(30))->count(),
                ],
                [
                    'name' => 'Buses',
                    'value' => Bus::where('created_at', '>=', now()->subDays(30))->count(),
                ],
            ];
        }

        return response()->json([
            'data' => $data,
        ]);
    }

    public function operatorAnalytics(Request $request)
    {
        try {
            $filter = $request->query('filter', 'today');

            // 📅 Date filter
            switch ($filter) {
                case 'week':
                    $startDate = Carbon::now()->startOfWeek();
                    break;
                case 'month':
                    $startDate = Carbon::now()->startOfMonth();
                    break;
                default:
                    $startDate = Carbon::today();
                    break;
            }

            // 📊 Summary
            $summary = OperatorBusDailyAnalytic::where('date', '>=', $startDate)
                ->selectRaw('
                SUM(total_passengers) as passengers,
                SUM(total_distance_km) as distance,
                AVG(avg_speed) as speed,
                COUNT(DISTINCT bus_id) as buses
            ')
                ->first();

            // 👤 Drivers
            $drivers = User::where('role_id', 4)->count();

            // 🚌 Per bus analytics
            $buses = OperatorBusDailyAnalytic::with('bus')
                ->where('date', '>=', $startDate)
                ->get()
                ->map(function ($item) {
                    return [
                        'name' => $item->bus->plate_no ?? 'N/A',
                        'passengers' => $item->total_passengers,
                        'distance' => round($item->total_distance_km, 2),
                        'speed' => round($item->avg_speed, 2),
                    ];
                });

            return response()->json([
                'success' => true,
                'data' => [
                    'summary' => [
                        'buses' => (int) ($summary->buses ?? 0),
                        'drivers' => $drivers,
                        'passengers' => (int) ($summary->passengers ?? 0),
                        'distance' => round($summary->distance ?? 0, 2),
                        'speed' => round($summary->speed ?? 0, 2),
                    ],
                    'buses' => $buses,
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Error loading analytics',
                'error' => $e->getMessage(),
            ], 500);
        }
    }
}
