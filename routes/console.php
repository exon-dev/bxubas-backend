<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote')->hourly();


Artisan::command('send-violation-reminder', function () {
    $this->info('Sending violation reminder to business owners less than 3 days from due date');
})->purpose('Send violation reminders to business reminders')->everyMinute();
