<?php

namespace App\Controller;

use App\Notification\OccupationEndNotificationDispatcher;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * HTTP entry points for the scheduled tasks (Infomaniak task scheduler, which
 * can only call a URL — not a shell command).
 *
 * These routes are public (^/api/cron is PUBLIC_ACCESS in security.yaml) and
 * therefore guarded by a shared secret passed as ?token=…. Keep the configured
 * URL private; rotate APP_CRON_SECRET if it leaks.
 */
final class CronController
{
    public function __construct(
        private readonly OccupationEndNotificationDispatcher $dispatcher,
        private readonly string $cronSecret,
    ) {
    }

    #[Route('/api/cron/occupation-end-notifications', name: 'cron_occupation_end', methods: 'GET')]
    public function occupationEndNotifications(Request $request): JsonResponse
    {
        $provided = (string) $request->query->get('token', '');

        if ('' === $this->cronSecret || !hash_equals($this->cronSecret, $provided)) {
            return new JsonResponse(['error' => 'Forbidden'], Response::HTTP_FORBIDDEN);
        }

        $day = new \DateTimeImmutable('today');
        $sent = $this->dispatcher->dispatch($day);

        return new JsonResponse([
            'date' => $day->format('Y-m-d'),
            'dispatched' => $sent,
        ]);
    }
}
