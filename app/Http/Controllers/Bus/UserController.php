<?php

namespace App\Http\Controllers\Bus;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    // get all user where role is user
    public function index()
    {
        try {
            $users = User::select(['id', 'role_id', 'name', 'email', 'created_at', 'updated_at'])
                ->with('role')
                ->where('role_id', 3)
                ->orderBy('created_at', 'desc') // optional direction
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
}
