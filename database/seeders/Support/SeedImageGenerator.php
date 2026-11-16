<?php

namespace Database\Seeders\Support;

use Illuminate\Support\Facades\Storage;

/**
 * Pure-PHP PNG image generator (no GD/Imagick required) for seeding
 * real, valid product and variant images onto the public storage disk.
 */
class SeedImageGenerator
{
    /**
     * Generate a 240x240 RGB PNG image on the 'public' disk and return its Storage::url() path.
     */
    public static function generate(
        string $relativePath,
        array $primaryRgb,
        array $accentRgb,
        int $variantIndex = 0
    ): string {
        $width = 240;
        $height = 240;

        $pngData = self::buildPng($width, $height, $primaryRgb, $accentRgb, $variantIndex);
        Storage::disk('public')->put($relativePath, $pngData);

        return Storage::url($relativePath);
    }

    private static function buildPng(
        int $width,
        int $height,
        array $primaryRgb,
        array $accentRgb,
        int $variantIndex
    ): string {
        [$r1, $g1, $b1] = $primaryRgb;
        [$r2, $g2, $b2] = $accentRgb;

        $raw = '';
        $centerX = $width / 2;
        $centerY = $height / 2;

        for ($y = 0; $y < $height; $y++) {
            $raw .= "\x00"; // Filter type 0 (None)
            $ty = $y / ($height - 1);

            for ($x = 0; $x < $width; $x++) {
                $tx = $x / ($width - 1);
                $t = ($tx * 0.45) + ($ty * 0.55);

                $r = (int) round($r1 + ($r2 - $r1) * $t);
                $g = (int) round($g1 + ($g2 - $g1) * $t);
                $b = (int) round($b1 + ($b2 - $b1) * $t);

                // Outer subtle frame
                if ($x < 10 || $x >= $width - 10 || $y < 10 || $y >= $height - 10) {
                    $r = (int) min(255, $r + 22);
                    $g = (int) min(255, $g + 22);
                    $b = (int) min(255, $b + 22);
                }

                // Inner geometric emblem so each product/variant looks distinct
                $dx = $x - $centerX;
                $dy = $y - $centerY;
                $dist = sqrt($dx * $dx + $dy * $dy);

                if ($variantIndex % 3 === 0) {
                    // Concentric badge ring
                    if ($dist >= 44 && $dist <= 62) {
                        $r = 255;
                        $g = 255;
                        $b = 255;
                    } elseif ($dist < 30) {
                        $r = (int) max(0, $r2 - 25);
                        $g = (int) max(0, $g2 - 25);
                        $b = (int) max(0, $b2 - 25);
                    }
                } elseif ($variantIndex % 3 === 1) {
                    // Rounded diamond emblem
                    $manhattan = abs($dx) + abs($dy);
                    if ($manhattan >= 42 && $manhattan <= 60) {
                        $r = 255;
                        $g = 255;
                        $b = 255;
                    } elseif ($manhattan < 26) {
                        $r = (int) min(255, $r1 + 35);
                        $g = (int) min(255, $g1 + 35);
                        $b = (int) min(255, $b1 + 35);
                    }
                } else {
                    // Diagonal accent stripes + center square
                    if (abs($dx) < 34 && abs($dy) < 34) {
                        $r = 255;
                        $g = 255;
                        $b = 255;
                    } elseif ((($x + $y) % 48) < 8 && $dist < 88) {
                        $r = (int) min(255, $r + 30);
                        $g = (int) min(255, $g + 30);
                        $b = (int) min(255, $b + 30);
                    }
                }

                $raw .= chr(max(0, min(255, $r)))
                      . chr(max(0, min(255, $g)))
                      . chr(max(0, min(255, $b)));
            }
        }

        $ihdr = pack('N2C5', $width, $height, 8, 2, 0, 0, 0);
        $idat = gzcompress($raw, 6);

        return "\x89PNG\r\n\x1a\n"
            . self::chunk('IHDR', $ihdr)
            . self::chunk('IDAT', $idat)
            . self::chunk('IEND', '');
    }

    private static function chunk(string $type, string $data): string
    {
        $crc = crc32($type . $data);
        return pack('N', strlen($data)) . $type . $data . pack('N', $crc);
    }
}
