<?php

namespace App\State;

use ApiPlatform\Metadata\Operation;
use ApiPlatform\Metadata\Post;
use ApiPlatform\State\ProcessorInterface;
use App\Entity\Note;
use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @implements ProcessorInterface<Note, Note>
 */
final readonly class NoteProcessor implements ProcessorInterface
{
    public function __construct(
        // Chaîné sur PropertyScopeProcessor, qui délègue lui-même à
        // PersistProcessor après avoir résolu et vérifié le logement.
        private PropertyScopeProcessor $persistProcessor,
        private Security $security,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Note
    {
        if ($operation instanceof Post && $data instanceof Note) {
            $data->setCreatedAt(new \DateTimeImmutable());

            if ($data->getAuthor() === null) {
                $user = $this->security->getUser();
                if ($user instanceof User) {
                    $data->setAuthor($user);
                }
            }
        }

        return $this->persistProcessor->process($data, $operation, $uriVariables, $context);
    }
}
