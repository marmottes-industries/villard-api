<?php

namespace App\Validator;

use App\Contract\RoomScopedInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

/**
 * @see RoomBelongsToProperty
 */
final class RoomBelongsToPropertyValidator extends ConstraintValidator
{
    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof RoomBelongsToProperty) {
            throw new UnexpectedTypeException($constraint, RoomBelongsToProperty::class);
        }

        if (!$value instanceof RoomScopedInterface) {
            return;
        }

        $room = $value->getRoom();
        $property = $value->getProperty();

        /*
         * La validation s'exécute avant les processors : au `POST` où le client
         * omet `property`, le repli mono-logement de
         * {@see \App\State\PropertyScopeProcessor} n'a pas encore tourné et le
         * champ est encore nul. Comparer ici renverrait un faux 422 sur le cas
         * nominal — c'est exactement le piège qui avait cassé `POST /api/notes`
         * pour les non-admins. On ne compare donc que si les deux valeurs sont
         * connues ; le cas restant est rattrapé par le processor lui-même.
         */
        if (null === $room || null === $property) {
            return;
        }

        /*
         * Comparaison sur l'identifiant, pas sur l'instance : rien ne garantit
         * que Doctrine serve le même objet `Property` pour un proxy non
         * initialisé et pour l'IRI résolue depuis le payload.
         */
        if ($room->getProperty()?->getId() !== $property->getId()) {
            $this->context->buildViolation($constraint->message)
                ->atPath('room')
                ->addViolation();
        }
    }
}
