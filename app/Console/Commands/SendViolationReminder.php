<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Http\Controllers\NotificationController;
use Illuminate\Http\Request;

class SendViolationReminder extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'app:send-violation-reminder';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Violators with 3 days remaining to resolve their violations will receive a reminder email or SMS message.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $notificationController = new NotificationController();

        // Set the date range from now to 3 days from now
        $now = Carbon::now()->startOfDay();
        $threeDaysFromNow = Carbon::now()->addDays(3)->endOfDay();

        // Get violations with their business owners in one query
        $violations = DB::table('violations')
            ->join('businesses', 'violations.business_id', '=', 'businesses.business_id')
            ->join('business_owners', 'businesses.owner_id', '=', 'business_owners.business_owner_id')
            ->select(
                'violations.*',
                'business_owners.first_name',
                'business_owners.last_name',
                'business_owners.phone_number',
                'business_owners.business_owner_id'
            )
            ->whereBetween('violations.due_date', [$now, $threeDaysFromNow])
            ->where('violations.status', 'pending')
            ->get();

        $this->info("Found " . $violations->count() . " violations due within the next 3 days");

        foreach ($violations as $violation) {
            // Check if a reminder notification (specifically) exists
            $reminderCount = DB::table('notifications')
                ->where('violation_id', $violation->violation_id)
                ->where('title', 'Upcoming Due Date Reminder')
                ->count();

            if ($reminderCount >= 1) {
                $this->line("⚠ Reminder already sent for violation ID: {$violation->violation_id} - Skipping");
                continue;
            }

            $this->info("📤 Sending reminder for violation ID: {$violation->violation_id}");

            // Create notification message using the joined data
            $message = "Subject: Reminder: Visit the BPLD\n\n"
                . "Dear {$violation->first_name} {$violation->last_name},\n\n"
                . "This is a friendly reminder from the Business Permit and Licensing Department (BPLD) regarding your pending matter. "
                . "Kindly visit our office by {$violation->due_date}, and reference receipt number {$violation->violation_receipt_no} for assistance.\n\n"
                . "If you have any questions or require further clarification, please contact us directly.\n\n"
                . "Thank you for your prompt attention to this matter.\n\n"
                . "Best regards,\n"
                . "Business Permit and Licensing Department";

            // Send SMS notification and capture the result
            $notificationResult = $notificationController->sendNotification(new Request([
                'phone' => $violation->phone_number,
                'message' => $message,
            ]));

            // Log the complete notification attempt
            Log::info('SMS Notification Attempt:', [
                'violator_id' => $violation->business_owner_id,
                'phone' => $violation->phone_number,
                'response' => $notificationResult,
            ]);

            if ($notificationResult['status'] === 200) {
                // Create a new notification record with specific title
                DB::table('notifications')->insert([
                    'title' => 'Upcoming Due Date Reminder', // This distinguishes it from the initial notification
                    'content' => $message,
                    'violator_id' => $violation->violator_id,
                    'violation_id' => $violation->violation_id,
                    'type' => 'reminder', // You might need to add this column to your notifications table
                    'timestamps' => now(),
                ]);

                $this->info("✓ Reminder SMS sent successfully to {$violation->phone_number}");
            } else {
                $this->error("✗ Failed to send SMS to {$violation->phone_number}: " . ($notificationResult['message'] ?? 'Unknown error'));
                Log::error('SMS Sending Failed:', [
                    'phone' => $violation->phone_number,
                    'error' => $notificationResult['message'] ?? 'Unknown error'
                ]);
            }
        }

        Log::info('Violation reminders processed successfully.');
    }
}
