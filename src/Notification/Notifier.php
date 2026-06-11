<?php

namespace App\Notification;

use App\Notification\Transport\NotificationTransport;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Central entry point for sending notifications. Resolves each notification's
 * channels to the matching {@see NotificationTransport} and delegates delivery.
 *
 * Usage: $notifier->send(new SomeNotification(...));
 */
final class Notifier
{
    /** @var array<string, NotificationTransport> indexed by Channel value */
    private array $transports = [];

    /**
     * @param iterable<NotificationTransport> $transports
     */
    public function __construct(
        #[AutowireIterator('app.notification_transport')]
        iterable $transports,
        private readonly LoggerInterface $logger,
    ) {
        foreach ($transports as $transport) {
            $this->transports[$transport->getChannel()->value] = $transport;
        }
    }

    public function send(AppNotification $notification): void
    {
        foreach ($notification->getChannels() as $channel) {
            $transport = $this->transports[$channel->value] ?? null;
            if ($transport === null) {
                $this->logger->warning('No transport registered for notification channel', [
                    'channel' => $channel->value,
                ]);
                continue;
            }

            try {
                $transport->send($notification);
            } catch (\Throwable $e) {
                // One failing channel must not prevent the others from delivering.
                $this->logger->error('Notification channel failed', [
                    'channel' => $channel->value,
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
