<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\WeatherForecast;
use App\Weather\OpenMeteoClient;

/**
 * Backs the singleton `GET /api/weather` operation. Same pattern as
 * {@see MeProvider}: no Doctrine, just return the object built by the client.
 *
 * @implements ProviderInterface<WeatherForecast>
 */
final readonly class WeatherProvider implements ProviderInterface
{
    public function __construct(private OpenMeteoClient $client)
    {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WeatherForecast
    {
        return $this->client->getForecast();
    }
}
