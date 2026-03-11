<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Business;
use Illuminate\Support\Facades\DB;

class BusinessController extends Controller
{
    // Fetch all businesses with user count
    public function index(Request $request)
    {
        try {
            $perPage = $request->get('per_page', 10); // default 10 per page
            $page = $request->get('page', 1);        // default first page

            $query = DB::table('businesses')
                ->leftJoin('users', 'users.business_id', '=', 'businesses.id')
                ->leftJoin('buses', 'buses.business_id', '=', 'businesses.id')
                ->selectRaw('
                    businesses.id,
                    businesses.name,
                    businesses.status,
                    businesses.created_at,
                    businesses.updated_at,
                    COUNT(users.id) as total_users,
                    COUNT(DISTINCT buses.id) as total_buses
                ')
                ->groupBy(
                    'businesses.id',
                    'businesses.name',
                    'businesses.status',
                    'businesses.created_at',
                    'businesses.updated_at'
                )
                ->orderBy('businesses.created_at', 'desc');

            // Correct total count without breaking group by
            $total = DB::table('businesses')->count();

            // Paginate
            $businesses = $query
                ->forPage($page, $perPage)
                ->get();

            $totalPages = ceil($total / $perPage);

            return response()->json([
                'success' => true,
                'data' => $businesses,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'message' => 'Business list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve businesses',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Create new business
    public function store(Request $request)
    {
        try {
            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:businesses,name',
                'status' => 'required|boolean',
            ]);

            $business = Business::create([
                'name' => $validated['name'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $business,
                'message' => 'Business created successfully'
            ], 201);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create business',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update existing business
    public function update(Request $request, $id)
    {
        try {
            $business = Business::findOrFail($id);

            $validated = $request->validate([
                'name' => 'required|string|max:255|unique:businesses,name,' . $business->id,
                'status' => 'required|boolean',
            ]);

            $business->update([
                'name' => $validated['name'],
                'status' => $validated['status'],
            ]);

            return response()->json([
                'success' => true,
                'data' => $business,
                'message' => 'Business updated successfully'
            ]);
        } catch (\Illuminate\Database\Eloquent\ModelNotFoundException $e) {
            return response()->json([
                'success' => false,
                'message' => 'Business not found',
                'error' => $e->getMessage()
            ], 404);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update business',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}