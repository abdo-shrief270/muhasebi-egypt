<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Support\Exceptions\DomainRuleException;

/** The refusal when the owner has switched a feature off: 403 `feature_disabled`, the same from the middleware or an action. */
final class FeatureDisabled
{
    public static function for(string $key, ?Feature $feature): DomainRuleException
    {
        $label = $feature?->label ?? $key;

        return new DomainRuleException("«{$label}» مقفولة في المحل ده. صاحب المحل يقدر يفتحها من صفحة المميزات.", 'feature_disabled', 403, ['feature' => $key]);
    }
}
