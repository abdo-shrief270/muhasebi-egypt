<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Validation\Rule;

/**
 * The one value a feature switch may carry besides on / off, e.g. how many days a sale can be
 * returned in, or whether selling below cost is only warned about or refused. Stored with the
 * owner's override (`tenant_features.value`); no override = the default.
 */
final readonly class FeatureSetting
{
    public const INT = 'int';

    public const CHOICE = 'choice';

    /**
     * @param  array<string, string>  $choices  value => Arabic label (choice settings)
     */
    private function __construct(
        public string $type,
        public string $label,
        public int|string $default,
        public ?int $min = null,
        public ?int $max = null,
        public array $choices = [],
        public ?string $unit = null,
    ) {}

    public static function int(string $label, int $default, int $min, int $max, ?string $unit = null): self
    {
        if ($default < $min || $default > $max) {
            throw new \InvalidArgumentException("Default {$default} is outside {$min}..{$max}.");
        }

        return new self(self::INT, $label, $default, $min, $max, unit: $unit);
    }

    /**
     * @param  array<string, string>  $choices  value => Arabic label
     */
    public static function choice(string $label, string $default, array $choices): self
    {
        if (! array_key_exists($default, $choices)) {
            throw new \InvalidArgumentException("Default [{$default}] is not one of the choices.");
        }

        return new self(self::CHOICE, $label, $default, choices: $choices);
    }

    /** @return list<mixed> validation rules for a new value */
    public function rules(): array
    {
        return $this->type === self::INT
            ? ['integer', 'min:'.$this->min, 'max:'.$this->max]
            : ['string', Rule::in(array_keys($this->choices))];
    }

    /** A stored value (a string column) back to its type; anything no longer valid reads as the default. */
    public function cast(mixed $value): int|string
    {
        if ($value === null) {
            return $this->default;
        }
        if ($this->type === self::INT) {
            $int = filter_var($value, FILTER_VALIDATE_INT);

            return $int === false || $int < $this->min || $int > $this->max ? $this->default : $int;
        }

        return array_key_exists((string) $value, $this->choices) ? (string) $value : $this->default;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return array_filter([
            'type' => $this->type,
            'label' => $this->label,
            'default' => $this->default,
            'min' => $this->min,
            'max' => $this->max,
            'unit' => $this->unit,
            'choices' => $this->type === self::CHOICE
                ? array_map(fn (string $v, string $l) => ['value' => $v, 'label' => $l], array_keys($this->choices), $this->choices)
                : null,
        ], fn ($v) => $v !== null);
    }
}
