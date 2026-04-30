<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Role;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Tymon\JWTAuth\Facades\JWTAuth;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required|email|unique:users',
            'password' => 'required|min:6',
            'role' => 'required|exists:roles,name',
        ]);

        $role = Role::where('name', $request->role)->first();

        // store plain password BEFORE hashing (for email only)
        $plainPassword = $request->password;

        // Generate OTP only for role 5
        $otp = ($role->id === 5) ? rand(100000, 999999) : null;

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role_id' => $role->id,
            'business_id' => $request->business_id,
            'license_no' => $request->license_no,
            'phone_no' => $request->phone_no,
            'bus_id' => $request->bus_id,
            'plate_no' => $request->plate_no,
            // OTP fields
            'otp' => $otp ? Hash::make($otp) : null,
            'otp_expires_at' => $otp ? Carbon::now()->addMinutes(5) : null,
            'is_verified' => ($role->id === 5) ? false : true,
            'otp_last_sent_at' => $otp ? now() : null,

        ]);

        // / IF ROLE 5 → SEND OTP, DO NOT RETURN TOKEN YET
        if ($role->id === 5) {
            try {
                Mail::send('emails.otp', [
                    'otp' => $otp,
                    'user' => $user,
                ], function ($message) use ($user) {
                    $message->to($user->email);
                    $message->subject('Your OTP Code');
                });

                return response()->json([
                    'message' => 'OTP sent to email',
                    'user_id' => $user->id,
                ]);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to send OTP',
                    'debug' => $e->getMessage(),
                ], 500);
            }
        }

        $token = JWTAuth::fromUser($user);

        // Send email ONLY for operator (role_id === 3)
        if ($role->id === 3) {
            // Send confirmation email
            try {
                Mail::send('emails.operator_email', [
                    'user' => $user,
                    'password' => $plainPassword,
                ], function ($message) use ($user) {
                    $message->to($user->email);
                    $message->subject('Your Bus Tracker Operator Account');
                });
                Log::info('Password change confirmation email sent', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'time' => now(),
                ]);
            } catch (\Exception $e) {
                Log::error('Failed to send password change email', [
                    'user_id' => $user->id,
                    'email' => $user->email,
                    'error' => $e->getMessage(),
                    'time' => now(),
                ]);
            }
        }

        return response()->json(compact('user', 'token'));
    }

    public function verifyOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
            'otp' => 'required',
        ]);

        $user = User::find($request->user_id);

        if (! Hash::check($request->otp, $user->otp)) {
            return response()->json(['error' => 'Invalid OTP'], 400);
        }

        if (now()->gt($user->otp_expires_at)) {
            return response()->json(['error' => 'OTP expired'], 400);
        }

        $user->update([
            'is_verified' => true,
            'otp' => null,
            'otp_expires_at' => null,
        ]);

        $token = JWTAuth::fromUser($user);

        return response()->json([
            'message' => 'Verified successfully',
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function resendOtp(Request $request)
    {
        $request->validate([
            'user_id' => 'required|exists:users,id',
        ]);

        $user = User::find($request->user_id);

        // Only allow for role 5
        if ($user->role_id !== 5) {
            return response()->json(['error' => 'OTP not applicable'], 400);
        }

        // Already verified?
        if ($user->is_verified) {
            return response()->json(['error' => 'User already verified'], 400);
        }

        // Cooldown check (60 seconds)
        // if ($user->otp_last_sent_at && now()->diffInSeconds($user->otp_last_sent_at) < 60) {
        //     $elapsed = now()->diffInSeconds($user->otp_last_sent_at);
        //     $remaining = max(0, 60 - (int) $elapsed);

        //     $minutes = floor($remaining / 60);
        //     $seconds = $remaining % 60;

        //     $formatted = sprintf('%02d:%02d', $minutes, $seconds);

        //     return response()->json([
        //         'error' => "Please wait {$formatted} before requesting again",
        //         'remaining_seconds' => $remaining,
        //         'remaining_time' => $formatted,
        //     ], 429);
        // }

        // Generate new OTP
        $otp = rand(100000, 999999);

        $user->update([
            'otp' => $otp ? Hash::make($otp) : null,
            'otp_expires_at' => Carbon::now()->addMinutes(5),
            'otp_last_sent_at' => now(),
        ]);

        try {
            Mail::send('emails.otp', [
                'otp' => $otp,
                'user' => $user,
            ], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Your New OTP Code');
            });

            return response()->json([
                'message' => 'OTP resent successfully',
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Failed to send OTP',
                'debug' => $e->getMessage(),
            ], 500);
        }
    }

    // bus trackker login
    public function bus_login(Request $request)
    {
        \Log::warning('Password reset attempt failed', [
            'credentials' => $request->only('email', 'password'),
            'password' => $request->password,
            'ip' => $request->ip(),
            'time' => now(),
        ]);

        $credentials = $request->only('email', 'password');

        // Attempt login and generate token
        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // ✅ Retrieve the user associated with this token
        $user = JWTAuth::setToken($token)->toUser();

        // load the role
        $user->load('role');

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function updateProfile(Request $request)
    {
        $user = auth()->user(); // JWTAuth::parseToken()->authenticate() if using JWT

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,'.$user->id,
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

    // for admin only direct chnage password
    public function directChangePassword(Request $request)
    {
        $user = auth()->user();

        $request->validate([
            'current_password' => 'required',
            'new_password' => 'required|string|min:6|confirmed',
        ]);

        // Check if current password matches
        if (! Hash::check($request->current_password, $user->password)) {
            return response()->json([
                'success' => false,
                'message' => 'Current password is incorrect',
            ], 400);
        }

        // Update password
        $user->password = bcrypt($request->new_password);
        $user->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully',
        ]);
    }

    public function forgotPassword(Request $request)
    {
        // Manual validation to handle email not found gracefully
        $request->validate([
            'email' => 'required|email',
        ]);

        $user = User::where('email', $request->email)->first();

        if (! $user) {
            return response()->json([
                'error' => 'Email not found',
            ], 404);
        }

        // Generate temporary token for password change
        $tempToken = Str::random(64);

        // Store in cache for 5 minutes
        cache()->put('password_reset_'.$tempToken, $user->id, 300);

        return response()->json([
            'message' => 'Email validated. Enter new password.',
            'token' => $tempToken,
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
            'time' => now(),
        ]);

        $userId = cache()->get('password_reset_'.$request->token);

        if (! $userId) {
            Log::warning('Password reset attempt failed', [
                'token' => $request->token,
                'ip' => $request->ip(),
                'time' => now(),
            ]);

            return response()->json(['error' => 'Invalid or expired token'], 400);
        }

        $user = User::find($userId);
        if (! $user) {
            Log::error('Password reset failed: user not found', [
                'user_id' => $userId,
                'token' => $request->token,
                'ip' => $request->ip(),
                'time' => now(),
            ]);

            return response()->json(['error' => 'User not found'], 404);
        }

        $user->password = Hash::make($request->password);
        $user->save();

        Log::info('Password reset successful', [
            'user_id' => $user->id,
            'email' => $user->email,
            'ip' => $request->ip(),
            'time' => now(),
        ]);

        // Remove token
        cache()->forget('password_reset_'.$request->token);

        // Send confirmation email
        try {
            Mail::send('emails.password_changed', [], function ($message) use ($user) {
                $message->to($user->email);
                $message->subject('Bus Tracker Password Changed Successfully');
            });
            Log::info('Password change confirmation email sent', [
                'user_id' => $user->id,
                'email' => $user->email,
                'time' => now(),
            ]);
        } catch (\Exception $e) {
            Log::error('Failed to send password change email', [
                'user_id' => $user->id,
                'email' => $user->email,
                'error' => $e->getMessage(),
                'time' => now(),
            ]);
        }

        return response()->json(['message' => 'Password changed successfully.']);
    }

    public function login(Request $request)
    {
        $credentials = $request->only('email', 'password');

        // Attempt login and generate token
        if (! $token = JWTAuth::attempt($credentials)) {
            return response()->json(['error' => 'Invalid credentials'], 401);
        }

        // Retrieve authenticated user
        $user = JWTAuth::setToken($token)->toUser();

        // 🔥 OTP verification check ONLY for role 5
        if ($user->role_id == 5 && ! $user->is_verified) {

            // Generate new OTP
            $otp = rand(100000, 999999);

            $user->update([
                'otp' => $otp ? Hash::make($otp) : null,
                'otp_expires_at' => Carbon::now()->addMinutes(5),
                'otp_last_sent_at' => now(),
            ]);

            try {
                Mail::send('emails.otp', [
                    'otp' => $otp,
                    'user' => $user,
                ], function ($message) use ($user) {
                    $message->to($user->email);
                    $message->subject('Your New OTP Code');
                });

                return response()->json([
                    'error' => 'Please verify your email OTP before logging in.',
                    'requires_verification' => true,
                    'user_id' => $user->id,
                ], 403);
            } catch (\Exception $e) {
                return response()->json([
                    'error' => 'Failed to send OTP',
                    'debug' => $e->getMessage(),
                ], 500);
            }

            // return response()->json([
            //     'error' => 'Please verify your email OTP before logging in.',
            //     'requires_verification' => true,
            //     'user_id' => $user->id,
            // ], 403);
        }

        // Check for active driver on same bus
        if ($user->role_id == 4) {

            // CASE 1: Same user already active
            if ($user->is_active == 1) {
                return response()->json([
                    'error' => 'This account is already active. Please logout from the other device first.',
                ], 403);
            }

            // CASE 2: Another driver using same bus
            $existingDriver = User::where('bus_id', $user->bus_id)
                ->where('role_id', 4)
                ->where('is_active', 1)
                ->where('id', '!=', $user->id)
                ->first();

            if ($existingDriver) {
                return response()->json([
                    'error' => 'This bus is currently in use by another driver.',
                ], 403);
            }

            $user->is_active = 1; // online
            $user->save();
        }

        return response()->json([
            'token' => $token,
            'user' => $user,
        ]);
    }

    public function me()
    {
        return response()->json(auth()->user()->load('role', 'projects'));
    }

    public function logout()
    {
        $user = auth()->user();

        if ($user) {
            $user->is_active = 0; // set offline
            $user->save();
        }

        auth()->logout();

        return response()->json([
            'message' => 'Successfully logged out',
        ]);
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

        if (! $user) {
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
            'token' => $token,
        ]);
    }
}
