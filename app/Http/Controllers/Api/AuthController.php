<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required|min:6'
        ]);

        // Find user in your custom users table
        $user = DB::table('users')
            ->where('email', $request->email)
            ->first();

        // Check if user exists and password matches
        if (!$user || !Hash::check($request->password, $user->password_hash)) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid email or password'
            ], 401);
        }

        // Create a simple token (for testing)
        $token = base64_encode($user->user_id . '|' . $user->email . '|' . now());

        return response()->json([
            'success' => true,
            'message' => 'Login successful',
            'token' => $token,
            'user' => [
                'id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ]
        ]);
    }

    public function register(Request $request)
    {
        // Validate input
        $request->validate([
            'username' => 'required|string|max:50|unique:users,username',
            'email' => 'required|email|max:100|unique:users,email',
            'password' => 'required|min:6|confirmed'
        ]);

        // Insert new user
        $userId = DB::table('users')->insertGetId([
            'username' => $request->username,
            'email' => $request->email,
            'password_hash' => Hash::make($request->password),
            'role' => 'user',
            'created_at' => now()
        ]);

        // Get the newly created user
        $user = DB::table('users')->where('user_id', $userId)->first();

        // Create token
        $token = base64_encode($user->user_id . '|' . $user->email . '|' . now());

        return response()->json([
            'success' => true,
            'message' => 'Registration successful!',
            'token' => $token,
            'user' => [
                'id' => $user->user_id,
                'username' => $user->username,
                'email' => $user->email,
                'role' => $user->role
            ]
        ], 201);
    }
    
    public function dashboard(Request $request)
    {
        $token = $request->bearerToken();

        if (!$token) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Decode token to get user_id
        $parts = explode('|', base64_decode($token));
        $userId = $parts[0] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid token'
            ], 401);
        }

        // Get tanks for this user
        $tanks = DB::table('tanks')
            ->join('dashboard', 'tanks.TankID', '=', 'dashboard.TankID')
            ->where('dashboard.UserID', $userId)
            ->select(
                'dashboard.DashboardID',
                'dashboard.UserID',
                'dashboard.TankID',
                'dashboard.Mode',
                'dashboard.Temperature',
                'dashboard.Ph_Level',
                'dashboard.Turbidity',
                'dashboard.Status',
                'tanks.Tankname'
            )
            ->get();

        return response()->json([
            'success' => true,
            'data' => $tanks,
            'message' => 'Dashboard loaded'
        ]);
    }

    public function pairTank(Request $request)
    {
        // Validate input
        $request->validate([
            'product_id' => 'required|string|exits:purchases,purchase_id'
        ]);

        $productId = $request->product_id;

        // Check if ProductID exists and its not activated
        $purchase = DB::table('purchases')
            ->where('purchase_id', $productId)
            ->first();
        
        if (!$purchase) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid Product ID'
            ], 404);
        }

        if ($purchase->is_activated == 1) {
            return response()->json([
                'success' => false,
                'message' => 'This Product ID has already been paired'
            ], 409);
        }

        // Get the authenticated user (from token)
        $token = $request->bearerToken();
        $parts = explode('|', base64_decode($token));
        $userId = $parts[0] ?? null;

        if (!$userId) {
            return response()->json([
                'success' => false,
                'message' => 'Unauthorized'
            ], 401);
        }

        // Create a new tank
        $tankId = DB::table('tanks')->insertGetId([
            'ProductId' => $productId,
            'Tankname' => 'Tank' . $productId // Default name
        ]);

        // Link to user in dashboard
        DB::table('dashboard')->insert([
            'UserID' => $userId,
            'TankID' => $tankId,
            'Mode' => 'Growing',
            'Temperature' => 25.0,
            'Ph_Level' => 7.0,
            'Turbidity' => 0,
            'Status' => 'Safe'
        ]);

        // Mark ProductID as activated
        DB::table('purchases')
            ->where('purchase_id', $productId)
            ->update(['is_activated' => 1]);

        return response()->json([
            'success' => true,
            'message' => 'Tank paired successfully!',
            'tank' => [
                'TankID' => $tankId,
                'ProductID' => $productId,
                'Tankname' => 'Tank' . $productId
            ]
        ], 201);
    }
}