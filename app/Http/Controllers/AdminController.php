<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use App\Models\Inspector;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendCredentials;
use App\Models\Inspection;
use Illuminate\Support\Facades\Auth;

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

        // Hide the password before returning the user
        $user->makeHidden('password');

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
        ]);

        // Check if an inspector with the given email already exists
        if (Inspector::where('email', $data['email'])->exists()) {
            return response()->json([
                'status' => 409,
                'message' => 'Account already in use. Please use a different email.'
            ], 409);
        }

        // Generate a random 8-character password with symbols
        $password = Str::random(8);

        // Hash the generated password
        $data['password'] = Hash::make($password);

        // Add admin_id to the data array from the logged-in admin
        $data['admin_id'] = auth()->user()->admin_id;

        // Create the inspector instance but do not save it yet
        $inspector = new Inspector($data);

        $toEmail = $data['email'];
        $toName = $data['first_name'] . ' ' . $data['last_name'];

        // Prepare the message
        $message = 'Your Inspector account has been created successfully. Your email is ' . $toEmail . ' and your password is ' . $password . '. Please change your password after logging in.';

        try {
            // Attempt to send email
            Mail::to($toEmail)->send(new SendCredentials($message, $toName));

            // Save inspector only if email was sent successfully
            $inspector->save();

            return response()->json([
                'status' => 200,
                'message' => 'Inspector created successfully',
                'inspector' => $inspector
            ]);
        } catch (\Exception $e) {
            // Handle email sending failure
            return response()->json([
                'status' => 500,
                'message' => 'Failed to send email. Inspector account was not created.',
                'error' => $e->getMessage(),
            ], 500);
        }
    }


    public function inspectors()
    {
        // Fetch all inspectors with the count of inspections
        $inspectors = Inspector::withCount('inspections')->get()->makeHidden('password');

        return response()->json([
            'status' => 200,
            'inspectors' => $inspectors,
        ]);
    }


    public function inspectorsWithInspectionsCount()
    {
        // Get all inspectors with the count of inspections they conducted
        $inspectors = Inspector::withCount('inspections')->get()->makeHidden('password');

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
}
