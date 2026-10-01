<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for the outbox: normally events are published right after commit.
Schedule::command('events:relay')->everyFiveSeconds()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Customer data retention (Personal Data Protection Law 151/2020): shops that set a period erase inactive customers.
Schedule::command('customers:erase-inactive')->dailyAt('03:17')->withoutOverlapping();

// Erased used-device sellers: their national ID and card photos go when the anti-theft retention period ends.
Schedule::command('used-devices:purge-ids')->dailyAt('03:29')->withoutOverlapping();

// Owner app: each shop's end-of-day summary, once its hour (owner_app.daily_summary) has come.
Schedule::command('notifications:daily-summary')->everyTenMinutes()->withoutOverlapping();
