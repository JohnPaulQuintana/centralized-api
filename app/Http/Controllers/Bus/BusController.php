<?php

namespace App\Http\Controllers\Bus;

use App\Http\Controllers\Controller;
use App\Models\Bus;
use App\Models\User;
use App\Models\Business;
use App\Models\BusPath;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class BusController extends Controller
{
    // Get all registered buses (lightweight)
    public function index()
    {
        try {
            $role_id = request()->get('role_id'); // optional
            $role = request()->get('role'); // optional
            $business_id = request()->get('business_id'); // optional

            Log::info('Bus request role', [
                'role_id' => $role_id,
                'business_id' => $business_id
            ]);

            $query = Bus::select([
                'buses.id',
                'buses.business_id',
                'buses.bus_name',
                'buses.bus_capacity',
                'buses.is_active',
                'buses.created_at',
                'buses.updated_at'
            ]);

            // Only filter by business_id if present in request
            if ($business_id && $role != "admin") {
                $query->where('buses.business_id', $business_id);
            }

            if ($role_id) {
                // Inject one user column and total drivers
                $query->addSelect([
                    // first user per bus,
                    DB::raw("(SELECT name FROM users WHERE users.bus_id = buses.id AND users.role_id = 4 AND users.is_active = 1 LIMIT 1) as driver_name"),
                    DB::raw("(SELECT license_no FROM users WHERE users.bus_id = buses.id LIMIT 1) as license_no"),
                    DB::raw("(SELECT plate_no FROM users WHERE users.bus_id = buses.id LIMIT 1) as plate_no"),
                    // total drivers for this bus
                    DB::raw("(SELECT COUNT(*) FROM users WHERE users.bus_id = buses.id AND users.role_id = 4) as total_drivers"),
                    DB::raw("(SELECT name FROM businesses WHERE businesses.id = buses.business_id) as business_name")
                ]);
            } else {
                // if role_id not provided, still include total drivers
                $query->addSelect([
                    DB::raw("(SELECT COUNT(*) FROM users WHERE users.bus_id = buses.id AND users.role_id = 4) as total_drivers")
                ]);
            }

            $buses = $query->orderBy('buses.bus_name')->get();

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Bus list retrieved successfully'
            ]);

        } catch (\Exception $e) {

            Log::error('Bus list error', [
                'error' => $e->getMessage()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve bus list',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    public function indexGroup()
    {
        try {
            $role_id = request()->get('role_id'); // optional
            $role = request()->get('role'); // optional
            $business_id = request()->get('business_id'); // optional

            Log::info('Bus request role', [
                'role_id' => $role_id,
                'business_id' => $business_id
            ]);

            // Base bus query
            $query = Bus::select([
                'buses.id',
                'buses.business_id',
                'buses.bus_name',
                'buses.bus_capacity',
                'buses.is_active',
                'buses.created_at',
                'buses.updated_at'
            ]);

            // Join latest bus_paths location
            $latestPaths = DB::table('bus_paths as bp1')
                ->select('bp1.bus_id', 'bp1.latitude', 'bp1.longitude')
                ->whereRaw('bp1.created_at = (SELECT MAX(bp2.created_at) FROM bus_paths bp2 WHERE bp2.bus_id = bp1.bus_id)')
                ->toSql();

            $query->leftJoinSub(
                DB::table('bus_paths as bp1')
                    ->select('bp1.bus_id', 'bp1.latitude', 'bp1.longitude', DB::raw('MAX(bp1.created_at) as latest_time'))
                    ->groupBy('bp1.bus_id', 'bp1.latitude', 'bp1.longitude'),
                'latest_location',
                'latest_location.bus_id',
                '=',
                'buses.id'
            );

            // Filters
            if ($business_id && $role != "admin") {
                $query->where('buses.business_id', $business_id);
            }

            if ($role_id) {
                $query->addSelect([
                    DB::raw("(SELECT name FROM users WHERE users.bus_id = buses.id AND users.role_id = 4 AND users.is_active = 1 LIMIT 1) as driver_name"),
                    DB::raw("(SELECT license_no FROM users WHERE users.bus_id = buses.id LIMIT 1) as license_no"),
                    DB::raw("(SELECT plate_no FROM users WHERE users.bus_id = buses.id LIMIT 1) as plate_no"),
                    DB::raw("(SELECT COUNT(*) FROM users WHERE users.bus_id = buses.id AND users.role_id = 4) as total_drivers"),
                    DB::raw("(SELECT name FROM businesses WHERE businesses.id = buses.business_id) as business_name")
                ]);
            } else {
                $query->addSelect([
                    DB::raw("(SELECT COUNT(*) FROM users WHERE users.bus_id = buses.id AND users.role_id = 4) as total_drivers")
                ]);
            }

            // Include latest location columns
            $query->addSelect('latest_location.latitude as current_lat', 'latest_location.longitude as current_lng');

            $buses = $query->orderBy('buses.bus_name')->get();

            return response()->json([
                'success' => true,
                'data' => $buses,
                'message' => 'Bus list retrieved successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('Bus list error', [
                'error' => $e->getMessage()
            ]);

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
            $bus = Bus::find($id);

            if (!$bus) {
                return response()->json([
                    'success' => false,
                    'message' => 'Bus not found'
                ], 404);
            }

            // Filter only today's date
            $today = Carbon::today();

            // Get path travelled in chronological order (oldest to newest)
            $pathTravelled = $bus->paths()
                ->whereDate('created_at', $today)
                ->orderBy('id', 'asc')
                ->get(['latitude as lat', 'longitude as long', 'speed', 'passenger_count', 'created_at']);

            // If no records today, get the oldest record instead
            if ($pathTravelled->isEmpty()) {
                $pathTravelled = $bus->paths()
                    ->orderBy('id', 'asc')
                    ->limit(1)
                    ->get(['latitude as lat', 'longitude as long', 'speed', 'passenger_count', 'created_at']);
            }

            $pathArray = $pathTravelled->map(function ($point) {
                return [
                    'lat' => (float) $point->lat,
                    'long' => (float) $point->long,
                    'speed' => (float) $point->speed,
                    'passenger_count' => $point->passenger_count,
                    'updated_at' => $point->created_at ? $point->created_at->toISOString() : null
                ];
            })->toArray();

            // Use the last element of pathArray as current_position
            $lastPoint = end($pathTravelled);

            $currentPosition = $lastPoint ? [
                'lat' => (float) $lastPoint->lat,
                'long' => (float) $lastPoint->long,
                'speed' => (float) $lastPoint->speed,
                'passenger_count' => $lastPoint->passenger_count,
                'updated_at' => $lastPoint->created_at ? $lastPoint->created_at->toISOString() : null
            ] : null;

            // Get the active driver for this bus
            $driver = User::where('bus_id', $bus->id)
                ->where('is_active', 1)
                ->first();

            $busines = Business::where('id', $bus->business_id)->first();

            // Format response (same structure as before)
            $response = [
                'id' => $bus->id,
                'bus_name' => $bus->bus_name,
                'business_name' => $busines ? $busines->name : "Not registered",
                'bus_capacity' => $bus->bus_capacity,
                'driver_name' => $driver ? $driver->name : null,
                'plate_no' => $driver ? $driver->plate_no : null,
                'phone_no' => $driver ? $driver->phone_no : null,
                'is_active' => $bus->is_active,
                'current_position' => $currentPosition,
                'path_travelled' => $pathArray
            ];

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

             // Log incoming request raw data
        Log::info('🔵 updateLocation() REQUEST RECEIVED', [
            'bus_id' => $id,
            'data' => $request->all(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent()
        ]);

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

            // Check if bus is inactive or coordinates didn't change
            $lastPath = $bus->paths()->latest()->first();
            $latitude = (float) $request->latitude;
            $longitude = (float) $request->longitude;

            if (!$bus->is_active || ($lastPath && $lastPath->latitude == $latitude && $lastPath->longitude == $longitude)) {
                return response()->json([
                    'success' => true,
                    'message' => 'Bus is offline or location unchanged'
                ], 200);
            }

            // Create new path point
            $pathPoint = BusPath::create([
                'bus_id' => $bus->id,
                'latitude' => $latitude,
                'longitude' => $longitude,
                'speed' => $request->speed ?? 0,
                'passenger_count' => $request->passenger_count ?? 0
            ]);

            // Update bus as active if not already
            if (!$bus->is_active) {
                $bus->update(['is_active' => true]);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'id' => $pathPoint->id,
                    'bus_id' => $pathPoint->bus_id,
                    'latitude' => $pathPoint->latitude,
                    'longitude' => $pathPoint->longitude,
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
                'business_id' => 'required',
                'bus_name' => 'required|string|max:100',
                'bus_capacity' => 'required|integer|min:1|max:30', // validate capacity
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
                'business_id' => $request->business_id,
                'bus_name' => $request->bus_name,
                'bus_capacity' => $request->bus_capacity,
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
                'bus_capacity' => 'required|integer|min:1|max:30',
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

            $bus->update($request->only(['bus_name', 'bus_capacity', 'driver_name', 'license_plate', 'is_active']));

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

    public function deleteTrail(Request $request, $busId)
{
    Log::info('🔵 deleteTrail() called', [
        'bus_id' => $busId,
        'request' => $request->all(),
    ]);

    // Ensure $busId is numeric
    if (!is_numeric($busId)) {
        Log::warning('Invalid bus ID provided', ['bus_id' => $busId]);
        return response()->json(['message' => 'Invalid bus ID'], 400);
    }

    // Validate coordinates
    $request->validate([
        'latitude' => 'required',
        'longitude' => 'required',
    ]);

    $latitude = $request->latitude;
    $longitude = $request->longitude;

    // Get the first (oldest) record ID for this bus
    $firstRecord = DB::table('bus_paths')
        ->where('bus_id', $busId)
        ->orderBy('created_at', 'asc')
        ->lockForUpdate() // prevent race conditions
        ->first();

    if (!$firstRecord) {
        Log::warning('No bus trail records found', ['bus_id' => $busId]);
        return response()->json(['message' => 'No bus trail records found'], 404);
    }

    Log::info('First bus path record found', ['firstRecord' => $firstRecord]);

    // Delete all other records except the first
    $deletedCount = DB::table('bus_paths')
        ->where('bus_id', $busId)
        ->where('id', '<>', $firstRecord->id)
        ->delete();

    Log::info('Deleted other bus path records', [
        'bus_id' => $busId,
        'deleted_count' => $deletedCount
    ]);

    // Update the first record coordinates to the last stop
    $updated = DB::table('bus_paths')
        ->where('id', $firstRecord->id)
        ->update([
            'latitude' => $latitude,
            'longitude' => $longitude,
            'speed' => 0,           // reset optional fields
            'passenger_count' => 0, // reset optional fields
            'updated_at' => now(),
        ]);

    if ($updated === 0) {
        Log::error('Failed to update the first bus path record', [
            'bus_id' => $busId,
            'firstRecordId' => $firstRecord->id,
            'latitude' => $latitude,
            'longitude' => $longitude
        ]);
        return response()->json([
            'message' => 'Failed to update the first record'
        ], 500);
    }

    Log::info('First bus path record updated successfully', [
        'bus_id' => $busId,
        'firstRecordId' => $firstRecord->id,
        'latitude' => $latitude,
        'longitude' => $longitude
    ]);

    return response()->json([
        'message' => 'Bus trail cleared except the first record',
        'firstRecordUpdated' => true
    ]);
}
}
