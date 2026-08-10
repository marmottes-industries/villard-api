<?php

namespace App\Notification;

use App\Entity\Occupation;
use App\Entity\Property;
use App\Entity\User;

/**
 * Sent to the occupant on the last day of their stay, reminding them to update the
 * inventory and the shopping list before they leave.
 */
final readonly class OccupationEndingNotification implements AppNotification
{
    public function __construct(
        private Occupation $occupation,
        private string $webUrl,
    ) {
    }

    public function getRecipient(): User
    {
        $occupant = $this->occupation->getOccupant();
        \assert($occupant instanceof User);

        return $occupant;
    }

    public function getChannels(): array
    {
        return [Channel::Mail, Channel::Push];
    }

    /**
     * Nom du logement concerné. Une notification ne vaut que pour un logement
     * précis : avec plusieurs logements, un message générique serait ambigu.
     */
    private function getPropertyName(): string
    {
        $property = $this->occupation->getProperty();
        \assert($property instanceof Property);

        return (string) $property->getName();
    }

    /**
     * Le nom du logement est placé en apposition, jamais après une préposition :
     * « séjour à Le Cabanon » serait fautif, et on ne peut pas contracter
     * l'article d'un nom saisi librement par un gestionnaire.
     */
    public function getSubject(): string
    {
        return \sprintf('%s — fin de séjour, pensez à l\'inventaire et aux courses', $this->getPropertyName());
    }

    public function getMailTemplate(): string
    {
        return 'emails/occupation_end.html.twig';
    }

    public function getContext(): array
    {
        return [
            'username' => $this->getRecipient()->getUsername(),
            'endDate' => $this->occupation->getEndDate(),
            'propertyName' => $this->getPropertyName(),
            'webUrl' => $this->webUrl,
        ];
    }

    public function getPushBody(): string
    {
        return \sprintf(
            '%s — vous arrivez à la fin de votre séjour, avez-vous pensé à mettre à jour '
            .'l\'inventaire et la liste de courses ?',
            $this->getPropertyName()
        );
    }

    public function getPushData(): array
    {
        // Deep-link the app reads on tap (see app/_layout.tsx notification handler).
        return ['route' => '/(tabs)/inventaire'];
    }
}
