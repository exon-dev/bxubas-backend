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

        // Log the start of the process
        Log::info('Starting violation reminder process', [
            'date_range' => [
                'start' => $now->toDateTimeString(),
                'end' => $threeDaysFromNow->toDateTimeString()
            ]
        ]);

        // Use chunk to handle large datasets
        DB::table('violations')
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
            ->whereRaw("LOWER(violations.status) = ?", ['pending'])
            ->orderBy('violations.violation_id')
            ->chunk(100, function ($violations) use ($notificationController) {
                foreach ($violations as $violation) {
                    // Check if a reminder notification exists
                    $reminderExists = DB::table('notifications')
                        ->where('violation_id', $violation->violation_id)
                        ->where('title', 'Upcoming Due Date Reminder')
                        ->exists();

                    if ($reminderExists) {
                        $this->line("⚠ Reminder already sent for violation ID: {$violation->violation_id} - Skipping");
                        continue;
                    }

                    $this->info("📤 Sending reminder for violation ID: {$violation->violation_id}");

                    // Create notification message
                    $message = "Subject: Reminder: Visit the BPLD\n\n"
                        . "Dear {$violation->first_name} {$violation->last_name},\n\n"
                        . "This is a friendly reminder from the Business Permit and Licensing Department (BPLD) regarding your pending matter. "
                        . "Kindly visit our office by {$violation->due_date}, and reference receipt number {$violation->violation_receipt_no} for assistance.\n\n"
                        . "If you have any questions or require further clarification, please contact us directly.\n\n"
                        . "Thank you for your prompt attention to this matter.\n\n"
                        . "Best regards,\n"
                        . "Business Permit and Licensing Department";

                    try {
                        // Send notification
                        $notificationResult = $notificationController->sendNotification(new Request([
                            'phone' => $violation->phone_number,
                            'message' => $message,
                        ]));

                        // Create notification record
                        DB::table('notifications')->insert([
                            'title' => 'Upcoming Due Date Reminder',
                            'content' => $message,
                            'violator_id' => $violation->business_owner_id,
                            'violation_id' => $violation->violation_id,
                            'type' => 'reminder',
                            'status' => $notificationResult['status'] === 200 ? 'sent' : 'failed',
                            'error_message' => $notificationResult['status'] === 200 ? null : ($notificationResult['message'] ?? 'Unknown error'),
                            'created_at' => now(),
                            'updated_at' => now()
                        ]);

                        if ($notificationResult['status'] === 200) {
                            $this->info("✓ Reminder SMS sent successfully to {$violation->phone_number}");
                        } else {
                            $this->error("✗ Failed to send reminder SMS to {$violation->phone_number}: " . ($notificationResult['message'] ?? 'Unknown error'));
                        }

                        // Log the attempt
                        Log::info('Reminder attempt:', [
                            'violation_id' => $violation->violation_id,
                            'phone' => $violation->phone_number,
                            'status' => $notificationResult['status'] === 200 ? 'sent' : 'failed',
                            'error' => $notificationResult['status'] === 200 ? null : ($notificationResult['message'] ?? 'Unknown error')
                        ]);
                    } catch (\Exception $e) {
                        Log::error('Error processing violation reminder:', [
                            'violation_id' => $violation->violation_id,
                            'error' => $e->getMessage()
                        ]);
                        $this->error("Error processing violation ID {$violation->violation_id}: {$e->getMessage()}");
                    }
                }
            });

        Log::info('Violation reminders processed successfully.');
    }
}
