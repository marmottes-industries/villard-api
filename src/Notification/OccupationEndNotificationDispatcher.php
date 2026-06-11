<?php

namespace App\Notification;

use App\Repository\OccupationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Sends the end-of-stay notification for every occupation ending on a given day.
 *
 * Idempotent: each occupation is stamped with endNotifiedAt once notified
 * (findEndingOn filters those out), so running it several times never
 * double-sends. Shared by the CLI command and the cron HTTP endpoint.
 */
final class OccupationEndNotificationDispatcher
{
    public function __construct(
        private readonly OccupationRepository $occupations,
        private readonly Notifier $notifier,
        private readonly EntityManagerInterface $em,
        #[Autowire('%app.web_url%')]
        private readonly string $webUrl,
    ) {
    }

    /**
     * @return int number of notifications dispatched
     */
    public function dispatch(\DateTimeImmutable $day): int
    {
        $occupations = $this->occupations->findEndingOn($day);

        $sent = 0;
        foreach ($occupations as $occupation) {
            $this->notifier->send(new OccupationEndingNotification($occupation, $this->webUrl));
            $occupation->setEndNotifiedAt(new \DateTimeImmutable());
            ++$sent;
        }

        $this->em->flush();

        return $sent;
    }
}
