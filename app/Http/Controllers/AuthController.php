<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use App\Models\Admin;
use App\Mail\SendPasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Mailable\SendCredentials;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class AuthController extends Controller
{
    public function changePasswordRequest(Request $request)
    {
        $request->validate(['email' => 'required|email']);

        // Check user type (admin or inspector)
        $admin = Admin::where('email', $request->email)->first();
        $inspector = Inspector::where('email', $request->email)->first();

        if (!$admin && !$inspector) {
            return response()->json(['message' => 'Email not found.'], 404);
        }

        // Get user for token generation (admin or inspector)
        $user = $admin ?? $inspector;

        // Concatenate first_name and last_name or use email as a fallback
        $recipientName = trim("{$user->first_name} {$user->last_name}") ?: $user->email;

        // Generate reset token
        $token = Password::broker()->createToken($user);

        // Prepare reset link
        $resetLink = url('http://127.0.0.1:5500/pages/auth/changePassword.html', $token);

        // Prepare the email message
        $emailMessage = 'You have requested to reset your password. Please click the link below to reset your password:'; // Updated variable name

        // Send email
        Mail::to($request->email)->send(new SendPasswordReset($emailMessage, $recipientName, $resetLink)); // Updated variable name

        return response()->json(['message' => 'Password reset link sent.']);
    }

    public function changePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email',
            'token' => 'required',
            'password' => 'required|min:8|confirmed',
        ]);

        // Check user type
        $admin = Admin::where('email', $request->email)->first();
        $inspector = Inspector::where('email', $request->email)->first();

        if (!$admin && !$inspector) {
            return response()->json(['message' => 'Email not found.'], 404);
        }

        $user = $admin ?? $inspector;

        // Validate token (you'll need to implement token storage and validation)
        $isValidToken = Password::broker()->tokenExists($user, $request->token);
        if (!$isValidToken) {
            return response()->json(['message' => 'Invalid or expired token.'], 400);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        return response()->json(['message' => 'Password changed successfully.']);
    }
}
