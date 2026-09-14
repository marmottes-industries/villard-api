<?php

namespace App\State;

use ApiPlatform\Metadata\Exception\InvalidArgumentException;
use ApiPlatform\Metadata\Exception\ItemNotFoundException;
use ApiPlatform\Metadata\IriConverterInterface;
use ApiPlatform\Metadata\Operation;
use ApiPlatform\State\ProcessorInterface;
use ApiPlatform\Validator\Exception\ValidationException;
use App\Entity\Image;
use App\Entity\Note;
use App\Entity\User;
use App\Entity\Work;
use App\Image\ImageOptimizer;
use App\Image\ImageStorage;
use App\Security\Voter\PropertyVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\UnprocessableEntityHttpException;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

/**
 * `POST /api/images` en `multipart/form-data` : `file` et l'IRI du parent dans
 * `note` **ou** `work`.
 *
 * L'opération ne désérialise rien (`deserialize: false`) : il n'y a donc ni
 * objet pour `securityPostDenormalize`, ni validation automatique. Les deux
 * sont faits ici, dans cet ordre : parent, droits, fichier, compression,
 * stockage, persistance.
 *
 * La résolution de l'IRI passe par le provider Doctrine d'API Platform, donc
 * par {@see \App\Doctrine\PropertyScopeExtension} : le parent d'un logement
 * dont l'utilisateur n'est pas membre est tout simplement introuvable.
 *
 * @implements ProcessorInterface<null, Image>
 */
final readonly class ImageUploadProcessor implements ProcessorInterface
{
    public const MAX_IMAGES_PER_PARENT = 10;

    public function __construct(
        private RequestStack $requestStack,
        private IriConverterInterface $iriConverter,
        private Security $security,
        private ValidatorInterface $validator,
        private ImageOptimizer $optimizer,
        private ImageStorage $storage,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function process(mixed $data, Operation $operation, array $uriVariables = [], array $context = []): Image
    {
        $request = $this->requestStack->getCurrentRequest()
            ?? throw new \LogicException('Aucune requête HTTP en cours.');

        $parent = $this->resolveParent(
            $request->request->getString('note'),
            $request->request->getString('work'),
        );

        $this->denyUnlessCanEdit($parent);

        if ($parent->getImages()->count() >= self::MAX_IMAGES_PER_PARENT) {
            throw new UnprocessableEntityHttpException(\sprintf('%d images maximum.', self::MAX_IMAGES_PER_PARENT));
        }

        $file = $this->validatedFile($request->files->get('file'));
        $optimized = $this->optimizer->optimize($file->getPathname());

        $image = (new Image())
            ->setMimeType($optimized->mimeType)
            ->setSize($optimized->size)
            ->setWidth($optimized->width)
            ->setHeight($optimized->height)
            ->setCreatedAt(new \DateTimeImmutable());

        try {
            $image->setStoredName($this->storage->store($optimized->path, $optimized->extension));
        } finally {
            if ($optimized->temporary) {
                @unlink($optimized->path);
            }
        }

        $parent->addImage($image);

        try {
            $this->entityManager->persist($image);
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            // Pas de ligne en base : le fichier serait orphelin.
            $this->storage->delete((string) $image->getStoredName());

            throw $e;
        }

        return $image;
    }

    private function resolveParent(string $noteIri, string $workIri): Note|Work
    {
        if (('' === $noteIri) === ('' === $workIri)) {
            throw new UnprocessableEntityHttpException('Indiquez soit « note », soit « work ».');
        }

        try {
            $parent = $this->iriConverter->getResourceFromIri('' !== $noteIri ? $noteIri : $workIri);
        } catch (ItemNotFoundException|InvalidArgumentException) {
            $parent = null;
        }

        $expected = '' !== $noteIri ? Note::class : Work::class;

        if (!$parent instanceof $expected) {
            throw new UnprocessableEntityHttpException('' !== $noteIri ? 'Note introuvable.' : 'Travaux introuvables.');
        }

        return $parent;
    }

    /**
     * Même règle que le `PATCH` du parent : manager du logement, ou
     * contributeur auteur du parent.
     */
    private function denyUnlessCanEdit(Note|Work $parent): void
    {
        $property = $parent->getProperty();

        if ($this->security->isGranted(PropertyVoter::MANAGE, $property)) {
            return;
        }

        $user = $this->security->getUser();
        $isAuthor = $user instanceof User && $parent->getAuthor()?->getId() === $user->getId();

        if (!$isAuthor || !$this->security->isGranted(PropertyVoter::CONTRIBUTE, $property)) {
            throw new AccessDeniedHttpException("Vous n'avez pas les droits pour ajouter une image ici.");
        }
    }

    private function validatedFile(mixed $file): UploadedFile
    {
        // Un envoi qui dépasse `post_max_size` arrive sans aucun champ : c'est
        // la cause la plus probable d'un fichier absent.
        if (!$file instanceof UploadedFile) {
            throw new UnprocessableEntityHttpException('Aucun fichier reçu (fichier trop volumineux ?).');
        }

        $violations = $this->validator->validate($file, [
            new Assert\File(
                maxSize: '15M',
                mimeTypes: ['image/jpeg', 'image/png', 'image/webp'],
                maxSizeMessage: 'Image trop volumineuse ({{ size }} {{ suffix }}), {{ limit }} {{ suffix }} maximum.',
                mimeTypesMessage: 'Format non supporté : JPEG, PNG ou WebP uniquement.',
                uploadIniSizeErrorMessage: 'Image trop volumineuse pour le serveur ({{ limit }} {{ suffix }} maximum).',
            ),
        ]);

        if (\count($violations) > 0) {
            throw new ValidationException($violations);
        }

        return $file;
    }
}
