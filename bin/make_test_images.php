<?php

declare(strict_types=1);

/**
 * Tao cac tep anh gia lap (PNG, JPEG, WebP) cho may chu thu nghiem.
 * Chay: php bin/make_test_images.php
 */

$base = dirname(__DIR__);
$dir = $base . '/test_server/images';
if (!is_dir($dir)) {
    mkdir($dir, 0o775, true);
}

echo "Tao anh thu nghiem trong {$dir}\n";

/** Tao anh PNG bang GD (neu co) hoac viet truc tiep mot PNG hop le nho. */
function makePng(string $path, int $width, int $height, array $rgb, string $label = ''): void
{
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor($width, $height);
        $bg  = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);

        // Ve hinh chu nhat trang de phan biet.
        $fg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, (int)($width * 0.15), (int)($height * 0.2),
            (int)($width * 0.85), (int)($height * 0.8), $fg);
        $fg2 = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, (int)($width * 0.25), (int)($height * 0.35),
            (int)($width * 0.75), (int)($height * 0.65), $fg2);

        if ($label !== '' && function_exists('imagestring')) {
            imagestring($img, 5, 10, 10, $label, $fg);
        }

        imagepng($img, $path);
        imagedestroy($img);
        echo "[PNG ] {$path}\n";
        return;
    }

    // Khong co GD -> viet PNG 1x1 hop le.
    file_put_contents($path, base64_decode(
        'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAYAAAAfFcSJAAAADUlEQVR42mP8z8DwHwAFBQIAX8jx0gAAAABJRU5ErkJggg=='
    ));
    echo "[PNG ] {$path} (1x1 placeholder)\n";
}

function makeJpeg(string $path, int $width, int $height, array $rgb): void
{
    if (function_exists('imagecreatetruecolor')) {
        $img = imagecreatetruecolor($width, $height);
        $bg  = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        $fg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, (int)($width * 0.2), (int)($height * 0.25),
            (int)($width * 0.8), (int)($height * 0.75), $fg);
        $fg2 = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, (int)($width * 0.3), (int)($height * 0.35),
            (int)($width * 0.7), (int)($height * 0.65), $fg2);
        imagejpeg($img, $path, 85);
        imagedestroy($img);
        echo "[JPEG] {$path}\n";
        return;
    }

    // JPEG 1x1 hop le.
    file_put_contents($path, base64_decode(
        '/9j/4AAQSkZJRgABAQEAYABgAAD/2wBDAAgGBgcGBQgHBwcJCQgKDBQNDAsLDBkSEw8UHRofHh0a'
        . 'HBwgJC4nICIsIxwcKDcpLDAxNDQ0Hyc5PTgyPC4zNDL/wAALCAABAAEBAREA/8QAFAABAAAAAAAA'
        . 'AAAAAAAAAAAACf/EABQQAQAAAAAAAAAAAAAAAAAAAAD/2gAIAQEAAD8AKp//2Q=='
    ));
    echo "[JPEG] {$path} (1x1 placeholder)\n";
}

function makeWebp(string $path, int $width, int $height, array $rgb): void
{
    if (function_exists('imagecreatetruecolor') && function_exists('imagewebp')) {
        $img = imagecreatetruecolor($width, $height);
        $bg  = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, 0, 0, $width, $height, $bg);
        $fg = imagecolorallocate($img, 255, 255, 255);
        imagefilledrectangle($img, (int)($width * 0.2), (int)($height * 0.25),
            (int)($width * 0.8), (int)($height * 0.75), $fg);
        $fg2 = imagecolorallocate($img, $rgb[0], $rgb[1], $rgb[2]);
        imagefilledrectangle($img, (int)($width * 0.3), (int)($height * 0.35),
            (int)($width * 0.7), (int)($height * 0.65), $fg2);
        imagewebp($img, $path);
        imagedestroy($img);
        echo "[WEBP] {$path}\n";
        return;
    }

    // WebP 1x1 hop le.
    file_put_contents($path, base64_decode(
        'UklGRiIAAABXRUJQVlA4IBYAAAAwAQCdASoBAAEAAUAmJaQAA3AA/v89WAAAAA=='
    ));
    echo "[WEBP] {$path} (1x1 placeholder)\n";
}

// Tao cac anh duoc tham chieu trong cac trang HTML.
makeJpeg($dir . '/dong-que-1.jpg', 800, 450, [46, 125, 50]);
makeJpeg($dir . '/dong-que-2.jpg', 800, 450, [240, 150, 40]);
makePng($dir . '/safe.png', 200, 100, [59, 110, 165], 'SAFE');
makeWebp($dir . '/anh-webp.webp', 600, 400, [155, 89, 182]);
makeJpeg($dir . '/anh-jpeg.jpg', 600, 400, [231, 76, 60]);
makePng($dir . '/anh-1.png', 400, 300, [52, 152, 219]);
makeJpeg($dir . '/anh-2.jpg', 400, 300, [39, 174, 96]);
makeWebp($dir . '/anh-3.webp', 400, 300, [241, 196, 15]);

echo "\nHoan tat.\n";