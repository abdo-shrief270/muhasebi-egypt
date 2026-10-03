<?php

declare(strict_types=1);

namespace App\Support\Media;

use App\Support\Exceptions\DomainRuleException;
use GdImage;

/**
 * An uploaded photo turned into WebP at given widths (never upscaled), upright (phone photos
 * often carry an EXIF rotation instead of rotated pixels) and with transparency kept.
 */
final class WebpImage
{
    private function __construct(private readonly GdImage $image) {}

    public static function read(string $raw): self
    {
        $image = @imagecreatefromstring($raw);
        if (! $image instanceof GdImage) {
            throw new DomainRuleException('الصورة دي مش مقروءة. ابعت JPG أو PNG أو WebP.', 'image_invalid');
        }
        if (function_exists('exif_read_data') && str_starts_with($raw, "\xFF\xD8")) {
            $exif = @exif_read_data('data://image/jpeg;base64,'.base64_encode($raw));
            $angle = match ((int) ($exif['Orientation'] ?? 1)) {
                3 => 180,
                6 => -90,
                8 => 90,
                default => 0,
            };
            if ($angle !== 0) {
                $rotated = imagerotate($image, $angle, 0);
                $image = $rotated instanceof GdImage ? $rotated : $image;
            }
        }
        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        return new self($image);
    }

    public function width(): int
    {
        return imagesx($this->image);
    }

    public function height(): int
    {
        return imagesy($this->image);
    }

    /** WebP bytes, at most $width wide. */
    public function webp(int $width, int $quality = 82): string
    {
        $image = $this->image;
        if ($this->width() > $width) {
            $height = max(1, (int) round($this->height() * $width / $this->width()));
            $scaled = imagescale($this->image, $width, $height, IMG_BICUBIC);
            $image = $scaled instanceof GdImage ? $scaled : $this->image;
            imagesavealpha($image, true);
        }
        ob_start();
        imagewebp($image, null, $quality);

        return (string) ob_get_clean();
    }
}
