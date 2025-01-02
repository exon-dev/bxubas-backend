<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Inspector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function registerAdmin(Request $request)
    {
        // Validate the request data
        $data = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // Hash the password before saving
        $data['password'] = Hash::make($data['password']);

        // Create new admin, the UUID will be generated automatically in the model
        $admin = Admin::create($data);

        // Return success response
        return response()->json([
            'status' => 200,
            'message' => 'Admin created successfully',
            'admin' => $admin  // Optionally, return the created admin details
        ]);
    }

    public function login(Request $request)
    {
        // Validate the login credentials
        $request->validate([
            'email' => 'required|email',
            'password' => 'required'
        ]);

        // Retrieve the user by email
        $user = Admin::where('email', $request->email)->first();

        if (!$user) {
            return response(['message' => 'Admin account does not exist!'], 404);
        }

        // Check if the password is correct
        if (!Hash::check($request->password, $user->password)) {
            return response([
                'message' => 'Invalid credentials'
            ], 401);
        }

        // Authentication successful
        $token = $user->createToken('auth_token', ['admin'])->plainTextToken;

        // Return successful login response
        return response()->json([
            'message' => 'Login successful',
            'token' => $token,
            'user' => $user
        ], 200);
    }

    public function createInspector(Request $request)
    {
        // Validate input data
        $data = $request->validate([
            'first_name' => 'required',
            'last_name' => 'required',
            'email' => 'required|email',
            'password' => 'required|min:8',
        ]);

        // Hash password
        $data['password'] = Hash::make($data['password']);

        // Add admin_id to the data array from the logged-in admin
        $data['admin_id'] = auth()->user()->admin_id;

        // Create inspector
        $inspector = Inspector::create($data);

        // Return success response
        return response()->json([
            'status' => 200,
            'message' => 'Inspector created successfully',
            'inspector' => $inspector
        ]);
    }

    public function inspectors()
    {
        // Get all inspectors without passwords
        $inspectors = Inspector::all()->makeHidden('password');

        // Return success response
        return response()->json([
            'status' => 200,
            'inspectors' => $inspectors
        ]);
    }

    public function deleteInspector(Request $request)
    {
        // Implement delete inspector logic
        $inspector = Inspector::find($request->inspector_id);

        if (!$inspector) {
            return response()->json([
                'status' => 404,
                'message' => 'Inspector not found'
            ]);
        }

        $inspector->delete();

        return response()->json([
            'status' => 200,
            'message' => 'Inspector deleted successfully'
        ]);

    }

    public function logout(Request $request)
    {
        // Implement logout logic
    }
}
