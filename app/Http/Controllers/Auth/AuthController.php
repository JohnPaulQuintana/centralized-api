<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\User;
use App\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|exists:roles,name'
        ]);

        $role = Role::where('name', $request->role)->first();

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
        ]);

        $token = JWTAuth::fromUser($user);
        return response()->json(compact('user', 'token'));
    }

    // bus trackker login
    public function bus_login(Request $request)
    {
        \Log::warning('Password reset attempt failed', [
            'credentials' => $request->only('email', 'password'),
            'password' => $request->password,
            'ip' => $request->ip(),
            'time' => now()
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt login and generate token
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // ✅ Retrieve the user associated with this token
        $user = JWTAuth::setToken($token)->toUser();

        //load the role
        $user->load('role');
        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user(); // JWTAuth::parseToken()->authenticate() if using JWT

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $user->name = $request->name;
        $user->email = $request->email;
        $user->save();

        return response()->json([
            'message' => 'Profile updated successfully',
            'user' => $user,
        ]);
    }

    //for admin only direct chnage password
    public function directChangePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Check if current password matches
        if (!Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect'
            ], 400);
        }

        // Update password
        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully'
        ]);
    }


    public function forgotPassword(Request $request)
    {
        // Manual validation to handle email not found gracefully
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return response()->json([
                'error' => 'Email not found'
            ], 404);
        }

        // Generate temporary token for password change
        $tempToken = Str::random(64);

        // Store in cache for 5 minutes
        cache()->put('password_reset_' . $tempToken, $user->id, 300);

        return response()->json([
            'message' => 'Email validated. Enter new password.',
            'token' => $tempToken
        ]);
    }

    public function resetPassword(Request $request)
    {
        $request->validate([
            'token' => 'required|string',
            'password' => 'required|string|min:6|confirmed',
        ]);
        \Log::warning('Password reset attempt failed', [
            'token' => $request->token,
            'password' => $request->password,
            'ip' => $request->ip(),
            'time' => now()
        ]);

        $userId = cache()->get('password_reset_' . $request->token);

        if (!$userId) {
            Log::warning('Password reset attempt failed', [
                'token' => $request->token,
                'ip' => $request->ip(),
                'time' => now()
            ]);
            return response()->json(['error' => 'Invalid or expired token'], 400);
        }

        $user = User::find($userId);
        if (!$user) {
            Log::error('Password reset failed: user not found', [
                'user_id' => $userId,
                'token' => $request->token,
                'ip' => $request->ip(),
                'time' => now()
            ]);
            return response()->json(['error' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'time' => now()
        ]);

        // Remove token
        cache()->forget('password_reset_' . $request->token);

        // Send confirmation email
        try {
            Mail::send('emails.password_changed', [], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Bus Tracker Password Changed Successfully');
            });
            Log::info('Password change confirmation email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'time' => now()
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password change email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'time' => now()
            ]);
        }

        return response()->json(['message' => 'Password changed successfully.']);
    }


    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Attempt login and generate token
        if (!$token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // ✅ Retrieve the user associated with this token
        $user = JWTAuth::setToken($token)->toUser();

        return response()->json([
            'token' => $token,
            'user' => $user
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user()->load('role', 'projects'));
    }

    public function logout()
    {
        auth()->logout();
        return response()->json(['message' => 'Successfully logged out']);
    }

    public function googleLogin(Request $request)
    {
        $request->validate([
            'name' => 'required|string',
            'email' => 'required|email',
            'avatar' => 'nullable|string',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            // Default role name
            $defaultRoleName = 'user'; // adjust as needed
            $role = Role::where('name', $defaultRoleName)->first();

            // Create user with default password (random)
            $user = User::create([
                'name' => $request->name,
                'email' => $request->email,
                'password' => Hash::make(uniqid('google_', true)), // secure random password
                'role_id' => 3,
            ]);
        }

        // Generate JWT token
        $token = JWTAuth::fromUser($user);

        return response()->json([
            'user' => $user,
            'token' => $token
        ]);
    }
}
