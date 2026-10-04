<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Is everything the shops need working: the database, the cache, the queue workers and the
 * scheduler (each leaves a heartbeat every minute), and the disk. For the uptime monitor and the
 * host's monitor.sh (GET /api/v1/health → 503 when something is wrong).
 */
final class Health
{
    /** A heartbeat older than this means the worker / scheduler stopped. */
    public const STALE_MINUTES = 5;

    /** @return array{ok: bool, checks: array<string, array{ok: bool, detail: string}>} */
    public function check(): array
    {
        $checks = [
            'database' => $this->probe(function (): string {
                DB::select('select 1');

                return 'متصلة';
            }),
            'cache' => $this->probe(function (): string {
                Cache::put('monitoring:probe', 1, 60);
                if ((int) Cache::get('monitoring:probe') !== 1) {
                    throw new \RuntimeException('مش بيحفظ');
                }

                return 'شغال';
            }),
            'queue' => $this->heartbeat('queue', 'الطوابير (الإشعارات والرسائل)'),
            'scheduler' => $this->heartbeat('scheduler', 'المهام المجدولة'),
            'disk' => $this->disk(),
        ];

        return ['ok' => ! in_array(false, array_column($checks, 'ok'), true), 'checks' => $checks];
    }

    public static function beat(string $name): void
    {
        Cache::put("monitoring:heartbeat:{$name}", now()->getTimestamp(), now()->addDay());
    }

    /** @return array{ok: bool, detail: string} */
    private function heartbeat(string $name, string $label): array
    {
        return $this->probe(function () use ($name, $label): string {
            $at = Cache::get("monitoring:heartbeat:{$name}");
            if ($at === null) {
                throw new \RuntimeException("{$label}: مفيش نبض لسه");
            }
            $minutes = intdiv(now()->getTimestamp() - (int) $at, 60);
            if ($minutes >= self::STALE_MINUTES) {
                throw new \RuntimeException("{$label}: آخر نبض من {$minutes} دقيقة");
            }

            return "آخر نبض من {$minutes} دقيقة";
        });
    }

    /** @return array{ok: bool, detail: string} */
    private function disk(): array
    {
        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());
        if (! $total || $free === false) {
            return ['ok' => true, 'detail' => 'مش معروف'];
        }
        $percent = (int) floor($free * 100 / $total);

        return ['ok' => $percent >= (int) config('services.monitoring.min_free_percent', 10), 'detail' => "فاضي {$percent}% (".round($free / 1073741824, 1).' جيجا)'];
    }

    /** @return array{ok: bool, detail: string} */
    private function probe(callable $fn): array
    {
        try {
            return ['ok' => true, 'detail' => (string) $fn()];
        } catch (Throwable $e) {
            return ['ok' => false, 'detail' => mb_substr(ServerErrors::scrub($e->getMessage()), 0, 200)];
        }
    }
}
