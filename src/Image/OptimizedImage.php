<?php

namespace App\Image;

/**
 * Résultat de {@see ImageOptimizer::optimize()} : le fichier à stocker et ses
 * métadonnées. `temporary` indique un fichier ré-encodé à supprimer après copie.
 */
final readonly class OptimizedImage
{
    public function __construct(
        public string $path,
        public string $mimeType,
        public string $extension,
        public int $width,
        public int $height,
        public int $size,
        public bool $temporary,
    ) {
    }
}
