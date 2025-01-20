<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessOwner;
use App\Models\Inspection;
use App\Models\Notification;
use Twilio\Rest\Client;
use Exception;

// todo implement notification logic

class NotificationController extends Controller
{
    // send notification of inspection with violation

    public function sentMessage()
    {
        // view all sent message
        $data = Notification::all();

        return response()->json([
            'status' => 200,
            'message' => 'All sent message',
            'data' => $data
        ]);
    }

    public function sendNotification(Request $request)
    {
        // Retrieve Twilio credentials from the .env file
        $twilioSid = env('TWILIO_SID');
        $twilioAuthToken = env('TWILIO_AUTH_TOKEN');
        $twilioNumber = env('TWILIO_NUMBER');
        // Extract the phone number and message from the request.
        $to = $request->phone;
        $messageBody = $request->message;
        try {
            // Create a new Twilio client using the retrieved credentials
            $client = new Client($twilioSid, $twilioAuthToken);
            // Send the SMS message to the specified phone number
            $client->messages->create($to, [
                'from' => $twilioNumber, // Twilio phone number
                'body' => $messageBody // The message text
            ]);
            // Return success message if the message is sent successfully
            return "Message sent successfully!";
        } catch (Exception $e) {
            // Handle any errors that occur during message sending and return an error message
            return "Error sending message: " . $e->getMessage();
        }
    }

    public function inspectionAlmostDueNotif()
    {
        // if inspection is almost due send notification
        // find violation with due date less than 3 days
        // check inspection_id is already sent notification
        // if not send notification
    }
}
