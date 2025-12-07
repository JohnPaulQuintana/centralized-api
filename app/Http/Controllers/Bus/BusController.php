<?php

namespace App\Http\Controllers\Bus;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\BusPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class BusController extends Controller
{
    // Get all registered buses (lightweight)
    public function index()
    {
        try {
            $buses = Bus::select(['id', 'bus_name', 'driver_name', 'license_plate', 'is_active'])
                ->orderBy('bus_name')
                ->get();

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Bus list retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bus list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get detailed tracking data for a specific bus
    public function tracking($id)
    {
        try {
            // \Log::info('=== BUS TRACKING REQUEST START ===');
            // \Log::info('Bus ID requested: ' . $id);

            // Log the raw database query
            // \Log::info('Fetching bus with ID: ' . $id);

            $bus = Bus::with(['paths' => function ($query) {
                // \Log::info('Loading paths relationship with query');
                $query->orderBy('created_at', 'desc')->limit(50);
            }])->find($id);

            if (!$bus) {
                // \Log::warning('Bus not found with ID: ' . $id);
                return response()->json([
                    'success' => false,
                    'message' => 'Bus not found'
                ], 404);
            }

            // \Log::info('Bus found:', [
            //     'id' => $bus->id,
            //     'bus_name' => $bus->bus_name,
            //     'driver_name' => $bus->driver_name,
            //     'license_plate' => $bus->license_plate,
            //     'is_active' => $bus->is_active
            // ]);

            // Check if paths relationship is loaded
            // \Log::info('Checking paths relationship...');
            // \Log::info('Paths count (eager loaded): ' . $bus->paths->count());

            // Get latest position using fresh query
            // \Log::info('Getting latest position...');
            $currentPosition = $bus->paths()->latest()->first();

            // if ($currentPosition) {
            //     \Log::info('Current position found:', [
            //         'latitude' => $currentPosition->latitude,
            //         'longitude' => $currentPosition->longitude,
            //         'speed' => $currentPosition->speed,
            //         'passenger_count' => $currentPosition->passenger_count,
            //         'created_at' => $currentPosition->created_at
            //     ]);
            // } else {
            //     \Log::info('No current position found for bus ID: ' . $id);
            // }

            // Get path travelled
            // \Log::info('Getting path travelled (last 50 points)...');
            $pathTravelled = $bus->paths()
                ->orderBy('created_at', 'asc')
                ->get(['latitude as lat', 'longitude as long', 'speed', 'passenger_count']);

            // \Log::info('Path travelled count: ' . $pathTravelled->count());

            // if ($pathTravelled->count() > 0) {
            //     \Log::info('First path point:', $pathTravelled->first()->toArray());
            //     \Log::info('Last path point:', $pathTravelled->last()->toArray());
            // }

            // Format response
            $response = [
                'id' => $bus->id,
                'bus_name' => $bus->bus_name,
                'driver_name' => $bus->driver_name,
                'license_plate' => $bus->license_plate,
                'is_active' => $bus->is_active,
                'current_position' => $currentPosition ? [
                    'lat' => (float) $currentPosition->latitude,
                    'long' => (float) $currentPosition->longitude,
                    'speed' => (float) $currentPosition->speed,
                    'passenger_count' => $currentPosition->passenger_count,
                    'updated_at' => $currentPosition->created_at ? $currentPosition->created_at->toISOString() : null
                ] : null,
                'path_travelled' => $pathTravelled->map(function ($point) {
                    return [
                        'lat' => (float) $point->lat,
                        'long' => (float) $point->long,
                        'speed' => (float) $point->speed,
                        'passenger_count' => $point->passenger_count
                    ];
                })
            ];

            // \Log::info('Response being sent:', [
            //     'has_current_position' => !is_null($response['current_position']),
            //     'path_travelled_count' => count($response['path_travelled']),
            //     'response_keys' => array_keys($response)
            // ]);

            // \Log::info('=== BUS TRACKING REQUEST END ===');

            return response()->json([
                'success' => true,
                'data' => $response,
                'message' => 'Bus tracking data retrieved successfully'
            ]);
        } catch (\Exception $e) {
            \Log::error('Error in bus tracking endpoint for ID ' . $id . ': ' . $e->getMessage(), [
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bus tracking data',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    // Update bus location (from GPS device)
    public function updateLocation(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'latitude' => 'required|numeric|between:-90,90',
                'longitude' => 'required|numeric|between:-180,180',
                'speed' => 'nullable|numeric|min:0',
                'passenger_count' => 'nullable|integer|min:0'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bus = Bus::find($id);

            if (!$bus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bus not found'
                ], 404);
            }

            // Create new path point
            $pathPoint = BusPath::create([
                'bus_id' => $bus->id,
                'latitude' => $request->latitude,
                'longitude' => $request->longitude,
                'speed' => $request->speed ?? 0,
                'passenger_count' => $request->passenger_count ?? 0
            ]);

            // Update bus as active
            if (!$bus->is_active) {
                $bus->update(['is_active' => true]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $pathPoint->id,
                    'bus_id' => $pathPoint->bus_id,
                    'latitude' => (float) $pathPoint->latitude,
                    'longitude' => (float) $pathPoint->longitude,
                    'speed' => (float) $pathPoint->speed,
                    'passenger_count' => $pathPoint->passenger_count,
                    'created_at' => $pathPoint->created_at->toISOString()
                ],
                'message' => 'Location updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update location',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Get bus locations within time range (for analytics)
    public function locationHistory(Request $request, $id)
    {
        try {
            $validator = Validator::make($request->all(), [
                'start_date' => 'required|date',
                'end_date' => 'required|date|after_or_equal:start_date'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bus = Bus::find($id);

            if (!$bus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bus not found'
                ], 404);
            }

            $locations = $bus->paths()
                ->whereBetween('created_at', [$request->start_date, $request->end_date])
                ->orderBy('created_at', 'asc')
                ->get(['latitude', 'longitude', 'speed', 'passenger_count', 'created_at']);

            return response()->json([
                'success' => true,
                'data' => [
                    'bus_id' => $bus->id,
                    'bus_name' => $bus->bus_name,
                    'locations' => $locations->map(function ($location) {
                        return [
                            'lat' => (float) $location->latitude,
                            'long' => (float) $location->longitude,
                            'speed' => (float) $location->speed,
                            'passenger_count' => $location->passenger_count,
                            'timestamp' => $location->created_at->toISOString()
                        ];
                    }),
                    'total_points' => $locations->count()
                ],
                'message' => 'Location history retrieved successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve location history',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Create new bus (admin only)
    public function store(Request $request)
    {
        try {
            $validator = Validator::make($request->all(), [
                'bus_name' => 'required|string|max:100',
                'driver_name' => 'nullable|string|max:100',
                'license_plate' => 'nullable|string|max:20',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bus = Bus::create([
                'bus_name' => $request->bus_name,
                'driver_name' => $request->driver_name,
                'license_plate' => $request->license_plate,
                'is_active' => $request->is_active ?? true
            ]);

            return response()->json([
                'success' => true,
                'data' => $bus,
                'message' => 'Bus created successfully'
            ], 201);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to create bus',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // Update bus info
    public function update(Request $request, $id)
    {
        try {
            $bus = Bus::find($id);

            if (!$bus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bus not found'
                ], 404);
            }

            $validator = Validator::make($request->all(), [
                'bus_name' => 'sometimes|required|string|max:100',
                'driver_name' => 'nullable|string|max:100',
                'license_plate' => 'nullable|string|max:20',
                'is_active' => 'nullable|boolean'
            ]);

            if ($validator->fails()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Validation error',
                    'errors' => $validator->errors()
                ], 422);
            }

            $bus->update($request->only(['bus_name', 'driver_name', 'license_plate', 'is_active']));

            return response()->json([
                'success' => true,
                'data' => $bus,
                'message' => 'Bus updated successfully'
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to update bus',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
