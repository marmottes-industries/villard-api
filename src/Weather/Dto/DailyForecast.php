<?php

namespace App\Weather\Dto;

/**
 * One day of the daily forecast. The front-end matches `date` against occupation
 * dates to highlight the days that fall during a stay.
 */
final readonly class DailyForecast
{
    public function __construct(
        public string $date,
        public int $weatherCode,
        public float $tempMin,
        public float $tempMax,
        public float $precipitation,
        public float $snowfall,
        public float $windMax,
        public float $uvMax,
    ) {
    }
}
