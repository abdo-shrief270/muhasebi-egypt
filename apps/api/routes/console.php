<?php

use Illuminate\Support\Facades\Schedule;

// Safety net for the outbox: normally events are published right after commit.
Schedule::command('events:relay')->everyFiveSeconds()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Customer data retention (Personal Data Protection Law 151/2020): shops that set a period erase inactive customers.
Schedule::command('customers:erase-inactive')->dailyAt('03:17')->withoutOverlapping();
