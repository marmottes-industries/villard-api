<?php

namespace App\Notification\Transport;

use App\Notification\AppNotification;
use App\Notification\Channel;
use App\Repository\DeviceTokenRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Sends a push notification to every device the recipient has registered, via the
 * Expo Push API (https://docs.expo.dev/push-notifications/sending-notifications/).
 * Tokens Expo reports as no longer registered are pruned so they aren't retried.
 */
final readonly class ExpoPushTransport implements NotificationTransport
{
    private const EXPO_PUSH_URL = 'https://exp.host/--/api/v2/push/send';

    public function __construct(
        private HttpClientInterface $httpClient,
        private DeviceTokenRepository $deviceTokens,
        private EntityManagerInterface $em,
        private LoggerInterface $logger,
    ) {
    }

    public function getChannel(): Channel
    {
        return Channel::Push;
    }

    public function send(AppNotification $notification): void
    {
        $devices = $this->deviceTokens->findByOwner($notification->getRecipient());
        if (!$devices) {
            return;
        }

        // Build one message per device, keeping the token order so we can map the
        // Expo response tickets back to the device rows.
        $messages = [];
        foreach ($devices as $device) {
            $messages[] = [
                'to' => $device->getToken(),
                'title' => $notification->getSubject(),
                'body' => $notification->getPushBody(),
                'data' => $notification->getPushData(),
            ];
        }

        try {
            $response = $this->httpClient->request('POST', self::EXPO_PUSH_URL, [
                'headers' => [
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/json',
                ],
                'json' => $messages,
            ]);

            $tickets = $response->toArray(false)['data'] ?? [];
        } catch (\Throwable $e) {
            $this->logger->error('Expo push request failed', ['error' => $e->getMessage()]);

            return;
        }

        $removed = false;
        foreach ($tickets as $i => $ticket) {
            if (($ticket['status'] ?? null) !== 'error') {
                continue;
            }

            $this->logger->warning('Expo push ticket error', [
                'message' => $ticket['message'] ?? null,
                'details' => $ticket['details'] ?? null,
            ]);

            if (($ticket['details']['error'] ?? null) === 'DeviceNotRegistered' && isset($devices[$i])) {
                $this->em->remove($devices[$i]);
                $removed = true;
            }
        }

        if ($removed) {
            $this->em->flush();
        }
    }
}
