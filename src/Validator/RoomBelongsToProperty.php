<?php

namespace App\Validator;

use Symfony\Component\Validator\Constraint;

/**
 * Vérifie qu'une ressource et la pièce qu'elle référence appartiennent au même
 * logement. Portée par {@see \App\Entity\InventoryItem} et
 * {@see \App\Entity\Work}, cf. {@see \App\Contract\RoomScopedInterface}.
 *
 * Contrainte de classe plutôt qu'`Assert\Callback` dupliqué : la règle sert
 * deux entités, avec un message identique et une tolérance au `null` qui n'a
 * rien d'évident (voir le validateur).
 */
#[\Attribute(\Attribute::TARGET_CLASS)]
final class RoomBelongsToProperty extends Constraint
{
    public string $message = 'Cette pièce appartient à un autre logement.';

    public function getTargets(): string
    {
        return self::CLASS_CONSTRAINT;
    }
}
