<?php

declare(strict_types=1);

namespace App\Support\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * A business rule was violated. Rendered as { message, code, ...context } with the given HTTP status.
 */
class DomainRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        string $message,
        public readonly string $errorCode,
        public readonly int $status = 422,
        public readonly array $context = [],
    ) {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json([
            'message' => $this->getMessage(),
            'code' => $this->errorCode,
            ...$this->context,
        ], $this->status);
    }
}
