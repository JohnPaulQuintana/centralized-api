<?php

namespace App\Http\Controllers\Analytic;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Business;
use App\Models\Bus;

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
                    "name" => "Users",
                    "value" => User::count(),
                ],
                [
                    "name" => "Businesses",
                    "value" => Business::count(),
                ],
                [
                    "name" => "Buses",
                    "value" => Bus::count(),
                ],
            ];
        }

        elseif ($filter === 'week') {
            $data = [
                [
                    "name" => "Users",
                    "value" => User::where('created_at', '>=', now()->subDays(7))->count(),
                ],
                [
                    "name" => "Businesses",
                    "value" => Business::where('created_at', '>=', now()->subDays(7))->count(),
                ],
                [
                    "name" => "Buses",
                    "value" => Bus::where('created_at', '>=', now()->subDays(7))->count(),
                ],
            ];
        }

        else { // month
            $data = [
                [
                    "name" => "Users",
                    "value" => User::where('created_at', '>=', now()->subDays(30))->count(),
                ],
                [
                    "name" => "Businesses",
                    "value" => Business::where('created_at', '>=', now()->subDays(30))->count(),
                ],
                [
                    "name" => "Buses",
                    "value" => Bus::where('created_at', '>=', now()->subDays(30))->count(),
                ],
            ];
        }

        return response()->json([
            "data" => $data
        ]);
    }
}