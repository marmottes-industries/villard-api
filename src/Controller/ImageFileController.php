<?php

namespace App\Controller;

use App\Image\ImageStorage;
use App\Repository\ImageRepository;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\HttpKernel\Attribute\AsController;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Sert le fichier d'une image à partir de l'URL signée produite par
 * {@see \App\Serializer\ImageNormalizer}.
 *
 * La route est publique (`^/api/images/\d+/file` en `PUBLIC_ACCESS` dans
 * security.yaml) : c'est la signature, et non un JWT, qui autorise l'accès.
 * Un contrôleur plutôt qu'une opération API Platform, parce que la réponse est
 * un binaire et pas une ressource sérialisée.
 */
#[AsController]
final readonly class ImageFileController
{
    public function __construct(
        private ImageRepository $images,
        private ImageStorage $storage,
        private UriSigner $uriSigner,
    ) {
    }

    #[Route('/api/images/{id}/file', name: 'image_file', requirements: ['id' => '\d+'], methods: ['GET', 'HEAD'])]
    public function __invoke(Request $request, int $id): BinaryFileResponse
    {
        // Signature calculée sur le chemin seul, cf. ImageNormalizer : on ne
        // compare donc ni le schéma ni l'hôte de la requête.
        $signedPath = $request->getBaseUrl().$request->getPathInfo().'?'.$request->getQueryString();

        if (!$this->uriSigner->check($signedPath)) {
            throw new AccessDeniedHttpException('Lien invalide ou expiré.');
        }

        $image = $this->images->find($id);
        $path = null !== $image ? $this->storage->path($image) : null;

        if (null === $path || !is_file($path)) {
            throw new NotFoundHttpException('Image introuvable.');
        }

        $response = new BinaryFileResponse($path, headers: ['Content-Type' => $image->getMimeType()]);
        $response->setContentDisposition(ResponseHeaderBag::DISPOSITION_INLINE, 'image-'.$id.'.'.pathinfo($path, \PATHINFO_EXTENSION));
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }
}
