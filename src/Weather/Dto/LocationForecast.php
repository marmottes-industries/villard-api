<?php

namespace App\Weather\Dto;

/**
 * Forecast for one named point of a property. `key` is `main` (the property
 * itself) or `secondary` (its optional second point — typically a ski/hiking
 * area sitting several hundred metres higher, with its own snow depth and
 * temperatures). A property without a secondary point yields a single entry.
 *
 * Les clés sont stables d'un logement à l'autre : un client peut cibler `main`
 * sans connaître le logement affiché.
 */
final readonly class LocationForecast
{
    public function __construct(
        public string $key,
        public string $name,
        public float $latitude,
        public float $longitude,
        public float $elevation,
        public CurrentWeather $current,
        /** @var DailyForecast[] */
        public array $daily,
        public AirQuality $airQuality,
    ) {
    }
}
