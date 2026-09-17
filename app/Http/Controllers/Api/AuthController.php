<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
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

    public function tankDetail(Request $request, $tankId)
    {
        try {
            // Validate Token
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            $parts = explode('|', base64_decode($token));
            $userId = $parts[0] ?? null;

            if (!$userId) {
                return response()->json(['success' => false, 'message' => 'Invalid token'], 401);
            }

            // Get tank info + dashboard data
            $tank = DB::table('tanks')
                ->join('dashboard', 'tanks.TankID', '=', 'dashboard.TankID')
                ->where('tanks.TankID', $tankId)
                ->where('dashboard.UserID', $userId)
                ->select(
                    'tanks.TankID',
                    'tanks.Tankname',
                    'dashboard.Mode',
                    'dashboard.Temperature',
                    'dashboard.Ph_Level',
                    'dashboard.Turbidity',
                    'dashboard.Status'
                )
                ->first();

            if (!$tank) {
                return response()->json([
                    'success' => false,
                    'message' => 'Tank not found'
                ], 404);
            }

            // Get the latest prediction
            $prediction = DB::table('predictions')
                ->where('tank_id', $tankId)
                ->orderby('created_at', 'desc')
                ->first();

            // Get the latest sensor reading timestamp
            $latestReading = DB::table('sensor_data')
                ->where('tank_id', $tankId)
                ->orderby('timestamp', 'desc')
                ->first();

            // Combine into one response
            return response()->json([
                'success' => true,
                'data' => [
                    'TankID' => $tank->TankID,
                    'Tankname' => $tank->Tankname,
                    'Mode' => $tank->Mode,
                    'Temperature' => $tank->Temperature,
                    'Ph_Level' => $tank->Ph_Level,
                    'Turbidity' => $tank->Turbidity,
                    'Status' => $tank->Status,
                    'TimeToDanger' => $prediction ? $prediction->minutes_to_danger . 'minutes' : null,
                    'LastUpdated' => $latestReading ? $latestReading->timestamp : null,
                ]
            ]);
        } catch (\Exception $e) {
            Log::info('TankDetail error', ['message' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Server error'
            ], 500);
        }
    }

    public function pairTank(Request $request)
    {
        try {
            Log::info('PairTank started', ['product_id' => $request->product_id]);

            // Validate input
            $request->validate([
                'product_id' => 'required|string|exists:purchases,purchase_id'
            ]);

            $productId = $request->product_id;
            Log::info('Validated product_id', ['product_id' => $productId]);

            // Check if ProductID exists and is not activated
            $purchase = DB::table('purchases')
                ->where('purchase_id', $productId)
                ->first();

            Log::info('Purchase found', ['purchase' => $purchase]);

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
            Log::info('Token received', ['token' => $token]);

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token missing'
                ], 401);
            }

            $parts = explode('|', base64_decode($token));
            $userId = $parts[0] ?? null;
            Log::info('User ID extracted', ['user_id' => $userId]);

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
            Log::info('Creating tank', ['product_id' => $productId]);

            $tankId = DB::table('tanks')->insertGetId([
                'ProductID' => $productId,
                'Tankname' => 'Tank ' . $productId
            ]);

            Log::info('Tank created', ['tank_id' => $tankId]);

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

            Log::info('Dashboard entry created');

            // Mark ProductID as activated
            DB::table('purchases')
                ->where('purchase_id', $productId)
                ->update(['is_activated' => 1]);

            Log::info('Purchase activated');

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
            Log::error('PairTank error', [
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

    public function timeToDanger(Request $request, $tankId)
    {
        try {
            // 1. Verify token (same pattern)
            $token = $request->bearerToken();
            if (!$token) {
                return response()->json(['success' => false, 'message' => 'Unauthorized'], 401);
            }

            // 2. Fetch the last 21 sensor readings for this tank
            $readings = DB::table('sensor_data')
                ->where('tank_id', $tankId)
                ->orderBy('timestamp', 'desc')
                ->limit(21)
                ->get()
                ->reverse()
                ->values();

            if ($readings->count() < 21) {
                return response()->json([
                    'success' => false,
                    'message' => 'Not enough data. Need at least 21 readings.'
                ], 400);
            }

            // 3. Get tank's current mode
            $tank = DB::table('dashboard')
                ->where('TankID', $tankId)
                ->first();

            $mode = $tank->Mode ?? 'Growing';

            // 4. Define thresholds for each parameter based on mode
            $thresholds = $mode === 'Breeding' 
                ? ['temperature' => [18, 26], 'ph' => [6.8, 8.7], 'turbidity' => [0, 70]]
                : ['temperature' => [20, 28], 'ph' => [6.5, 8.5], 'turbidity' => [0, 100]];

            // 5. Compute Time-to-Danger for each parameter
            $result = [
                'temperature' => $this->computeTimeToDanger(
                    $readings->pluck('temperature')->toArray(),
                    $readings->pluck('timestamp')->toArray(),
                    $thresholds['temperature']
                ),
                'ph' => $this->computeTimeToDanger(
                    $readings->pluck('ph_level')->toArray(),
                    $readings->pluck('timestamp')->toArray(),
                    $thresholds['ph']
                ),
                'turbidity' => $this->computeTimeToDanger(
                    $readings->pluck('turbidity')->toArray(),
                    $readings->pluck('timestamp')->toArray(),
                    $thresholds['turbidity']
                ),
            ];

            return response()->json([
                'success' => true,
                'data' => $result,
                'message' => 'Time-to-Danger computed'
            ]);

        } catch (\Exception $e) {
            Log::error('TimeToDanger error', ['message' => $e->getMessage()]);
            return response()->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    /**
     * Compute time (in minutes) until the value crosses a threshold.
     * Returns null if no danger is predicted.
     */
    private function computeTimeToDanger(array $values, array $timestamps, array $range)
    {
        // 1. Fit the multiple linear regression model
        $coefficients = $this->fitMLR($values, $timestamps);
        if ($coefficients === null) return null;

        [$b, $m1, $m2, $m3] = $coefficients;

        // 2. Get the reference time (first reading)
        $firstTimestamp = strtotime($timestamps[0]);
        $now = time();
        $minutesSinceStart = ($now - $firstTimestamp) / 60;

        // 3. Iterate forward up to 30 days
        $maxMinutes = 30 * 24 * 60;
        for ($futureMinutes = 8; $futureMinutes <= $maxMinutes; $futureMinutes += 8) {
            $totalMinutes = $minutesSinceStart + $futureMinutes;
            $futureTimestamp = $now + ($futureMinutes * 60);
            $futureHour = (int)date('G', $futureTimestamp);  // 0-23

            $predicted = $b
                + $m1 * $totalMinutes
                + $m2 * sin(2 * M_PI * $futureHour / 24)
                + $m3 * cos(2 * M_PI * $futureHour / 24);

            if ($predicted >= $range[1] || $predicted <= $range[0]) {
                return [
                    'minutes' => $futureMinutes,
                    'predicted_value' => round($predicted, 2),
                    'breach_type' => $predicted >= $range[1] ? 'high' : 'low',
                ];
            }
        }

        return null;
    }

    /**
     * Fit y = b + m1*t + m2*sin(2πh/24) + m3*cos(2πh/24)
     * using ordinary least squares.
     */
    private function fitMLR(array $values, array $timestamps)
    {
        $n = count($values);
        if ($n < 4) return null;

        $startTime = strtotime($timestamps[0]);

        // Build the design matrix X (n × 4) and target vector y (n × 1)
        $X = [];
        $y = [];
        for ($i = 0; $i < $n; $i++) {
            $t = (strtotime($timestamps[$i]) - $startTime) / 60;  // minutes
            $h = (int)date('G', strtotime($timestamps[$i]));
            $X[] = [1, $t, sin(2 * M_PI * $h / 24), cos(2 * M_PI * $h / 24)];
            $y[] = $values[$i];
        }

        // Compute X^T · X (4×4 matrix) and X^T · y (4×1 vector)
        $XtX = array_fill(0, 4, array_fill(0, 4, 0.0));
        $Xty = array_fill(0, 4, 0.0);

        for ($i = 0; $i < $n; $i++) {
            for ($j = 0; $j < 4; $j++) {
                for ($k = 0; $k < 4; $k++) {
                    $XtX[$j][$k] += $X[$i][$j] * $X[$i][$k];
                }
                $Xty[$j] += $X[$i][$j] * $y[$i];
            }
        }

        // Solve the 4×4 system using Gaussian elimination
        return $this->solveLinearSystem($XtX, $Xty);
    }

    /**
     * Solve Ax = b for x using Gaussian elimination with partial pivoting.
     */
    private function solveLinearSystem(array $A, array $b)
    {
        $n = count($b);

        // Augmented matrix [A | b]
        for ($i = 0; $i < $n; $i++) {
            $A[$i][] = $b[$i];
        }

        // Forward elimination
        for ($i = 0; $i < $n; $i++) {
            // Find pivot
            $maxRow = $i;
            for ($k = $i + 1; $k < $n; $k++) {
                if (abs($A[$k][$i]) > abs($A[$maxRow][$i])) $maxRow = $k;
            }
            if (abs($A[$maxRow][$i]) < 1e-10) return null;  // singular

            // Swap rows
            [$A[$i], $A[$maxRow]] = [$A[$maxRow], $A[$i]];

            // Eliminate
            for ($k = $i + 1; $k < $n; $k++) {
                $factor = $A[$k][$i] / $A[$i][$i];
                for ($j = $i; $j <= $n; $j++) {
                    $A[$k][$j] -= $factor * $A[$i][$j];
                }
            }
        }

        // Back substitution
        $x = array_fill(0, $n, 0.0);
        for ($i = $n - 1; $i >= 0; $i--) {
            $sum = $A[$i][$n];
            for ($j = $i + 1; $j < $n; $j++) {
                $sum -= $A[$i][$j] * $x[$j];
            }
            $x[$i] = $sum / $A[$i][$i];
        }

        return $x;
    }
}