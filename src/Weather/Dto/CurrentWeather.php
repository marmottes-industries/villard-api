<?php

namespace App\Weather\Dto;

/**
 * Current conditions as reported by Open-Meteo (`current` block of the forecast API).
 */
final readonly class CurrentWeather
{
    public function __construct(
        public float $temperature,
        public float $apparentTemperature,
        public int $weatherCode,
        public float $windSpeed,
        public int $humidity,
        public string $time,
    ) {
    }
}
