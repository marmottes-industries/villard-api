<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\WeatherProvider;
use App\Weather\Dto\LocationForecast;

/**
 * Read-only weather resource. Bundles every tracked location (the apartment at
 * Villard-de-Lans and the Côte 2000 ski area) in a single payload so the clients
 * fetch once and toggle between them locally.
 *
 * Not a Doctrine entity: data comes from Open-Meteo via {@see WeatherProvider}
 * and {@see \App\Weather\OpenMeteoClient} (HTTP + 30 min cache). Exposed as the
 * singleton `GET /api/weather`, following the provider pattern used by
 * {@see \App\State\MeProvider} for `/api/me`.
 */
#[ApiResource(
    operations: [
        new Get(
            uriTemplate: '/weather',
            security: "is_granted('IS_AUTHENTICATED_FULLY')",
            provider: WeatherProvider::class,
        ),
    ],
)]
final readonly class WeatherForecast
{
    public function __construct(
        public string $timezone,
        /** @var LocationForecast[] */
        public array $locations,
    ) {
    }
}
