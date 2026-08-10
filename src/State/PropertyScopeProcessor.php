<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Contract\PropertyScopedInterface;
use App\Entity\Property;
use App\Entity\User;
use App\Repository\PropertyMemberRepository;
use App\Repository\PropertyRepository;
use App\Security\Voter\PropertyVoter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;

/**
 * Renseigne le logement d'une ressource métier au `POST` quand le client
 * l'omet, et vérifie une dernière fois l'autorisation avant persistance.
 *
 * Sans ce repli, les builds mobiles déjà installés — qui ne connaissent pas
 * le champ `property` — casseraient net à la mise à jour de l'API. Tant que
 * l'utilisateur n'appartient qu'à un seul logement, l'omission est donc
 * résolue silencieusement ; au-delà, la requête est refusée en 422 avec un
 * message explicite, et c'est le forçage de mise à jour
 * (`APP_VERSION_LATEST`) qui prend le relais.
 *
 * Chaîne de décoration : `NoteProcessor` / `WorkProcessor` → ce processor →
 * `PersistProcessor`. {@see NoteProcessor} pour le pattern d'origine.
 *
 * @implements ProcessorInterface<PropertyScopedInterface|object, object>
 */
final readonly class PropertyScopeProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private PropertyMemberRepository $members,
        private PropertyRepository $properties,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): mixed
    {
        if ($data instanceof PropertyScopedInterface) {
            if ($operation instanceof Post && null === $data->getProperty()) {
                $data->setProperty($this->resolveDefaultProperty());
            }

            // Filet de sécurité : les expressions `securityPostDenormalize`
            // tolèrent un logement nul au `POST` pour laisser passer le repli
            // ci-dessus. On revérifie donc ici, une fois le champ résolu.
            if (!$this->security->isGranted(PropertyVoter::CONTRIBUTE, $data->getProperty())) {
                throw new UnprocessableEntityHttpException('Logement invalide ou non autorisé.');
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }

    /**
     * Repli mono-logement : n'aboutit que si l'utilisateur appartient à
     * exactement un logement. Un `ROLE_ADMIN` sans appartenance doit, lui,
     * toujours désigner le logement explicitement.
     */
    private function resolveDefaultProperty(): ?Property
    {
        $user = $this->security->getUser();

        if (!$user instanceof User) {
            return null;
        }

        $propertyIds = $this->members->findPropertyIdsForUser($user);

        if (1 === \count($propertyIds)) {
            return $this->properties->find($propertyIds[0]);
        }

        throw new UnprocessableEntityHttpException(
            [] === $propertyIds
                ? 'Aucun logement ne peut être déduit : le champ « property » est obligatoire.'
                : 'Vous êtes membre de plusieurs logements : le champ « property » est obligatoire.'
        );
    }
}
