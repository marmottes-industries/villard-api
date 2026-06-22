<?php

namespace App\Weather\Dto;

/**
 * Air quality snapshot from Open-Meteo's air-quality API (separate base URL).
 * Fields are nullable: the air-quality service can be momentarily unavailable
 * without failing the whole forecast.
 */
final readonly class AirQuality
{
    public function __construct(
        public ?int $europeanAqi,
        public ?float $pm25,
        public ?float $pm10,
    ) {
    }
}
