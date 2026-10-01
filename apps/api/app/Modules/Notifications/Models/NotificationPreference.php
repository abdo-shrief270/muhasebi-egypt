<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * What a user wants pushed: categories muted, and quiet hours (Cairo time) when only urgent
 * ones come. No row = everything, any time.
 *
 * @property string $user_id
 * @property string $tenant_id
 * @property list<string> $muted
 * @property string|null $quiet_from HH:MM:SS
 * @property string|null $quiet_to HH:MM:SS
 */
#[Fillable(['user_id', 'tenant_id', 'muted', 'quiet_from', 'quiet_to'])]
final class NotificationPreference extends Model
{
    use BelongsToTenant;

    protected $primaryKey = 'user_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $attributes = ['muted' => '[]'];

    protected function casts(): array
    {
        return ['muted' => 'array'];
    }

    /** Inside the quiet hours at this Cairo time (a range may wrap midnight, e.g. 23:00 → 09:00). */
    public function isQuietAt(string $time): bool
    {
        if ($this->quiet_from === null || $this->quiet_to === null) {
            return false;
        }
        $from = substr($this->quiet_from, 0, 5);
        $to = substr($this->quiet_to, 0, 5);
        $now = substr($time, 0, 5);

        return $from <= $to ? ($now >= $from && $now < $to) : ($now >= $from || $now < $to);
    }
}
