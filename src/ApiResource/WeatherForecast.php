<?php

namespace App\ApiResource;

use ApiPlatform\Metadata\ApiResource;
use ApiPlatform\Metadata\Get;
use App\State\WeatherProvider;
use App\Weather\Dto\AirQuality;
use App\Weather\Dto\CurrentWeather;
use App\Weather\Dto\DailyForecast;

/**
 * Read-only weather resource for the apartment's location (Villard-de-Lans).
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
        public float $latitude,
        public float $longitude,
        public string $timezone,
        public CurrentWeather $current,
        /** @var DailyForecast[] */
        public array $daily,
        public AirQuality $airQuality,
    ) {
    }
}
