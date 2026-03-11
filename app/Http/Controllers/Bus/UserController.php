<?php

namespace App\Http\Controllers\Bus;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserController extends Controller
{
    // get all user where role is user
    public function index()
    {
        try {

            $currentUser = auth()->user();

            $query = User::select([
                    'users.id',
                    'users.role_id',
                    'users.business_id',
                    'users.license_no',
                    'users.phone_no',
                    'users.plate_no',
                    'users.bus_id',
                    'users.name',
                    'users.email',
                    'users.created_at',
                    'users.updated_at',
                    DB::raw("COALESCE(businesses.name, 'Unknown') as business_name"),
                    DB::raw("COALESCE(buses.bus_name, 'Unassigned') as bus_name")
                ])
                ->leftJoin('businesses', 'users.business_id', '=', 'businesses.id')
                ->leftJoin('buses', 'users.bus_id', '=', 'buses.id')
                ->with('role')
                ->whereIn('users.role_id', [5])
                ->where('users.id', '!=', $currentUser->id);

            // If the logged-in user is an operator, filter by business_id
            if ($currentUser->role->name === 'operator') {
                $query->where('users.business_id', $currentUser->business_id);
            }

            $users = $query
                ->orderBy('users.created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'User list retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // get all user where role is user
    public function indexDriver()
    {
        try {

            $currentUser = auth()->user();

            $query = User::select([
                    'users.id',
                    'users.role_id',
                    'users.business_id',
                    'users.license_no',
                    'users.phone_no',
                    'users.plate_no',
                    'users.bus_id',
                    'users.name',
                    'users.email',
                    'users.created_at',
                    'users.updated_at',
                    DB::raw("COALESCE(businesses.name, 'Unknown') as business_name"),
                    DB::raw("COALESCE(buses.bus_name, 'Unassigned') as bus_name")
                ])
                ->leftJoin('businesses', 'users.business_id', '=', 'businesses.id')
                ->leftJoin('buses', 'users.bus_id', '=', 'buses.id')
                ->with('role')
                ->whereIn('users.role_id', [4])
                ->where('users.id', '!=', $currentUser->id);

            // If the logged-in user is an operator, filter by business_id
            if ($currentUser->role->name === 'operator') {
                $query->where('users.business_id', $currentUser->business_id);
            }

            $users = $query
                ->orderBy('users.created_at', 'desc')
                ->paginate(10);

            return response()->json([
                'success' => true,
                'data' => $users,
                'message' => 'User list retrieved successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve user list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

  
    // Update Plate No for all users assigned to a bus
    public function updatePlateNo(Request $request, $busId)
    {
        $request->validate([
            'plate_no' => 'required|string|max:255',
        ]);

        try {
            // Update all users who have this bus_id
            $updatedRows = User::where('bus_id', $busId)
                ->update(['plate_no' => $request->plate_no]);

            return response()->json([
                'success' => true,
                'message' => "Plate number updated for {$updatedRows} user(s) on bus ID {$busId}",
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update plate numbers',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
