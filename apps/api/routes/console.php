<?php

use App\Support\Monitoring\Health;
use App\Support\Monitoring\QueueHeartbeat;
use Illuminate\Support\Facades\Schedule;

// Safety net for the outbox: normally events are published right after commit.
Schedule::command('events:relay')->everyFiveSeconds()->withoutOverlapping();

Schedule::command('horizon:snapshot')->everyFiveMinutes();

// Heartbeats for /api/v1/health: the scheduler runs, and a queue worker picks up a job.
Schedule::call(fn () => Health::beat('scheduler'))->name('monitoring:scheduler-beat')->everyMinute();
Schedule::job(new QueueHeartbeat)->name('monitoring:queue-beat')->everyMinute();

// Customer data retention (Personal Data Protection Law 151/2020): shops that set a period erase inactive customers.
Schedule::command('customers:erase-inactive')->dailyAt('03:17')->withoutOverlapping();

// Erased used-device sellers: their national ID and card photos go when the anti-theft retention period ends.
Schedule::command('used-devices:purge-ids')->dailyAt('03:29')->withoutOverlapping();

// Renewal reminders to shop owners, mid-morning Cairo time (once per step and end date).
Schedule::command('billing:remind')->dailyAt('08:07')->withoutOverlapping();

// Import shipments past their expected arrival (once per expected date).
Schedule::command('imports:late-alerts')->dailyAt('07:13')->withoutOverlapping();

// Owner app: each shop's end-of-day summary, once its hour (owner_app.daily_summary) has come.
Schedule::command('notifications:daily-summary')->everyTenMinutes()->withoutOverlapping();

// «سوق محاسبي»: what changed in the shops (products, prices, stock) reaches the search index every minute,
// and the whole index is rebuilt from Postgres every night (no-ops while SEARCH_URL is empty).
Schedule::command('market:sync')->everyMinute()->withoutOverlapping(5);
Schedule::command('market:reindex')->dailyAt('04:11')->withoutOverlapping(120);
