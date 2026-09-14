<?php

namespace App\Image;

use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Contrôle et compresse une image envoyée, avec GD.
 *
 * Une image de 1 Mo ou moins est gardée telle quelle. Au-delà, elle est
 * redressée selon son orientation EXIF, ramenée à {@see self::MAX_EDGE} px sur
 * son plus grand côté, puis ré-encodée en JPEG qualité {@see self::JPEG_QUALITY}.
 * Une photo de téléphone de 4 à 6 Mo tombe ainsi autour de 300 à 600 Ko.
 *
 * Le redressement est indispensable : les téléphones enregistrent le capteur
 * « à plat » et notent la rotation dans l'EXIF, que GD ignore et que le
 * ré-encodage efface. Sans lui, les photos portrait ressortiraient couchées.
 */
final class ImageOptimizer
{
    public const COMPRESS_THRESHOLD = 1024 * 1024;
    public const MAX_EDGE = 2048;
    public const JPEG_QUALITY = 82;

    /**
     * Au-delà, le décodage GD dépasserait raisonnablement la mémoire du serveur
     * (environ 5 octets par pixel).
     */
    public const MAX_PIXELS = 50_000_000;

    private const EXTENSIONS = [
        'image/jpeg' => 'jpg',
        'image/png' => 'png',
        'image/webp' => 'webp',
    ];

    public function optimize(string $path): OptimizedImage
    {
        $mimeType = (new \finfo(\FILEINFO_MIME_TYPE))->file($path) ?: '';

        if (!isset(self::EXTENSIONS[$mimeType])) {
            throw new UnprocessableEntityHttpException('Format non supporté : JPEG, PNG ou WebP uniquement.');
        }

        $info = @getimagesize($path);

        if (false === $info) {
            throw new UnprocessableEntityHttpException('Image illisible.');
        }

        [$width, $height] = $info;
        $size = (int) filesize($path);
        $orientation = $this->exifOrientation($path, $mimeType);

        $original = new OptimizedImage(
            path: $path,
            mimeType: $mimeType,
            extension: self::EXTENSIONS[$mimeType],
            // Orientations 5 à 8 : l'image s'affiche pivotée d'un quart de tour.
            width: $orientation >= 5 ? $height : $width,
            height: $orientation >= 5 ? $width : $height,
            size: $size,
            temporary: false,
        );

        if ($size <= self::COMPRESS_THRESHOLD) {
            return $original;
        }

        if ($width * $height > self::MAX_PIXELS) {
            throw new UnprocessableEntityHttpException('Image trop grande (50 mégapixels maximum).');
        }

        $this->raiseMemoryLimit();

        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            'image/webp' => @imagecreatefromwebp($path),
        };

        if (false === $image) {
            throw new UnprocessableEntityHttpException('Image illisible.');
        }

        $image = $this->applyOrientation($image, $orientation);
        $image = $this->resizeOnWhite($image);

        $target = tempnam(sys_get_temp_dir(), 'img');
        imageinterlace($image, true);
        $written = imagejpeg($image, $target, self::JPEG_QUALITY);
        $optimized = new OptimizedImage(
            path: $target,
            mimeType: 'image/jpeg',
            extension: 'jpg',
            width: imagesx($image),
            height: imagesy($image),
            size: (int) filesize($target),
            temporary: true,
        );

        // Cas rare (PNG très plat, JPEG déjà très compressé) : le ré-encodage
        // ne gagne rien, on garde l'original.
        if (!$written || $optimized->size >= $size) {
            @unlink($target);

            return $original;
        }

        return $optimized;
    }

    private function exifOrientation(string $path, string $mimeType): int
    {
        if ('image/jpeg' !== $mimeType || !\function_exists('exif_read_data')) {
            return 1;
        }

        $exif = @exif_read_data($path);
        $orientation = (int) ($exif['Orientation'] ?? 1);

        return $orientation >= 1 && $orientation <= 8 ? $orientation : 1;
    }

    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        // `imagerotate` tourne dans le sens anti-horaire.
        if (\in_array($orientation, [2, 7], true)) {
            imageflip($image, \IMG_FLIP_HORIZONTAL);
        }
        if (\in_array($orientation, [4, 5], true)) {
            imageflip($image, \IMG_FLIP_VERTICAL);
        }

        $angle = match ($orientation) {
            3 => 180,
            5, 6, 7 => -90,
            8 => 90,
            default => 0,
        };

        if (0 === $angle) {
            return $image;
        }

        return imagerotate($image, $angle, 0);
    }

    /**
     * Ramène le plus grand côté à {@see self::MAX_EDGE} px et aplatit sur fond
     * blanc en une seule copie : le JPEG n'a pas de transparence, un PNG détouré
     * ressortirait sinon sur fond noir.
     *
     * `imagecopyresampled` plutôt que `imagescale` : ce dernier renvoie `false`
     * en `IMG_BICUBIC` sur certains builds de GD.
     */
    private function resizeOnWhite(\GdImage $image): \GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min(1, self::MAX_EDGE / max($width, $height));
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));

        $canvas = imagecreatetruecolor($targetWidth, $targetHeight);
        imagefill($canvas, 0, 0, (int) imagecolorallocate($canvas, 255, 255, 255));
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $canvas;
    }

    /**
     * Décoder une photo de 48 Mpx demande environ 250 Mo, au-dessus du
     * `memory_limit` courant de 128 Mo. On relève la limite pour cette requête
     * seulement, sans jamais l'abaisser.
     */
    private function raiseMemoryLimit(): void
    {
        $current = (string) ini_get('memory_limit');

        if ('-1' === $current || $this->toBytes($current) >= 512 * 1024 * 1024) {
            return;
        }

        @ini_set('memory_limit', '512M');
    }

    private function toBytes(string $value): int
    {
        $value = trim($value);
        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }
}
