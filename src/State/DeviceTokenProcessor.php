<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\DeviceToken;
use App\Entity\User;
use App\Repository\DeviceTokenRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * Registers a push token. Attaches the current user as owner and upserts by token
 * value: re-registering the same device token (e.g. on every login) updates the
 * existing row instead of failing the unique constraint or creating duplicates.
 *
 * @implements ProcessorInterface<DeviceToken, DeviceToken>
 */
final readonly class DeviceTokenProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private Security $security,
        private DeviceTokenRepository $deviceTokens,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): DeviceToken
    {
        if (!$operation instanceof Post || !$data instanceof DeviceToken) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        $user = $this->security->getUser();
        $now = new \DateTimeImmutable();

        // Upsert: if this token already exists, update the existing row instead of
        // inserting a duplicate (the same install re-registers on each login).
        $existing = $data->getToken() !== null
            ? $this->deviceTokens->findOneByToken($data->getToken())
            : null;

        $entity = $existing ?? $data;
        $entity->setPlatform($data->getPlatform() ?? $entity->getPlatform() ?? 'web');
        if ($user instanceof User) {
            $entity->setOwner($user);
        }
        if ($existing === null) {
            $entity->setCreatedAt($now);
        }
        $entity->setLastSeenAt($now);

        return $this->persistProcessor->process($entity, $operation, $uriVariables, $context);
    }
}
