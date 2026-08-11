<?php

namespace App\Contract;

use App\Entity\Property;

/**
 * Marque une entité comme rattachée à un logement.
 *
 * Toute ressource implémentant ce contrat est automatiquement cloisonnée par
 * {@see \App\Doctrine\PropertyScopeExtension} : ses collections et ses items
 * sont filtrés sur les logements dont l'utilisateur est membre, sans que le
 * client ait à envoyer le moindre filtre. Ajouter une nouvelle ressource
 * métier revient donc à implémenter cette interface — impossible d'oublier le
 * cloisonnement.
 */
interface PropertyScopedInterface
{
    public function getProperty(): ?Property;

    public function setProperty(?Property $property): static;
}
