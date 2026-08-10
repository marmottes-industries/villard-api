<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProviderInterface;
use App\ApiResource\WeatherForecast;
use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyMemberRepository;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use App\Weather\OpenMeteoClient;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Backs the singleton `GET /api/weather` operation. Same pattern as
 * {@see MeProvider}: no Doctrine resource, just return the object built by the
 * client.
 *
 * `WeatherForecast` n'étant pas une entité Doctrine, l'extension de
 * cloisonnement ne s'y applique pas : la résolution du `?property=` et le
 * contrôle d'appartenance se font donc explicitement ici.
 *
 * Comme pour les créations de ressources métier, un `?property=` absent est
 * toléré tant que l'utilisateur n'a qu'un seul logement — les builds mobiles
 * antérieurs interrogent `/api/weather` sans paramètre.
 *
 * @implements ProviderInterface<WeatherForecast>
 */
final readonly class WeatherProvider implements ProviderInterface
{
    public function __construct(
        private OpenMeteoClient $client,
        private RequestStack $requestStack,
        private Security $security,
        private PropertyRepository $properties,
        private PropertyMemberRepository $members,
    ) {
    }

    public function provide(Operation $operation, array $uriVariables = [], array $context = []): WeatherForecast
    {
        return $this->client->getForecast($this->resolveProperty());
    }

    private function resolveProperty(): Property
    {
        $raw = $this->requestStack->getCurrentRequest()?->query->get('property');

        $property = null !== $raw && '' !== $raw
            ? $this->findByIriOrId((string) $raw)
            : $this->defaultProperty();

        if (!$this->security->isGranted(PropertyVoter::VIEW, $property)) {
            throw new AccessDeniedHttpException('Logement non autorisé.');
        }

        return $property;
    }

    /**
     * Accepte l'IRI (`/api/properties/1`) comme l'identifiant nu (`1`), à
     * l'image du `SearchFilter` d'API Platform sur les relations.
     */
    private function findByIriOrId(string $raw): Property
    {
        $id = filter_var(basename($raw), \FILTER_VALIDATE_INT);

        $property = false !== $id ? $this->properties->find($id) : null;

        if (null === $property) {
            throw new NotFoundHttpException(\sprintf('Logement « %s » introuvable.', $raw));
        }

        return $property;
    }

    private function defaultProperty(): Property
    {
        $user = $this->security->getUser();
        $propertyIds = $user instanceof User ? $this->members->findPropertyIdsForUser($user) : [];

        if (1 !== \count($propertyIds)) {
            throw new UnprocessableEntityHttpException(
                [] === $propertyIds
                    ? 'Aucun logement ne peut être déduit : le paramètre « property » est obligatoire.'
                    : 'Vous êtes membre de plusieurs logements : le paramètre « property » est obligatoire.'
            );
        }

        $property = $this->properties->find($propertyIds[0]);
        \assert($property instanceof Property);

        return $property;
    }
}
