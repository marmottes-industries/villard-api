# Gestion Appartement nom de code, les Marmottes — Réservations & Inventaire

Application personnelle et auto-hébergée de gestion d'un appartement familial : planning d'occupation (qui occupe le logement et quand) et suivi d'inventaire (linge, vaisselle, équipement, courses récurrentes, état des lieux).

Projet à double objectif : usage réel et auto-hébergement dans une démarche de sortie des services GAFAM (ownership des données), tout en servant de pièce de portfolio démontrant une architecture découplée moderne.

> **Note vie privée** : ce dépôt public ne contient aucune donnée personnelle (adresse, noms, contenu réel). Les seeds et fixtures utilisent des données fictives.

---

## Architecture

Architecture découplée en deux clients consommant une API centrale, conçue pour accueillir un troisième client (mobile) ultérieurement.

```
                 ┌─────────────────────┐
                 │   API Symfony 8     │
                 │   + API Platform    │
                 │   (REST / JSON)     │
                 └──────────┬──────────┘
                            │
            ┌───────────────┼───────────────┐
            │               │               │
     ┌──────┴──────┐ ┌──────┴──────┐ ┌──────┴────────┐
     │  SPA Vue 3  │ │   Mobile    │ │   (futurs     │
     │  (Vite)     │ │  (à venir)  │ │   clients)    │
     └─────────────┘ └─────────────┘ └───────────────┘
```

Deux dépôts Git distincts :
- **`appart-api`** — backend Symfony 8 + API Platform
- **`appart-front`** — SPA Vue 3 (Vite)

Le choix de deux dépôts plutôt qu'un monorepo reflète l'indépendance réelle des cycles de vie : l'API et le front se déploient, se versionnent et se testent séparément. Le client mobile (dépôt à venir) consommera la même API sans modification côté backend.

---

## Stack technique

### Backend (`appart-api`)
- **PHP 8.3+ / Symfony 8**
- **API Platform** — exposition REST automatique à partir des entités Doctrine
- **Doctrine ORM** — persistance, migrations
- **PostgreSQL** — base de données
- **Lexik JWT** ou **API Platform + JWT** — authentification stateless (adaptée à des clients multiples)
- **PHPUnit** — tests
- **PHP-CS-Fixer / PHPStan** — qualité de code

### Frontend (`appart-front`)
- **Vue 3** (Composition API)
- **Vite** — build et dev server (HMR)
- **Vue Router** — navigation SPA
- **Pinia** — gestion d'état
- **TypeScript** (recommandé pour la robustesse et le signal portfolio)
- **Vitest** — tests unitaires

### Infrastructure
- **Docker / Docker Compose** — conteneurisation des deux apps (portabilité : le déploiement ne dépend pas d'un hébergeur précis)
- **FrankenPHP** ou **Nginx + PHP-FPM** — serveur applicatif backend
- **GitHub Actions** — CI/CD
- **VPS Infomaniak** — hébergement de production (interchangeable grâce à Docker)

---

## Domaine fonctionnel

### Réservations / occupation
- Calendrier d'occupation du logement
- Création / modification / annulation de périodes d'occupation
- Vue d'ensemble des disponibilités

### Inventaire
- Catalogue des biens présents dans le logement (catégorisés)
- Suivi des quantités et des états
- Liste de courses / consommables récurrents
- Notes liées au lieu (état des lieux, choses à signaler entre occupants)

> C'est l'inventaire spécifique au lieu qui constitue la valeur de l'application : un simple calendrier partagé (CalDAV/Nextcloud) couvrirait la seule partie réservation, mais pas le suivi d'inventaire contextualisé.

---

## Démarrage rapide (développement)

> Détails complets dans la `ROADMAP.md`. Aperçu :

```bash
# Backend
git clone <url-appart-api>
cd appart-api
docker compose up -d
composer install
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load   # données fictives

# Frontend
git clone <url-appart-front>
cd appart-front
npm install
npm run dev
```

---

## Perspectives futures

- **Client mobile** — technologie à arbitrer le moment venu (candidats : Symfony UX Native / Hotwire Native, React Native, Flutter, ou PWA). Le choix dépendra du besoin réel de "natif" vs simple accès mobile. La même API Symfony sera consommée sans changement.
- **Notifications** — rappels (ex. arrivée prochaine, courses à prévoir) via Symfony Messenger + workers.
- **Multi-logements** — généralisation du modèle si d'autres biens s'ajoutent.
- **Partage / invitations** — gestion fine des accès si le cercle d'utilisateurs s'élargit.

---

## Statut

Projet en cours de développement. Ce README évoluera avec le projet.
