<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests;

use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\DailyUsage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Cancelling an operation: services.settings for any; with services.manage, your own operation
 * on the same day (a cashier fixing a slip before the day is over).
 */
final class ReverseRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if ($user?->can('services.settings')) {
            return true;
        }
        $transaction = $this->route('transaction');

        return $user !== null && $user->can('services.manage')
            && $transaction instanceof ServiceTransaction
            && $transaction->user_id === $user->getAuthIdentifier()
            && $transaction->created_at->greaterThanOrEqualTo(CarbonImmutable::now(DailyUsage::TZ)->startOfDay());
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return ['reason' => ['required', 'string', 'max:200']];
    }

    public function attributes(): array
    {
        return ['reason' => 'السبب'];
    }
}
