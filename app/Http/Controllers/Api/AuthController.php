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
            'password' => 'required|min:6',
            'confirm_password' => 'required|same:password',
            'product_id' => 'required|string|exists:purchases,purchase_id'
        ]);

        $productId = $request->product_id;

        // Check if ProductID is already activated
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
                'message' => 'This Product ID has aleardy been used to register an account'
            ], 409);
        }

        // Check if ProductID is already linked to a tank (redundance check)
        $existingTank = DB::table('tanks')
            ->where('ProductID', $productId)
            ->first();

        if ($existingTank) {
            return response()->json([
                'success' => false,
                'message' => 'This Product ID is already linked to a tank'
            ], 409);
        }

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

        // Create a tank automatically
        $tankId = DB::table('tanks')->insertGetId([
            'ProductID' => $productId,
            'Tankname' => 'Tank' . $productId
        ]);

        // Link tank to user in dashboard
        DB::table('dashboard')->insert([
            'UserID' => $userId,
            'TankID' => $tankId,
            'Mode' => 'Growing',
            'Temperature' => 25.0,
            'Ph_Level' => 7.0,
            'Turbidity' => 0,
            'Status' => 'Safe'
        ]);

        DB::table('purchases')
            ->where('purchase_id', $productId)
            ->update(['is_activated' => 1]);

        // Create token
        $token = base64_encode($user->user_id . '|' . $user->email . '|' . now());

        return response()->json([
            'success' => true,
            'message' => 'Registration successful! Your tank has been set up.',
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
        try {
            \Log::info('PairTank started', ['product_id' => $request->product_id]);

            // Validate input
            $request->validate([
                'product_id' => 'required|string|exists:purchases,purchase_id'
            ]);

            $productId = $request->product_id;
            \Log::info('Validated product_id', ['product_id' => $productId]);

            // Check if ProductID exists and is not activated
            $purchase = DB::table('purchases')
                ->where('purchase_id', $productId)
                ->first();

            \Log::info('Purchase found', ['purchase' => $purchase]);

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
            \Log::info('Token received', ['token' => $token]);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token missing'
                ], 401);
            }

            $parts = explode('|', base64_decode($token));
            $userId = $parts[0] ?? null;
            \Log::info('User ID extracted', ['user_id' => $userId]);

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'Unauthorized'
                ], 401);
            }

            // Check if this ProductID is already linked to a tank
            $existingTank = DB::table('tanks')
                ->where('ProductID', $productId)
                ->first();

            if ($existingTank) {
                return response()->json([
                    'success' => false,
                    'message' => 'This Product ID is already linked to a tank'
                ], 409);
            }

            // Create a new tank
            \Log::info('Creating tank', ['product_id' => $productId]);

            $tankId = DB::table('tanks')->insertGetId([
                'ProductID' => $productId,
                'Tankname' => 'Tank ' . $productId
            ]);

            \Log::info('Tank created', ['tank_id' => $tankId]);

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

            \Log::info('Dashboard entry created');

            // Mark ProductID as activated
            DB::table('purchases')
                ->where('purchase_id', $productId)
                ->update(['is_activated' => 1]);

            \Log::info('Purchase activated');

            return response()->json([
                'success' => true,
                'message' => 'Tank paired successfully!',
                'tank' => [
                    'TankID' => $tankId,
                    'ProductID' => $productId,
                    'Tankname' => 'Tank ' . $productId
                ]
            ], 201);

        } catch (\Exception $e) {
            \Log::error('PairTank error', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Server error: ' . $e->getMessage()
            ], 500);
        }
    }
}