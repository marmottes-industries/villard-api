# ROADMAP

Feuille de route du projet, de l'initialisation à la mise en production sur VPS Infomaniak.

Lien claude design pour claude code : 
https://api.anthropic.com/v1/design/h/z19abI9e5ppuDqIeju-i3w?open_file=Les+M%C3%A9l%C3%A8zes.html
Implement: Les Marmottes.html 

> **À lire en premier — points faciles à manquer**
> - Le déploiement est **conteneurisé (Docker)** : il est volontairement **portable**. Si tu changes de VPS plus tard, seule la cible SSH change, pas l'architecture.
> - **Ordre critique** : ne configure la CI/CD (phase 5) qu'une fois l'app fonctionnelle en local *et* le VPS préparé (phase 4). Sauter l'ordre = pipeline qui déploie dans le vide.
> - **Secrets** : aucun secret (JWT, mots de passe DB, clés SSH) ne doit être commité. Utilise les *secrets* GitHub Actions et un `.env.local` jamais versionné. Vérifie ton `.gitignore` **avant** le premier commit.
> - Deux dépôts = deux pipelines CI/CD distincts. Ne les mélange pas.

---

## Phase 0 — Cadrage & préparation

- [ ] Créer les deux dépôts Git : `appart-api` et `appart-front`
- [ ] Définir le modèle de données (entités : `Occupation`, `InventoryItem`, `Category`, `ShoppingItem`, `User`, `Note`…)
- [ ] Mettre en place `.gitignore` corrects dès le départ (vérifier `.env.local`, `/vendor`, `/node_modules`, `/var`)
- [ ] Choisir la licence (MIT recommandé pour un portfolio)
- [ ] Rédiger le `README.md` initial

---

## Phase 1 — Backend : fondations API (`appart-api`)

- [ ] `composer create-project symfony/skeleton appart-api` (Symfony 8)
- [ ] Installer Doctrine ORM + PostgreSQL (`symfony/orm-pack`)
- [ ] Installer API Platform (`api-platform/core`)
- [ ] Créer un `docker-compose.yml` pour le **développement** (service `php`/FrankenPHP + service `database` PostgreSQL)
- [ ] Définir les entités Doctrine + attributs API Platform (`#[ApiResource]`)
- [ ] Générer et exécuter les premières migrations Doctrine
- [ ] Créer des **fixtures** avec données **fictives** (DoctrineFixturesBundle)
- [ ] Vérifier l'API auto-documentée (Swagger UI à `/api`)

**Gotcha** : API Platform expose tout par défaut. Pense aux groupes de sérialisation (`normalizationContext` / `denormalizationContext`) pour ne pas fuiter de champs.

---

## Phase 2 — Backend : authentification & sécurité

- [ ] Installer l'authentification JWT (LexikJWTAuthenticationBundle)
- [ ] Générer la paire de clés JWT (⚠️ **ne pas committer** — passer par les secrets)
- [ ] Configurer le firewall Symfony (route `/api/login` stateless)
- [ ] Protéger les ressources API (`security` attributes / voters)
- [ ] Gérer le CORS (`nelmio/cors-bundle`) pour autoriser le front (et plus tard le mobile)

**Gotcha** : le CORS mal configuré est la cause #1 de "ça marche en local mais pas en prod". Liste explicitement les origines autorisées par environnement.

---

## Phase 3 — Frontend : SPA Vue 3 (`appart-front`)

- [ ] `npm create vite@latest appart-front -- --template vue-ts`
- [ ] Installer Vue Router + Pinia
- [ ] Configurer un client HTTP (Axios ou fetch wrappé) avec base URL par variable d'environnement (`VITE_API_URL`)
- [ ] Gérer le stockage du token JWT et son injection dans les requêtes
- [ ] Construire les vues : authentification, calendrier d'occupation, inventaire, courses, notes
- [ ] Gérer les états de chargement / erreurs
- [ ] Écrire quelques tests (Vitest)

**Gotcha** : ne hardcode jamais l'URL de l'API. `VITE_API_URL` doit différer entre dev (`localhost`) et prod (domaine du VPS). Une variable `VITE_*` est injectée **au build**, pas au runtime — il faut donc rebuild pour changer de cible.

---

## Phase 4 — Préparation du VPS Infomaniak

- [ ] Accès SSH au VPS, créer un **utilisateur dédié non-root** pour le déploiement
- [ ] Installer Docker + Docker Compose sur le VPS
- [ ] Configurer le pare-feu (UFW : ouvrir 22, 80, 443 uniquement)
- [ ] Pointer le(s) domaine(s)/sous-domaines vers l'IP du VPS (DNS)
- [ ] Mettre en place un **reverse proxy** (Traefik ou Caddy) gérant le HTTPS automatique (Let's Encrypt)
  - `api.mondomaine.xyz` → conteneur API
  - `app.mondomaine.xyz` → conteneur front (fichiers statiques servis par Nginx/Caddy)
- [ ] Préparer un `docker-compose.prod.yml` (images de prod, pas de volumes de dev, variables via `.env` serveur)

**Gotcha** : le HTTPS doit être en place **avant** d'exposer l'API publiquement (le JWT transite dans les en-têtes). Traefik/Caddy automatisent Let's Encrypt — privilégie ça à une config Nginx manuelle.

---

## Phase 5 — CI/CD (GitHub Actions)

Deux workflows distincts, un par dépôt.

### `appart-api`
- [ ] Workflow CI : à chaque push/PR → lancer PHPStan, PHP-CS-Fixer (check), PHPUnit (avec une DB de test en service)
- [ ] Workflow CD : sur push `main` (ou tag) →
  - [ ] Build de l'image Docker de l'API
  - [ ] Push vers un registre (GitHub Container Registry — `ghcr.io`)
  - [ ] Connexion SSH au VPS → `docker compose pull` + `up -d`
  - [ ] Exécuter les migrations Doctrine (`doctrine:migrations:migrate --no-interaction`)

### `appart-front`
- [ ] Workflow CI : lint + Vitest
- [ ] Workflow CD : build Vite → déploiement des fichiers statiques (push image Nginx, ou rsync du `dist/` vers le volume servi par le reverse proxy)

**Gotchas** :
- Stocke clé SSH, identifiants registre et variables d'env dans les **GitHub Secrets**, jamais dans le YAML.
- Les **migrations** se jouent au déploiement, pas dans l'image. Une migration ratée doit faire échouer le déploiement (et idéalement déclencher un rollback — voir phase 7).

---

## Phase 6 — Scripts d'aide (bash)

Scripts pour réduire la friction au quotidien. À placer dans un dossier `scripts/` de chaque dépôt.

Exemples à écrire :
- [ ] `scripts/setup.sh` — install initiale locale (composer/npm install, up des conteneurs, migrations, fixtures) en une commande
- [ ] `scripts/deploy.sh` — déploiement manuel de secours (SSH + pull + up + migrate), utile si la CI/CD est indisponible
- [ ] `scripts/backup-db.sh` — `pg_dump` de la base de prod via SSH, horodaté
- [ ] `scripts/logs.sh` — récupération/suivi des logs des conteneurs distants

**Gotcha** : tout script qui touche la prod doit échouer proprement (`set -euo pipefail` en tête) pour ne pas continuer après une erreur.

---

## Phase 7 — Production : robustesse & exploitation

- [ ] **Sauvegardes automatiques** de la base (cron sur le VPS appelant `pg_dump`, rétention de N jours)
- [ ] Tester une **restauration** de sauvegarde (une sauvegarde non testée n'existe pas)
- [ ] Monitoring minimal (santé des conteneurs, espace disque)
- [ ] Healthcheck endpoint sur l'API + healthchecks Docker
- [ ] Stratégie de rollback (garder l'image précédente, redéployer en cas d'échec)
- [ ] Logs centralisés ou au moins consultables facilement

---

## Phase 8 — Documentation & finition portfolio

- [ ] Finaliser le `README.md` de chaque dépôt (captures, archi, choix techniques justifiés)
- [ ] Documenter les décisions d'architecture (un court `docs/ADR.md` : pourquoi 2 repos, pourquoi JWT, pourquoi Vite, etc.)
- [ ] Vérifier une dernière fois l'absence de données personnelles dans l'historique Git (`git log`, fichiers, fixtures)
- [ ] Rédiger le "pitch" du projet pour l'entretien : problème réel résolu, archi découplée, auto-hébergement, démarche dégafamisation

---

## Phase 9 — Mobile (ultérieur)

- [ ] Arbitrer la technologie (Symfony UX Native / Hotwire Native, React Native, Flutter, ou PWA) selon le besoin réel
- [ ] Nouveau dépôt `appart-mobile`
- [ ] Consommer l'API existante (l'auth JWT et le CORS sont déjà prévus pour des clients multiples)

---

## Récapitulatif des points critiques

1. **`.gitignore` et secrets corrects AVANT le premier commit** — un secret commité reste dans l'historique.
2. **Ordre des phases** : app fonctionnelle en local (1-3) → VPS prêt (4) → CI/CD (5). Pas l'inverse.
3. **CORS + HTTPS** : les deux pièges classiques du passage en prod.
4. **Sauvegardes testées** : une sauvegarde jamais restaurée n'est pas une sauvegarde.
5. **Docker = portabilité** : ta réserve sur le VPS Infomaniak est couverte, tu peux migrer ailleurs sans réécrire le déploiement.
