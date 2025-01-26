<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

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
        // Set the due date for 3 days from now
        $threeDaysFromNow = Carbon::now()->addDays(3)->startOfDay();

        // Query violations where due_date is 3 days away
        $violations = DB::table('violations')
            ->whereDate('due_date', $threeDaysFromNow)
            ->get();

        foreach ($violations as $violation) {
            // Check if a reminder already exists for this violation
            $existingNotification = DB::table('notifications')
                ->where('violation_id', $violation->violation_id)
                ->exists();

            if ($existingNotification) {
                Log::info('Reminder already exists for violation ID: ' . $violation->violation_id);
                continue; // Skip if a notification already exists
            }

            // Get violator details
            $violator = DB::table('business_owner')
                ->where('business_owner_id', $violation->violator_id)
                ->first();

            if ($violator) {
                // Create a new notification
                DB::table('notifications')->insert([
                    'title' => 'Upcoming Due Date Reminder',
                    'content' => 'This is a reminder that your violation (' . $violation->violation_receipt . ') is due in 3 days. Please take immediate action to resolve it.',
                    'violator_id' => $violation->violator_id,
                    'violation_id' => $violation->violation_id,
                    'timestamps' => now(),
                ]);

                // Log or send the reminder (email/SMS)
                Log::info('Reminder sent to: ' . $violator->email);
                // Add your email/SMS sending logic here
                // Example: Mail::to($violator->email)->send(new ViolationReminderMail($violation));
            }
        }

        Log::info('Violation reminders processed successfully.');
    }
}
