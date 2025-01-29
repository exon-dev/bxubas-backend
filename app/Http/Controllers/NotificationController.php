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
    public function sentMessage(Request $request)
    {
        try {
            // Get query parameters with defaults
            $page = $request->input('page', 1);
            $perPage = $request->input('per_page', 15);
            $sortOrder = $request->input('sort_order', 'desc');
            $violationStatus = $request->input('violation_status');
            $notificationType = $request->input('type'); // 'initial' or 'reminder'
            $notificationStatus = $request->input('notification_status'); // 'pending', 'sent', or 'failed'
            $search = $request->input('search');

            // Start building the query
            $query = Notification::with(['violator', 'violation'])
                ->join('violations', 'notifications.violation_id', '=', 'violations.violation_id')
                ->select('notifications.*');

            // Apply violation status filter if provided
            if ($violationStatus && $violationStatus !== 'all') {
                $query->where('violations.status', $violationStatus);
            }

            // Apply notification type filter if provided
            if ($notificationType && $notificationType !== 'all') {
                $query->where('notifications.type', $notificationType);
            }

            // Apply notification status filter if provided
            if ($notificationStatus && $notificationStatus !== 'all') {
                $query->where('notifications.status', $notificationStatus);
            }

            // Apply search filter if provided
            if ($search) {
                $query->where(function ($q) use ($search) {
                    $q->where('notifications.content', 'like', "%{$search}%")
                        ->orWhereHas('violator', function ($q) use ($search) {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%");
                        })
                        ->orWhereHas('violation', function ($q) use ($search) {
                            $q->where('violation_receipt_no', 'like', "%{$search}%");
                        });
                });
            }

            // Apply sorting
            $query->orderBy('notifications.created_at', $sortOrder);

            // Paginate the results
            $notifications = $query->paginate($perPage);

            // Transform the data
            $transformedData = $notifications->through(function ($notification) {
                return [
                    'notification_id' => $notification->notification_id,
                    'type' => $notification->type,
                    'title' => $notification->title,
                    'content' => $notification->content,
                    'status' => $notification->status,
                    'error_message' => $notification->error_message,
                    'created_at' => $notification->created_at,
                    'violator' => [
                        'violator_id' => $notification->violator->business_owner_id,
                        'first_name' => $notification->violator->first_name,
                        'last_name' => $notification->violator->last_name,
                        'email' => $notification->violator->email,
                        'phone_number' => $notification->violator->phone_number,
                    ],
                    'violation' => [
                        'violation_id' => $notification->violation->violation_id,
                        'type_of_inspection' => $notification->violation->type_of_inspection,
                        'violation_receipt_no' => $notification->violation->violation_receipt_no,
                        'violation_date' => $notification->violation->violation_date,
                        'due_date' => $notification->violation->due_date,
                        'status' => $notification->violation->status,
                        'violation_status' => $notification->violation->violation_status,
                    ],
                ];
            });

            return response()->json([
                'status' => 200,
                'message' => 'All sent messages retrieved successfully.',
                'data' => $transformedData->items(),
                'pagination' => [
                    'current_page' => $notifications->currentPage(),
                    'per_page' => $notifications->perPage(),
                    'total' => $notifications->total(),
                    'last_page' => $notifications->lastPage(),
                    'from' => $notifications->firstItem(),
                    'to' => $notifications->lastItem(),
                ],
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 500,
                'message' => 'Error retrieving sent messages.',
                'error' => $e->getMessage()
            ], 500);
        }
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

            // Ensure the response is decoded as an array if it's JSON
            $responseData = $response->successful() ? $response->json() : [];

            Log::info('SMS API Response:', ['response' => $responseData]);

            if ($response->successful()) {
                if (isset($responseData['status']) && $responseData['status'] == 200) {
                    return [
                        'status' => 200,
                        'message' => 'Message sent successfully!'
                    ];
                } else {
                    return [
                        'status' => 500,
                        'message' => $responseData['message'] ?? 'Failed to send the SMS notification.'
                    ];
                }
            } else {
                return [
                    'status' => 500,
                    'message' => 'Failed to send the SMS notification due to an API error.'
                ];
            }
        } catch (Exception $e) {
            return [
                'status' => 500,
                'message' => 'An error occurred while trying to send the SMS notification.'
            ];
        }
    }


    // /**
    //  * Send <notificati></notificati>ons for inspections almost due.
    //  */
    // public function inspectionAlmostDueNotif()
    // {
    //     $thresholdDate = Carbon::now()->addDays(3); // Define the threshold as 3 days from today.

    //     $violations = Inspection::with('violations')
    //         ->whereHas('violations', function ($query) use ($thresholdDate) {
    //             $query->where('due_date', '<=', $thresholdDate)
    //                 ->where('status', 'pending');
    //         })
    //         ->get();

    //     foreach ($violations as $inspection) {
    //         $businessOwner = $inspection->business->owner;

    //         // Check if a notification has already been sent
    //         $alreadyNotified = Notification::where('violation_id', $inspection->violations->first()->violation_id)
    //             ->exists();

    //         if (!$alreadyNotified) {
    //             $message = "Dear {$businessOwner->first_name}, your business '{$inspection->business->business_name}' has a pending violation due on {$inspection->violations->first()->due_date}. Please address it promptly.";

    //             $this->sendNotification(new Request([
    //                 'phone' => $businessOwner->phone_number,
    //                 'message' => $message,
    //             ]));

    //             Notification::create([
    //                 'title' => 'Inspection Due Reminder',
    //                 'content' => $message,
    //                 'violator_id' => $businessOwner->business_owner_id,
    //                 'violation_id' => $inspection->violations->first()->violation_id,
    //             ]);

    //             Log::info("Inspection reminder sent to {$businessOwner->phone_number}");
    //         }
    //     }

    //     return response()->json([
    //         'status' => 200,
    //         'message' => 'Inspection due notifications processed successfully.',
    //     ]);
    // }
}
