<?php

namespace App\State;

use ApiPlatform\Doctrine\Common\State\PersistProcessor;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\User;
use App\Entity\Work;
use App\Enum\WorkStatus;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

/**
 * @implements ProcessorInterface<Work, Work>
 */
final readonly class WorkProcessor implements ProcessorInterface
{
    public function __construct(
        #[Autowire(service: PersistProcessor::class)]
        private ProcessorInterface $persistProcessor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Work
    {
        if (!$data instanceof Work) {
            return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
        }

        if ($operation instanceof Post) {
            $data->setCreatedAt(new \DateTimeImmutable());

            if ($data->getAuthor() === null) {
                $user = $this->security->getUser();
                if ($user instanceof User) {
                    $data->setAuthor($user);
                }
            }
        }

        if ($data->getStatus() === WorkStatus::DONE && $data->getCompletedAt() === null) {
            $data->setCompletedAt(new \DateTimeImmutable());
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
