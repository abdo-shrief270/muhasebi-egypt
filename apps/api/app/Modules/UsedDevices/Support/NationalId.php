<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

use Carbon\CarbonImmutable;

/**
 * An Egyptian national ID (الرقم القومي), 14 digits: C YYMMDD GG SSS G X
 *  - C: century (2 = 1900s, 3 = 2000s)
 *  - YYMMDD: birth date
 *  - GG: governorate of birth registration (88 = born abroad)
 *  - digit 13: odd = male, even = female
 *  - X: check digit (its algorithm isn't published, so it isn't checked)
 */
final readonly class NationalId
{
    public const GOVERNORATES = [
        '01' => 'القاهرة', '02' => 'الإسكندرية', '03' => 'بورسعيد', '04' => 'السويس',
        '11' => 'دمياط', '12' => 'الدقهلية', '13' => 'الشرقية', '14' => 'القليوبية', '15' => 'كفر الشيخ',
        '16' => 'الغربية', '17' => 'المنوفية', '18' => 'البحيرة', '19' => 'الإسماعيلية',
        '21' => 'الجيزة', '22' => 'بني سويف', '23' => 'الفيوم', '24' => 'المنيا', '25' => 'أسيوط',
        '26' => 'سوهاج', '27' => 'قنا', '28' => 'أسوان', '29' => 'الأقصر',
        '31' => 'البحر الأحمر', '32' => 'الوادي الجديد', '33' => 'مطروح', '34' => 'شمال سيناء', '35' => 'جنوب سيناء',
        '88' => 'مولود بره مصر',
    ];

    private function __construct(
        public string $number,
        public CarbonImmutable $birthDate,
        public string $governorateCode,
        public string $gender,
    ) {}

    /** Arabic-Indic digits and spaces are accepted and folded. */
    public static function normalize(string $raw): string
    {
        $raw = strtr($raw, ['٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9']);

        return (string) preg_replace('/[\s\-]+/u', '', $raw);
    }

    /**
     * @return array{0: self|null, 1: string|null} the ID, or null and why it is wrong (Arabic)
     */
    public static function parse(string $raw, ?CarbonImmutable $today = null): array
    {
        $n = self::normalize($raw);
        if (! preg_match('/^\d{14}$/', $n)) {
            return [null, 'الرقم القومي لازم يبقى 14 رقم.'];
        }
        $century = match ($n[0]) {
            '2' => 1900,
            '3' => 2000,
            default => null,
        };
        if ($century === null) {
            return [null, 'أول رقم في الرقم القومي لازم يبقى 2 أو 3.'];
        }
        $year = $century + (int) substr($n, 1, 2);
        $month = (int) substr($n, 3, 2);
        $day = (int) substr($n, 5, 2);
        $today ??= CarbonImmutable::now('Africa/Cairo');
        if (! checkdate($month, $day, $year)) {
            return [null, 'تاريخ الميلاد اللي في الرقم القومي مش صحيح.'];
        }
        $birth = CarbonImmutable::create($year, $month, $day, 0, 0, 0, 'Africa/Cairo');
        if ($birth->greaterThan($today)) {
            return [null, 'تاريخ الميلاد اللي في الرقم القومي لسه ماجاش.'];
        }
        $governorate = substr($n, 7, 2);
        if (! isset(self::GOVERNORATES[$governorate])) {
            return [null, 'كود المحافظة اللي في الرقم القومي مش صحيح.'];
        }

        return [new self($n, $birth, $governorate, ((int) $n[12]) % 2 === 1 ? 'male' : 'female'), null];
    }

    public function governorate(): string
    {
        return self::GOVERNORATES[$this->governorateCode];
    }

    public function age(?CarbonImmutable $today = null): int
    {
        return (int) $this->birthDate->diffInYears($today ?? CarbonImmutable::now('Africa/Cairo'));
    }

    /** A keyed hash, so the same person is found again without storing the number in the clear. */
    public function hash(): string
    {
        return self::hashOf($this->number);
    }

    public static function hashOf(string $number): string
    {
        return hash_hmac('sha256', self::normalize($number), hash_hmac('sha256', 'national-id', (string) config('app.key')));
    }

    /**
     * @return array{birth_date: string, age: int, gender: string, gender_label: string, governorate: string}
     */
    public function details(): array
    {
        return [
            'birth_date' => $this->birthDate->toDateString(),
            'age' => $this->age(),
            'gender' => $this->gender,
            'gender_label' => $this->gender === 'male' ? 'ذكر' : 'أنثى',
            'governorate' => $this->governorate(),
        ];
    }
}
