<?php

declare(strict_types=1);

namespace App\Modules\Imports\Support;

/**
 * The landed cost of each item of a shipment: its price plus its share of the shipment's costs
 * (by value, by quantity or by weight; shares in whole piasters adding up exactly), spread over the good
 * units that arrived. Units missing or damaged: when claimed from the supplier their price comes
 * back, so only the share of costs falls on the good units; when not, they cost the good ones too.
 */
final class LandedCost
{
    /**
     * @param  list<array{qty: int, unit_price: int, received: int, weight?: int|null}>  $lines
     * @return list<array{share: int, unit_cost: int}> in the same order
     */
    public static function compute(array $lines, int $costs, string $allocation, bool $claimed): array
    {
        $weights = array_map(fn (array $l) => match ($allocation) {
            'qty' => $l['qty'],
            'weight' => (int) ($l['weight'] ?? 0),
            default => $l['qty'] * $l['unit_price'],
        }, $lines);
        $shares = self::split($costs, $weights);

        return array_map(function (array $line, int $share) use ($claimed): array {
            if ($line['received'] <= 0) {
                return ['share' => $share, 'unit_cost' => 0];
            }
            $goods = $line['unit_price'] * ($claimed ? $line['received'] : $line['qty']);

            return ['share' => $share, 'unit_cost' => intdiv($goods + $share + intdiv($line['received'], 2), $line['received'])];
        }, $lines, $shares);
    }

    /**
     * $total split by $weights, the remainders going to the largest fractions (sum is exact).
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    public static function split(int $total, array $weights): array
    {
        $sum = array_sum($weights);
        if ($sum <= 0) {
            $weights = array_fill(0, count($weights), 1);
            $sum = count($weights);
        }
        if ($sum === 0) {
            return [];
        }
        $shares = [];
        $fractions = [];
        foreach ($weights as $i => $w) {
            $exact = $total * $w;
            $shares[$i] = intdiv($exact, $sum);
            $fractions[$i] = $exact % $sum;
        }
        arsort($fractions);
        $left = $total - array_sum($shares);
        foreach (array_keys($fractions) as $i) {
            if ($left <= 0) {
                break;
            }
            $shares[$i]++;
            $left--;
        }
        ksort($shares);

        return array_values($shares);
    }
}
