<?php

namespace App\Weather;

use App\ApiResource\WeatherForecast;
use App\Weather\Dto\AirQuality;
use App\Weather\Dto\CurrentWeather;
use App\Weather\Dto\DailyForecast;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Fetches the weather for the apartment's coordinates from Open-Meteo (no API key
 * required) and caches it. Two endpoints are queried: the forecast API and the
 * air-quality API (different base URLs).
 *
 * Mirrors the HttpClient usage in {@see \App\Notification\Transport\ExpoPushTransport}.
 */
final readonly class OpenMeteoClient
{
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';
    private const AIR_QUALITY_URL = 'https://air-quality-api.open-meteo.com/v1/air-quality';

    private const CACHE_KEY = 'weather_villard';
    private const CACHE_TTL = 1800; // 30 min — Open-Meteo refreshes roughly hourly.
    private const FORECAST_DAYS = 16; // Open-Meteo free tier max horizon.

    public function __construct(
        private HttpClientInterface $httpClient,
        private CacheInterface $cache,
        private LoggerInterface $logger,
        #[Autowire('%app.weather.latitude%')] private float $latitude,
        #[Autowire('%app.weather.longitude%')] private float $longitude,
        #[Autowire('%app.weather.timezone%')] private string $timezone,
    ) {
    }

    public function getForecast(): WeatherForecast
    {
        return $this->cache->get(self::CACHE_KEY, function (ItemInterface $item): WeatherForecast {
            $item->expiresAfter(self::CACHE_TTL);

            return $this->fetch();
        });
    }

    private function fetch(): WeatherForecast
    {
        $forecast = $this->request(self::FORECAST_URL, [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'forecast_days' => self::FORECAST_DAYS,
            'current' => 'temperature_2m,apparent_temperature,weather_code,wind_speed_10m,relative_humidity_2m',
            'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_sum,snowfall_sum,wind_speed_10m_max,uv_index_max',
        ]);

        $air = $this->request(self::AIR_QUALITY_URL, [
            'latitude' => $this->latitude,
            'longitude' => $this->longitude,
            'timezone' => $this->timezone,
            'current' => 'european_aqi,pm2_5,pm10',
        ]);

        return $this->map($forecast, $air);
    }

    /**
     * @param array<string, mixed> $query
     *
     * @return array<string, mixed>
     */
    private function request(string $url, array $query): array
    {
        try {
            return $this->httpClient->request('GET', $url, ['query' => $query])->toArray();
        } catch (\Throwable $e) {
            $this->logger->error('Open-Meteo request failed', ['url' => $url, 'error' => $e->getMessage()]);

            throw new HttpException(502, 'Service météo momentanément indisponible.', $e);
        }
    }

    /**
     * @param array<string, mixed> $forecast
     * @param array<string, mixed> $air
     */
    private function map(array $forecast, array $air): WeatherForecast
    {
        $c = $forecast['current'] ?? [];
        $current = new CurrentWeather(
            temperature: (float) ($c['temperature_2m'] ?? 0),
            apparentTemperature: (float) ($c['apparent_temperature'] ?? 0),
            weatherCode: (int) ($c['weather_code'] ?? 0),
            windSpeed: (float) ($c['wind_speed_10m'] ?? 0),
            humidity: (int) ($c['relative_humidity_2m'] ?? 0),
            time: (string) ($c['time'] ?? ''),
        );

        $d = $forecast['daily'] ?? [];
        $daily = [];
        foreach (($d['time'] ?? []) as $i => $date) {
            $daily[] = new DailyForecast(
                date: (string) $date,
                weatherCode: (int) ($d['weather_code'][$i] ?? 0),
                tempMin: (float) ($d['temperature_2m_min'][$i] ?? 0),
                tempMax: (float) ($d['temperature_2m_max'][$i] ?? 0),
                precipitation: (float) ($d['precipitation_sum'][$i] ?? 0),
                snowfall: (float) ($d['snowfall_sum'][$i] ?? 0),
                windMax: (float) ($d['wind_speed_10m_max'][$i] ?? 0),
                uvMax: (float) ($d['uv_index_max'][$i] ?? 0),
            );
        }

        $a = $air['current'] ?? [];
        $airQuality = new AirQuality(
            europeanAqi: isset($a['european_aqi']) ? (int) $a['european_aqi'] : null,
            pm25: isset($a['pm2_5']) ? (float) $a['pm2_5'] : null,
            pm10: isset($a['pm10']) ? (float) $a['pm10'] : null,
        );

        return new WeatherForecast(
            latitude: $this->latitude,
            longitude: $this->longitude,
            timezone: $this->timezone,
            current: $current,
            daily: $daily,
            airQuality: $airQuality,
        );
    }
}
