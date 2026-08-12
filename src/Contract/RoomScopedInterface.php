<?php

namespace App\Contract;

use App\Entity\Room;

/**
 * Marque une entité comme rattachable à une pièce, en plus de son logement.
 *
 * Une pièce appartenant elle-même à un logement, la cohérence des deux champs
 * doit être garantie : sans cela, un utilisateur membre de deux logements peut
 * rattacher une ressource du logement A à une pièce du logement B — les deux
 * IRI lui étant accessibles, ni {@see \App\Doctrine\PropertyScopeExtension} ni
 * {@see \App\Security\Voter\PropertyVoter} ne s'y opposent.
 *
 * Ce contrat donne un type unique aux deux points de contrôle qui ferment le
 * trou : {@see \App\Validator\RoomBelongsToProperty} et le filet de
 * {@see \App\State\PropertyScopeProcessor}.
 */
interface RoomScopedInterface extends PropertyScopedInterface
{
    public function getRoom(): ?Room;

    public function setRoom(?Room $room): static;
}
