<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\WeatherProvider;
use App\Weather\Dto\LocationForecast;

/**
 * Read-only weather resource, scoped to one property. Bundles every point that
 * property tracks — itself (`main`) and its optional secondary point
 * (`secondary`, typically a ski area) — in a single payload, so clients fetch
 * once and toggle between them locally.
 *
 * `GET /api/weather?property=/api/properties/1`. Le paramètre est facultatif
 * tant que l'utilisateur n'a qu'un logement, pour les builds mobiles antérieurs
 * au multi-logements.
 *
 * Not a Doctrine entity: data comes from Open-Meteo via {@see WeatherProvider}
 * and {@see \App\Weather\OpenMeteoClient} (HTTP + 30 min cache, keyed per
 * property). Exposed as the singleton `GET /api/weather`, following the provider
 * pattern used by {@see \App\State\MeProvider} for `/api/me`.
 *
 * Le cloisonnement Doctrine ne s'appliquant pas à une ressource non-Doctrine,
 * c'est {@see WeatherProvider} qui contrôle explicitement l'appartenance.
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
