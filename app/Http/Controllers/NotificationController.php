<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessOwner;
use App\Models\Inspection;
use App\Models\Notification;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Http;

class NotificationController extends Controller
{
    /**
     * View all sent notifications.
     */
    public function sentMessage()
    {
        $data = Notification::all();

        return response()->json([
            'status' => 200,
            'message' => 'All sent messages retrieved successfully.',
            'data' => $data,
        ]);
    }

    /**
     * Send a notification via Twilio SMS.
     */
    public function sendNotification(Request $request, $callback = null)
    {
        // Validate the incoming request
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $endpoint = 'https://sms.iprogtech.com/api/v1/sms_messages'; // Updated endpoint
        $apiToken = config('services.philsms.api_token');

        // Log the API token for debugging
        Log::debug('API Token:', ['token' => $apiToken]);

        // Prepare headers
        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        // Prepare the payload
        $payload = [
            'api_token' => $apiToken, // Token from .env
            'phone_number' => $request->phone, // Updated to match API docs
            'message' => $request->message, // Message content
        ];

        try {
            // Send the request to the API
            $response = Http::withHeaders($headers)->post($endpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();
                Log::info("API Response:", ['response' => $responseData]);

                if (isset($responseData['status']) && $responseData['status'] == 200) {
                    Log::info("Notification sent successfully to {$request->phone}");
                    return response()->json([
                        'status' => 200,
                        'message' => 'Message sent successfully!',
                    ]);
                } else {
                    Log::error("Error sending notification to {$request->phone}: " . $response->body());
                    // Return a success message even if SMS sending failed
                    return response()->json([
                        'status' => 200,
                        'message' => 'Inspection created successfully, but SMS notification failed.',
                    ]);
                }
            } else {
                Log::error("Error sending notification to {$request->phone}: " . $response->body());
                // Return a success message even if SMS sending failed
                return response()->json([
                    'status' => 200,
                    'message' => 'Inspection created successfully, but SMS notification failed.',
                ]);
            }
        } catch (Exception $e) {
            Log::error("Error sending notification to {$request->phone}: {$e->getMessage()}");
            // Return a success message even if an exception occurred
            return response()->json([
                'status' => 200,
                'message' => 'Inspection created successfully, but there was an error sending the SMS notification.',
            ]);
        }
    }





    /**
     * Send notifications for inspections almost due.
     */
    public function inspectionAlmostDueNotif()
    {
        $thresholdDate = Carbon::now()->addDays(3); // Define the threshold as 3 days from today.

        $violations = Inspection::with('violations')
            ->whereHas('violations', function ($query) use ($thresholdDate) {
                $query->where('due_date', '<=', $thresholdDate)
                    ->where('status', 'pending');
            })
            ->get();

        foreach ($violations as $inspection) {
            $businessOwner = $inspection->business->owner;

            // Check if a notification has already been sent
            $alreadyNotified = Notification::where('violation_id', $inspection->violations->first()->violation_id)
                ->exists();

            if (!$alreadyNotified) {
                $message = "Dear {$businessOwner->first_name}, your business '{$inspection->business->business_name}' has a pending violation due on {$inspection->violations->first()->due_date}. Please address it promptly.";

                $this->sendNotification(new Request([
                    'phone' => $businessOwner->phone_number,
                    'message' => $message,
                ]));

                Notification::create([
                    'title' => 'Inspection Due Reminder',
                    'content' => $message,
                    'violator_id' => $businessOwner->business_owner_id,
                    'violation_id' => $inspection->violations->first()->violation_id,
                ]);

                Log::info("Inspection reminder sent to {$businessOwner->phone_number}");
            }
        }

        return response()->json([
            'status' => 200,
            'message' => 'Inspection due notifications processed successfully.',
        ]);
    }
}
