<?php

namespace App\Doctrine;

use App\Entity\Image;
use App\Image\ImageStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Events;

/**
 * Efface du disque le fichier d'une {@see Image} supprimée.
 *
 * Couvre la suppression d'une image seule (`DELETE /api/images/{id}`) comme la
 * cascade ORM depuis `Note::$images` et `Work::$images`. Un `ON DELETE CASCADE`
 * purement SQL, lui, ne passe pas par ici : c'est pour ça que la cascade est
 * portée par l'ORM.
 *
 * Le fichier n'est effacé qu'en `postFlush`, une fois la transaction validée.
 * `postRemove` tombe encore dans la transaction : un échec plus loin dans le
 * même `flush` ferait un rollback de la ligne, mais pas du fichier.
 */
#[AsDoctrineListener(event: Events::postRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
final class ImageFileRemover
{
    /** @var list<string> */
    private array $pending = [];

    public function __construct(
        private readonly ImageStorage $storage,
    ) {
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $entity = $args->getObject();

        if ($entity instanceof Image && null !== $entity->getStoredName()) {
            $this->pending[] = $entity->getStoredName();
        }
    }

    public function postFlush(): void
    {
        $pending = $this->pending;
        $this->pending = [];

        foreach ($pending as $storedName) {
            $this->storage->delete($storedName);
        }
    }
}
