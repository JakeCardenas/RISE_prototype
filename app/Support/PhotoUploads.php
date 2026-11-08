<?php

namespace App\Support;

use App\Services\Photos\PhotoProcessor;
use Illuminate\Support\Number;

class PhotoUploads
{
    // Photo formats every modern browser can show
    public const EXTENSIONS = ['jpg', 'jpeg', 'png', 'webp'];

    public const MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp'];

    // Accepted only where this server can convert them to JPEG
    public const HEIC_EXTENSIONS = ['heic', 'heif'];

    public static function heicSupported(): bool
    {
        return app(PhotoProcessor::class)->supportsHeic();
    }

    // For the file picker's accept attribute; extensions help macOS offer HEIC files
    public static function acceptedTypes(): array
    {
        return self::heicSupported()
            ? [...self::MIME_TYPES, ...PhotoProcessor::HEIC_TYPES, '.heic', '.heif']
            : self::MIME_TYPES;
    }

    public static function formatLabel(): string
    {
        return self::heicSupported() ? 'JPEG, PNG, WebP or HEIC' : 'JPEG, PNG or WebP';
    }

    // The largest accepted photo in kilobytes: the RISE limit or PHP's limits, whichever is lower
    public static function maxKilobytes(): int
    {
        $limits = array_filter([
            (int) config('rise.max_upload_kb'),
            intdiv(self::iniBytes('upload_max_filesize'), 1024),
            // Leave room in the request for the note and the other form fields
            intdiv(self::iniBytes('post_max_size'), 1024) - 64,
        ], fn (int $kilobytes) => $kilobytes > 0);

        return $limits === [] ? 1 : min($limits);
    }

    public static function maxBytes(): int
    {
        return self::maxKilobytes() * 1024;
    }

    // The limit as residents read it, such as "2 MB"
    public static function maxLabel(): string
    {
        return Number::fileSize(self::maxBytes(), maxPrecision: 1);
    }

    // Convert a php.ini size such as "8M" to bytes; 0 means no limit
    private static function iniBytes(string $key): int
    {
        $value = trim((string) ini_get($key));
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
