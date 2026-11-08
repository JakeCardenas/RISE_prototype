<?php

namespace App\Services\Photos;

use Carbon\CarbonImmutable;
use Imagick;
use RuntimeException;
use Throwable;

// Makes the private copies RISE needs from a resident's photo. Originals are never changed:
// a JPEG display copy is made only for HEIC/HEIF photos and photos that carry location data,
// and analysis copies are built in memory, so no temporary files are left behind.
class PhotoProcessor
{
    public const HEIC_TYPES = ['image/heic', 'image/heif'];

    // Imagick with an HEIC decoder (libheif) is needed; without it HEIC uploads are refused with export advice
    public function supportsHeic(): bool
    {
        if (! config('rise.heic_conversion') || ! extension_loaded('imagick')) {
            return false;
        }

        return Imagick::queryFormats('HEIC') !== [];
    }

    public function isHeic(?string $mime): bool
    {
        return in_array($mime, self::HEIC_TYPES, true);
    }

    // Why the original shouldn't be shown as-is, or null when it can be
    public function displayCopyReason(string $path, string $mime): ?string
    {
        if ($this->isHeic($mime)) {
            return 'converted_from_heic';
        }

        return $this->hasLocation($path, $mime) ? 'location_removed' : null;
    }

    // An upright JPEG with every embedded detail, including location, removed
    public function displayCopy(string $bytes): ProcessedImage
    {
        return $this->jpegCopy($bytes, maxSide: 4096, quality: 90);
    }

    // A smaller JPEG sized for the analysis provider, also without embedded details
    public function analysisCopy(string $bytes, int $maxSide): ProcessedImage
    {
        return $this->jpegCopy($bytes, $maxSide, quality: 85);
    }

    /** @return array{0: int|null, 1: int|null} */
    public function dimensions(string $path, string $mime): array
    {
        $size = @getimagesize($path);

        if ($size !== false) {
            return [$size[0], $size[1]];
        }

        if ($this->isHeic($mime) && $this->supportsHeic()) {
            try {
                $image = new Imagick();
                $image->pingImage($path);

                return [$image->getImageWidth(), $image->getImageHeight()];
            } catch (Throwable) {
                // Reported as unreadable below
            }
        }

        return [null, null];
    }

    /**
     * When the photo says it was taken, from its own EXIF details: the camera clock's date and time, and its UTC offset
     * when the camera recorded one. Unverified metadata; anything missing, malformed or implausible gives [null, null].
     *
     * @return array{0: CarbonImmutable|null, 1: string|null}
     */
    public function takenAt(string $path, string $mime): array
    {
        [$time, $offset] = $this->exifTimeOriginal($path, $mime);
        $time = is_string($time) ? trim($time) : '';

        if (! CarbonImmutable::canBeCreatedFromFormat($time, 'Y:m:d H:i:s')) {
            return [null, null];
        }

        $taken = CarbonImmutable::createFromFormat('!Y:m:d H:i:s', $time, 'UTC');

        // A camera clock can be off, but not by decades or into the future
        if (! $taken->between(CarbonImmutable::create(2000), now()->addDay())) {
            return [null, null];
        }

        $offset = is_string($offset) ? trim($offset) : '';

        return [$taken, preg_match('/^[+-](0\d|1[0-4]):[0-5]\d$/', $offset) === 1 ? $offset : null];
    }

    /** @return array{0: mixed, 1: mixed} */
    private function exifTimeOriginal(string $path, string $mime): array
    {
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick();
                $image->pingImage($path);
                $properties = $image->getImageProperties('exif:*TimeOriginal', true);

                return [$properties['exif:DateTimeOriginal'] ?? null, $properties['exif:OffsetTimeOriginal'] ?? null];
            } catch (Throwable) {
                // Fall through to the EXIF reader
            }
        }

        $exif = $mime === 'image/jpeg' && extension_loaded('exif') ? @exif_read_data($path, 'EXIF') : false;

        return [$exif['DateTimeOriginal'] ?? null, $exif['OffsetTimeOriginal'] ?? null];
    }

    public function hasLocation(string $path, string $mime): bool
    {
        if (extension_loaded('imagick')) {
            try {
                $image = new Imagick();
                $image->pingImage($path);
                $xmp = $image->getImageProfiles('xmp', true)['xmp'] ?? '';

                return $image->getImageProperties('exif:GPS*', false) !== []
                    || stripos($xmp, 'GPSLatitude') !== false
                    || stripos($xmp, 'GPSLongitude') !== false;
            } catch (Throwable) {
                // Fall through to the EXIF reader
            }
        }

        return $mime === 'image/jpeg' && extension_loaded('exif') && @exif_read_data($path, 'GPS') !== false;
    }

    private function jpegCopy(string $bytes, int $maxSide, int $quality): ProcessedImage
    {
        return extension_loaded('imagick')
            ? $this->imagickCopy($bytes, $maxSide, $quality)
            : $this->gdCopy($bytes, $maxSide, $quality);
    }

    private function imagickCopy(string $bytes, int $maxSide, int $quality): ProcessedImage
    {
        $image = new Imagick();
        $image->readImageBlob($bytes);
        $image->setIteratorIndex(0);
        $image->autoOrient();
        $image->stripImage();

        if (max($image->getImageWidth(), $image->getImageHeight()) > $maxSide) {
            $image->thumbnailImage($maxSide, $maxSide, true);
        }

        // Transparent PNG or WebP areas become white instead of black
        if ($image->getImageAlphaChannel()) {
            $image->setImageBackgroundColor('white');
            $image = $image->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }

        $image->setImageFormat('jpeg');
        $image->setImageCompressionQuality($quality);

        return new ProcessedImage($image->getImageBlob(), $image->getImageWidth(), $image->getImageHeight());
    }

    // Fallback for servers without Imagick (no HEIC support there)
    private function gdCopy(string $bytes, int $maxSide, int $quality): ProcessedImage
    {
        $source = @imagecreatefromstring($bytes) ?: throw new RuntimeException('The image could not be decoded.');

        $exif = extension_loaded('exif') ? @exif_read_data('data://image/jpeg;base64,'.base64_encode($bytes)) : false;
        $source = match ((int) ($exif['Orientation'] ?? 1)) {
            3 => imagerotate($source, 180, 0),
            6 => imagerotate($source, -90, 0),
            8 => imagerotate($source, 90, 0),
            default => $source,
        };

        $scale = min(1, $maxSide / max(imagesx($source), imagesy($source)));
        $copy = imagecreatetruecolor(max(1, (int) round(imagesx($source) * $scale)), max(1, (int) round(imagesy($source) * $scale)));
        imagefill($copy, 0, 0, imagecolorallocate($copy, 255, 255, 255));
        imagecopyresampled($copy, $source, 0, 0, 0, 0, imagesx($copy), imagesy($copy), imagesx($source), imagesy($source));

        ob_start();
        imagejpeg($copy, null, $quality);

        return new ProcessedImage((string) ob_get_clean(), imagesx($copy), imagesy($copy));
    }
}
