<?php

namespace App\Http\Controllers\Bus;

use App\Http\Controllers\Controller;
use App\Models\BusStop;
use App\Models\Stop;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class StopController extends Controller
{
    // Get all bus stops
    public function index()
    {
        try {
            $stops = BusStop::select(['id', 'name', 'latitude', 'longitude'])
                ->orderBy('name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $stops,
                'message' => 'Stops retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve stops',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create new stop
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'name' => 'required|string|max:100',
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $stop = BusStop::create([
                'name' => $request->name,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude
            ]);

            return response()->json([
                'success' => true,
                'data' => $stop,
                'message' => 'Stop created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create stop',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update stop
    public function update(Request $request, $id)
    {
        try {
            $stop = BusStop::find($id);

            if (!$stop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stop not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'name' => 'sometimes|required|string|max:100',
                'latitude' => 'sometimes|required|numeric|between:-90,90',
                'longitude' => 'sometimes|required|numeric|between:-180,180'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $stop->update($request->only(['name', 'latitude', 'longitude']));

            return response()->json([
                'success' => true,
                'data' => $stop,
                'message' => 'Stop updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update stop',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Delete stop
    public function destroy($id)
    {
        try {
            $stop = BusStop::find($id);

            if (!$stop) {
                return response()->json([
                    'success' => false,
                    'message' => 'Stop not found'
                ], 404);
            }

            $stop->delete();

            return response()->json([
                'success' => true,
                'message' => 'Stop deleted successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to delete stop',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
