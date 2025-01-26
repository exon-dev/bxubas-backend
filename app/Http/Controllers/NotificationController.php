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
    public function sendNotification(Request $request)
    {
        $request->validate([
            'phone' => 'required|string',
            'message' => 'required|string',
        ]);

        $endpoint = 'https://sms.iprogtech.com/api/v1/sms_messages';
        $apiToken = config('services.philsms.api_token');

        $headers = [
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ];

        $payload = [
            'api_token' => $apiToken,
            'phone_number' => $request->phone,
            'message' => $request->message,
        ];

        try {
            $response = Http::withHeaders($headers)->post($endpoint, $payload);

            if ($response->successful()) {
                $responseData = $response->json();

                if (isset($responseData['status']) && $responseData['status'] == 200) {
                    // Only log success if the API confirms
                    Log::info('Violation notification sent successfully.', ['business_owner' => $request->owner_email ?? null]);
                    return response()->json([
                        'status' => 200,
                        'message' => 'Message sent successfully!',
                    ]);
                } else {
                    // Log API response error
                    Log::error('Error sending notification: ' . $response->body());
                    return response()->json([
                        'status' => 500,
                        'message' => $responseData['message'] ?? 'Failed to send the SMS notification.',
                    ]);
                }
            } else {
                // Log non-successful API response
                Log::error('API Error: ' . $response->body());
                return response()->json([
                    'status' => 500,
                    'message' => 'Failed to send the SMS notification due to an API error.',
                ]);
            }
        } catch (Exception $e) {
            // Log exceptions
            Log::error('Exception: ' . $e->getMessage());
            return response()->json([
                'status' => 500,
                'message' => 'An error occurred while trying to send the SMS notification.',
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
