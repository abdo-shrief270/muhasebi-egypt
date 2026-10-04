<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

/**
 * Every uncaught server exception: grouped by where it was thrown (`server_errors`, count, last
 * seen; a resolved one reopens), and an alert the first time and then at most every 30 minutes
 * per error. Never throws: when the database itself is the problem, the alert still goes.
 */
final class ServerErrors
{
    /** Minutes between two alerts about the same error. */
    public const ALERT_EVERY = 30;

    public function __construct(private readonly Alerts $alerts) {}

    public function capture(Throwable $e): void
    {
        $file = self::relative($e->getFile()).':'.$e->getLine();
        $fingerprint = sha1(get_class($e).'|'.$file);
        $message = self::scrub($e->getMessage());
        $context = self::context();

        $count = null;
        try {
            $count = $this->record($e, $fingerprint, $file, $message, $context);
        } catch (Throwable) {
            // The database is down or broken: the alert below says so.
        }

        try {
            $first = Cache::add("monitoring:alerted:{$fingerprint}", 1, now()->addMinutes(self::ALERT_EVERY));
        } catch (Throwable) {
            $first = true;
        }
        if ($first) {
            $this->alerts->send(implode("\n", array_filter([
                '🔴 خطأ في السيرفر'.($count !== null && $count > 1 ? " (حصل {$count} مرة)" : ''),
                class_basename($e).': '.Str::limit($message, 300),
                $file,
                $context,
            ])));
        }
    }

    /** @return int how many times it happened */
    private function record(Throwable $e, string $fingerprint, string $file, string $message, ?string $context): int
    {
        $now = now();
        $tenant = app(CurrentTenant::class)->id();
        $trace = Str::limit(self::scrub($e->getTraceAsString()), 6000, '');
        $updated = DB::table('server_errors')->where('fingerprint', $fingerprint)->update([
            'count' => DB::raw('count + 1'),
            'message' => mb_substr($message, 0, 500),
            'trace' => $trace,
            'context' => $context,
            'last_tenant_id' => $tenant,
            'last_seen_at' => $now,
            'resolved_at' => null,
        ]);
        if ($updated === 0) {
            DB::table('server_errors')->insertOrIgnore([
                'id' => (string) Str::uuid7(),
                'fingerprint' => $fingerprint,
                'class' => mb_substr(get_class($e), 0, 255),
                'message' => mb_substr($message, 0, 500),
                'file' => mb_substr($file, 0, 255),
                'trace' => $trace,
                'context' => $context,
                'last_tenant_id' => $tenant,
                'count' => 1,
                'first_seen_at' => $now,
                'last_seen_at' => $now,
            ]);

            return 1;
        }

        return (int) DB::table('server_errors')->where('fingerprint', $fingerprint)->value('count');
    }

    /** "POST /api/v1/sales", or the console command. */
    private static function context(): ?string
    {
        if (app()->runningInConsole()) {
            $argv = $_SERVER['argv'] ?? [];

            return mb_substr('console: '.implode(' ', array_slice($argv, 1, 3)), 0, 255);
        }
        $request = request();

        return mb_substr($request->method().' /'.ltrim($request->path(), '/'), 0, 255);
    }

    private static function relative(string $path): string
    {
        return str_starts_with($path, base_path()) ? ltrim(substr($path, strlen(base_path())), '/') : $path;
    }

    /** No phone numbers, e-mails or long digit runs (SQL bindings, IDs) in what is kept or sent. */
    public static function scrub(string $text): string
    {
        $text = (string) preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/u', '[email]', $text);

        return (string) preg_replace('/\+?\d[\d\s-]{6,}\d/', '[digits]', $text);
    }
}
