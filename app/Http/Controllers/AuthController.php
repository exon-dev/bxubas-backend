<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Inspector;
use App\Models\Admin;
use App\Mail\SendPasswordReset;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;


class AuthController extends Controller
{

    public function logout(Request $request)
    {
        // Revoke the user's current token
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Logged out successfully'
        ]);
    }
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
        $plainToken = bin2hex(random_bytes(32)); // Generate a secure random token
        $hashedToken = Hash::make($plainToken); // Hash the token for storage

        // Encrypt email
        $encryptedEmail = encrypt($request->email); // Use Laravel's built-in encryption

        // Save token to the database
        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $request->email],
            [
                'token' => $hashedToken,
                'created_at' => now(),
            ]
        );

        // Create reset link with plain token and encrypted email
        $resetLink = url('http://127.0.0.1:5500/pages/auth/changePassword.html') . '?token=' . $plainToken . '&email=' . urlencode($encryptedEmail);

        // Prepare the email message
        $emailMessage = 'You have requested to reset your password. Please click the link below to reset your password:';

        // Send email
        Mail::to($request->email)->send(new SendPasswordReset($emailMessage, $recipientName, $resetLink));

        return response()->json(['message' => 'Password reset link sent.']);
    }
    public function changePassword(Request $request)
    {
        $request->validate([
            'token' => 'required',
            'email' => 'required', // No need to validate as email since it's encrypted
            'password' => 'required|min:8',
        ]);

        try {
            $decryptedEmail = decrypt($request->email); // Decrypt the email
        } catch (\Exception $e) {
            return response()->json(['message' => 'Invalid email encryption.'], 400);
        }

        // Retrieve reset record
        $resetRecord = DB::table('password_reset_tokens')
            ->where('email', $decryptedEmail)
            ->first();

        if (!$resetRecord || !Hash::check($request->token, $resetRecord->token)) {
            return response()->json(['message' => 'Invalid token or email.'], 400);
        }

        // Check token expiration (e.g., valid for 60 minutes)
        if (now()->diffInMinutes($resetRecord->created_at) > 60) {
            return response()->json(['message' => 'Token has expired.'], 400);
        }

        // Find user by email
        $user = Admin::where('email', $decryptedEmail)->first() ??
            Inspector::where('email', $decryptedEmail)->first();

        if (!$user) {
            return response()->json(['message' => 'User not found.'], 404);
        }

        // Update password
        $user->password = Hash::make($request->password);
        $user->save();

        // Delete the reset token
        DB::table('password_reset_tokens')->where('email', $decryptedEmail)->delete();

        return response()->json(['message' => 'Password reset successfully!']);
    }

    /**
     * Reset password for logged-in user
     */
    public function resetLoggedInPassword(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'current_password' => 'required|string|min:8',
                'new_password' => 'required|string|min:8|different:current_password',
                'confirm_password' => 'required|string|same:new_password'
            ], [
                'email.required' => 'The email field is required.',
                'email.email' => 'The email must be a valid email address.',
                'current_password.required' => 'The current password field is required.',
                'current_password.min' => 'The current password must be at least 8 characters.',
                'new_password.required' => 'The new password field is required.',
                'new_password.min' => 'The new password must be at least 8 characters.',
                'new_password.different' => 'The new password and current password must be different.',
                'confirm_password.required' => 'The confirm password field is required.',
                'confirm_password.same' => 'The confirm password and new password must match.'
            ]);

            // Check in Admin table
            $admin = Admin::where('email', $request->email)->first();

            // Check in Inspector table if not found in Admin
            $inspector = !$admin ? Inspector::where('email', $request->email)->first() : null;

            // If user not found in either table
            if (!$admin && !$inspector) {
                return response()->json([
                    'status' => 404,
                    'message' => 'User not found with this email.'
                ], 404);
            }

            $user = $admin ?: $inspector;

            // Verify current password
            if (!Hash::check($request->current_password, $user->password)) {
                return response()->json([
                    'status' => 400,
                    'message' => 'Current password is incorrect.'
                ], 400);
            }

            // Update password
            $user->password = Hash::make($request->new_password);
            $user->save();

            return response()->json([
                'status' => 200,
                'message' => 'Password updated successfully.'
            ]);

        } catch (\Illuminate\Validation\ValidationException $e) {
            return response()->json([
                'status' => 422,
                'message' => $e->getMessage(),
                'errors' => $e->errors()    
            ], 422);
        } catch (\Exception $e) {
            Log::info($e);
            return response()->json([
                'status' => 500,
                'message' => 'Error updating password.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
