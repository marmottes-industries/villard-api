<?php

namespace App\Serializer;

use App\Entity\Image;
use Symfony\Component\HttpFoundation\UriSigner;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareInterface;
use Symfony\Component\Serializer\Normalizer\NormalizerAwareTrait;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

/**
 * Ajoute `url` à chaque {@see Image} sérialisée : l'adresse signée de
 * {@see \App\Controller\ImageFileController}.
 *
 * Une balise `<img>` ou un `<Image>` React Native ne peut pas envoyer de JWT,
 * d'où la signature (HMAC sur `kernel.secret`) plutôt qu'un contrôle
 * d'authentification.
 *
 * L'URL est un **chemin** (`/api/images/12/file?...`), que chaque client
 * préfixe avec sa propre URL d'API. Une URL absolue dépendrait du schéma et de
 * l'hôte vus par PHP : derrière un proxy TLS non déclaré, elle sortirait en
 * `http://` et serait bloquée en contenu mixte sur le front en HTTPS.
 *
 * L'expiration est arrondie au surlendemain minuit : l'URL reste identique
 * toute la journée, ce qui laisse le cache du navigateur et de l'appli
 * fonctionner, et vaut entre 24 et 48 h. Une URL signée à la seconde changerait
 * à chaque chargement de liste et re-téléchargerait toutes les photos.
 */
final class ImageNormalizer implements NormalizerInterface, NormalizerAwareInterface
{
    use NormalizerAwareTrait;

    private const ALREADY_CALLED = 'image_normalizer_already_called';

    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UriSigner $uriSigner,
    ) {
    }

    public function normalize(mixed $data, ?string $format = null, array $context = []): array|string|int|float|bool|\ArrayObject|null
    {
        $context[self::ALREADY_CALLED][spl_object_id($data)] = true;

        $normalized = $this->normalizer->normalize($data, $format, $context);

        if (\is_array($normalized) && null !== $data->getId()) {
            $path = $this->urlGenerator->generate('image_file', ['id' => $data->getId()]);
            $normalized['url'] = $this->uriSigner->sign($path, new \DateTimeImmutable('tomorrow +1 day'));
        }

        return $normalized;
    }

    public function supportsNormalization(mixed $data, ?string $format = null, array $context = []): bool
    {
        return $data instanceof Image && !isset($context[self::ALREADY_CALLED][spl_object_id($data)]);
    }

    public function getSupportedTypes(?string $format): array
    {
        // Pas cacheable : le support dépend du contexte (garde anti-récursion).
        return [Image::class => false];
    }
}
