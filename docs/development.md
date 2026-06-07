# Développement

Commandes et workflows pour le développement quotidien sur `villard-api`.

## Démarrer le serveur

```bash
symfony server:start
```

La Symfony CLI :

1. Lit `.symfony.local.yaml` et déclenche `docker compose up -d` → MariaDB démarre.
2. Démarre un serveur web local (HTTPS sur `https://127.0.0.1:8000` par défaut).

Alternative manuelle :

```bash
docker compose up -d
symfony serve -d
```

Arrêt :

```bash
symfony server:stop
docker compose down            # arrête MariaDB
```

## Doctrine

### Générer une migration depuis les changements d'entités

```bash
php bin/console doctrine:migrations:diff
```

Crée un nouveau fichier dans `migrations/` (versionné).

### Appliquer les migrations

```bash
php bin/console doctrine:migrations:migrate --no-interaction
```

### Inspecter le schéma

```bash
php bin/console doctrine:schema:validate
php bin/console doctrine:migrations:status
```

> **À éviter** : `doctrine:schema:update --force` — utilise toujours des migrations.

## Fixtures

```bash
php bin/console doctrine:fixtures:load --no-interaction
```

`AppFixtures` (`src/DataFixtures/AppFixtures.php`) charge :

- 6 utilisateurs (1 admin + 5 standards, mot de passe = username)
- 8 catégories (Cuisine, Salle de bain, Chambre, Salon, Extérieur, Cave, Produits frais, Épicerie)
- ~25 objets d'inventaire répartis sur les catégories
- 10 articles de courses (mélange de purchased true/false)
- 7 occupations sur juin 2026
- 6 notes datées de juin 2026

Liste complète des comptes : voir [`getting-started.md`](getting-started.md#5-charger-les-fixtures-dev).

> Le bundle Fixtures n'est activé qu'en `dev` et `test` (`config/bundles.php`). Pas de risque en prod.

## Commandes console

```bash
# Lister les routes
php bin/console debug:router

# Lister les services
php bin/console debug:container

# Voir la configuration résolue d'un bundle
php bin/console debug:config security
php bin/console debug:config api_platform

# Voir les entités mappées
php bin/console doctrine:mapping:info

# Vider les caches
php bin/console cache:clear
```

### Commandes applicatives

`app:create-user` (`src/Command/CreateUserCommand.php`) :

```bash
php bin/console app:create-user alice              # crée un admin
php bin/console app:create-user alice --no-admin   # crée un user simple
```

Le mot de passe est demandé en interactif (saisie masquée), puis hashé via `UserPasswordHasherInterface`.

## Documentation Swagger

`https://127.0.0.1:8000/api/docs` — Swagger UI généré par API Platform. Accès public. Inclut un bouton « Authorize » qui injecte un `Authorization: Bearer ...` sur toutes les requêtes (clé API déclarée dans `api_platform.yaml`).

Pratique pour explorer rapidement le CRUD sans coder de client.

## Tests

Pas encore de suite de tests dans le dépôt (`tests/` absent, PHPUnit non installé). Quand on en ajoutera :

- DB de test : suffixe `_test` géré automatiquement par `doctrine.yaml` (`when@test`).
- `security.yaml` réduit le coût du hashage de mot de passe en `when@test` (algorithme `auto`, `cost: 4`, `memory_cost: 10`) pour accélérer les tests.

## Workflow recommandé pour une évolution d'entité

1. Modifier le PHP dans `src/Entity/`.
2. `php bin/console doctrine:migrations:diff` → vérifier le diff généré dans `migrations/`.
3. `php bin/console doctrine:migrations:migrate --no-interaction`.
4. Mettre à jour les fixtures dans `src/DataFixtures/AppFixtures.php` si pertinent.
5. Si l'évolution est exposée à l'API : mettre à jour [`docs/resources.md`](resources.md) **et** [`API.md`](../API.md) à la racine (utilisé par le front).
6. Vérifier sur `/api/docs` que le contrat reflète bien les changements.

## Points d'attention

- **Pas de breaking change sur l'API** sans concertation avec les clients. Ajouter des champs nullables, créer un nouveau endpoint ou un nouveau groupe de sérialisation plutôt que casser l'existant.
- **Groupes de sérialisation** : pour l'instant seul `User` les utilise. Avant d'exposer un nouveau champ sensible sur une autre entité, ajouter `normalizationContext` / `denormalizationContext` avant de mettre en prod.
- **JWT** : ne pas commiter les clés (`config/jwt/*.pem`) ; le `.gitignore` les ignore déjà.
