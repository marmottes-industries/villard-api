<?php

namespace App\Image;

use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Dossier de stockage des images sur le disque du serveur.
 *
 * La racine vient de `IMAGE_STORAGE_DIR`. En prod elle doit se trouver hors du
 * dépôt : le déploiement fait un `git checkout` dans le dossier de l'API, et
 * ce dossier doit être inclus dans les sauvegardes, comme la base.
 */
final readonly class ImageStorage
{
    private Filesystem $filesystem;

    public function __construct(
        #[Autowire(env: 'resolve:IMAGE_STORAGE_DIR')]
        private string $directory,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Copie le fichier dans le stockage sous un nom aléatoire et renvoie ce nom.
     */
    public function store(string $sourcePath, string $extension): string
    {
        $name = bin2hex(random_bytes(16)).'.'.$extension;

        $this->filesystem->mkdir($this->directory);
        $this->filesystem->copy($sourcePath, $this->pathFor($name), true);

        return $name;
    }

    public function path(Image $image): string
    {
        return $this->pathFor((string) $image->getStoredName());
    }

    public function delete(string $storedName): void
    {
        $this->filesystem->remove($this->pathFor($storedName));
    }

    private function pathFor(string $storedName): string
    {
        // `basename` : un nom stocké ne doit jamais pouvoir sortir du dossier.
        return rtrim($this->directory, '/').'/'.basename($storedName);
    }
}
