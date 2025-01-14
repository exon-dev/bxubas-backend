<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\BusinessOwner;
use App\Models\Inspection;
use App\Models\Notification;

// todo implement notification logic

class NotificationController extends Controller
{
    // call this function upon creating inspection with violation
    public function sentNotifications(Request $request)
    {
        //find inspections with violations and with no id in notifications table
        $inspections = Inspection::where('with_violations', true)->whereDoesntHave('notifications')->get();

        // get business owner information
        $businessOwner = BusinessOwner::find($inspections->business_id);

        // logic for sending notification via twillio
        $message = "Notification sent successfully";

        return response()->json(['message' => $message], 200);
    }


    public function sendReminder(){
        // send reminder to business owners
        $message = "Reminder sent successfully";
        return response()->json(['message' => $message], 200);
    }

    public function sendNotification()
    {
        // display sent notificications to business owners
        $notifications = Notification::all();
    }
}
