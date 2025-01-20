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

        $twilioSid = env('TWILIO_SID');
        $twilioAuthToken = env('TWILIO_AUTH_TOKEN');
        $twilioNumber = env('TWILIO_NUMBER');
        $to = $request->phone;
        $messageBody = $request->message;

        try {
            $client = new Client($twilioSid, $twilioAuthToken);
            $client->messages->create($to, [
                'from' => $twilioNumber,
                'body' => $messageBody,
            ]);

            Log::info("Notification sent successfully to {$to}");

            return response()->json([
                'status' => 200,
                'message' => 'Message sent successfully!',
            ]);
        } catch (Exception $e) {
            Log::error("Error sending notification to {$to}: {$e->getMessage()}");

            return response()->json([
                'status' => 500,
                'message' => 'Error sending message.',
                'error' => $e->getMessage(),
            ], 500);
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
