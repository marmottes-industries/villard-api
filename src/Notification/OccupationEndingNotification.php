<?php

namespace App\Notification;

use App\Entity\Occupation;
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

    public function getSubject(): string
    {
        return 'Fin de séjour — pensez à l\'inventaire et aux courses';
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
            'webUrl' => $this->webUrl,
        ];
    }

    public function getPushBody(): string
    {
        return 'Vous arrivez à la fin de votre séjour, avez-vous pensé à mettre à jour '
            .'l\'inventaire et la liste de courses ?';
    }

    public function getPushData(): array
    {
        // Deep-link the app reads on tap (see app/_layout.tsx notification handler).
        return ['route' => '/(tabs)/inventaire'];
    }
}
