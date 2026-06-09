<?php

namespace App\Controller;

use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class AppVersionController
{
    public function __construct(
        private readonly string $latestVersion,
        private readonly string $minVersion,
        private readonly string $iosStoreUrl,
        private readonly string $androidStoreUrl,
    )
    {}

    #[Route('/api/app/version', name: 'app_version', methods: 'GET')]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'latestVersion' => $this->latestVersion,
            'minVersion' => $this->minVersion,
            'iosStoreUrl' => $this->iosStoreUrl,
            'androidStoreUrl' => $this->androidStoreUrl,
        ]);
    }
}
