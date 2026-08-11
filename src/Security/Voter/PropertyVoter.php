<?php

namespace App\Security\Voter;

use App\Entity\Property;
use App\Entity\User;
use App\Enum\PropertyRole;
use App\Repository\PropertyMemberRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Garde les écritures sur les ressources rattachées à un logement.
 *
 * Pendant que {@see \App\Doctrine\PropertyScopeExtension} filtre les lectures,
 * ce voter tranche les écritures à partir de l'appartenance de l'utilisateur :
 *
 *  - `PROPERTY_VIEW`     — membre du logement, quel que soit son rôle ;
 *  - `PROPERTY_CONTRIBUTE` — idem : un occupant peut créer et modifier ses
 *    propres séjours, notes, travaux et l'inventaire courant ;
 *  - `PROPERTY_MANAGE`   — réservé au rôle local `manager` : administration
 *    du logement et de ses membres, écriture sur les ressources d'autrui.
 *
 * `ROLE_ADMIN` traverse tous les logements — c'est le super-rôle global, qui
 * conserve les capacités d'administration antérieures au multi-logements.
 */
final class PropertyVoter extends Voter
{
    public const VIEW = 'PROPERTY_VIEW';
    public const CONTRIBUTE = 'PROPERTY_CONTRIBUTE';
    public const MANAGE = 'PROPERTY_MANAGE';

    public function __construct(
        private readonly Security $security,
        private readonly PropertyMemberRepository $members,
    ) {
    }

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::CONTRIBUTE, self::MANAGE], true)
            && ($subject instanceof Property || null === $subject);
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        if (!$user instanceof User) {
            return false;
        }

        if ($this->security->isGranted('ROLE_ADMIN')) {
            return true;
        }

        // Un sujet null signifie que la ressource n'a pas (encore) de logement.
        // C'est le cas au `POST` quand le client omet le champ : la décision
        // revient alors à PropertyScopeProcessor, qui applique le repli
        // mono-logement ou refuse en 422.
        if (!$subject instanceof Property) {
            return false;
        }

        $role = $this->members->findRoleFor($user, $subject);

        if (null === $role) {
            return false;
        }

        return match ($attribute) {
            self::VIEW, self::CONTRIBUTE => true,
            self::MANAGE => PropertyRole::MANAGER === $role,
            default => false,
        };
    }
}
