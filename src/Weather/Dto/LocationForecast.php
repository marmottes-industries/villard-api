<?php

namespace App\Weather\Dto;

/**
 * Forecast for one named point. The app shows two: the apartment (Villard-de-Lans)
 * and the ski/hiking area (Côte 2000), which sits ~600 m higher and therefore has
 * its own conditions (snow depth, temperatures).
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
