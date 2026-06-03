# Design — Les Marmottes

Système de design de l'application **Les Marmottes** (gestion d'appartement familial, Villard-de-Lans).
Ce document est la **source de vérité visuelle** : tokens, typographie, atomes, modules. Il alimentera le futur front Vue 3 (`appart-front`).

> 🗂 **Référence brute** : le prototype HTML/CSS/React livré par Claude Design est conservé tel quel dans [`design/prototype/`](design/prototype/). Ouvrir [`Les Mélèzes.html`](design/prototype/Les%20M%C3%A9l%C3%A8zes.html) dans un navigateur pour explorer.
> Captures d'écran : [`design/prototype/screenshots/`](design/prototype/screenshots/).
>
> Quand un détail manque ici, **lire la source** : `design/prototype/styles.css` (tokens + shell + atomes) et `design/prototype/app.css` (modules).

---

## 1. Direction

**Ambiance** : montagne / forêt du Vercors. Crème chaud comme papier, verts sapin profonds, accents bois et sauge.
**Ton éditorial** : titres **serif** (Spectral), texte **sans** (Hanken Grotesk), métadonnées **mono** (IBM Plex Mono — clin d'œil self-hosted).
**Format** : desktop d'abord, shell à sidebar fixe, contenu max **1180 px** centré.

---

## 2. Typographie

```html
<!-- À charger dans <head> -->
<link rel="preconnect" href="https://fonts.googleapis.com" />
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
<link href="https://fonts.googleapis.com/css2?family=Spectral:ital,wght@0,400;0,500;0,600;1,400&family=Hanken+Grotesk:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet" />
```

| Rôle | Famille | Usages |
| --- | --- | --- |
| `--serif` | **Spectral** (400, 500, 600, 400 italic) | `h1`–`h4`, gros chiffres (stat, jauge, date liste), titres de modules |
| `--sans` | **Hanken Grotesk** (400, 500, 600, 700) | Corps, navigation, boutons, libellés de formulaire |
| `--mono` | **IBM Plex Mono** (400, 500) | Eyebrows, badges, dates ISO, métriques, host-pill |

**Règle de titre** : `font-family: var(--serif); font-weight: 500; letter-spacing: -.01em; color: var(--ink);`.
**Eyebrow** (sur-titre) : `font-family: var(--mono); font-size: 10.5px; letter-spacing: .14em; text-transform: uppercase; color: var(--ink-3); font-weight: 500;`.

### Échelle (densité « regular »)

| Élément | Taille | Poids |
| --- | --- | --- |
| Titre topbar (`h1`) | 23 px | 500 (serif) |
| Section (`h2`) | 18 px | 500 (serif) |
| Catégorie (`h3`) | 16 px | 500 (serif) |
| Corps | **15 px** (`--fs`) | 400 |
| Bouton | 13.5 px | 600 |
| Chip / tag | 12 px / 11 px | 600 / 600 |
| Eyebrow | 10.5 px | 500 (mono) |

L'échelle se compresse en densité **compact** (corps 14 px, topbar 20 px).

---

## 3. Palette

Tous les tokens sont déclarés sur `:root` dans `design/prototype/styles.css` (à recopier tel quel).

### Surfaces (papier crème)

| Token | Hex | Usage |
| --- | --- | --- |
| `--paper` | `#F3F0E7` | Fond global de l'app |
| `--paper-2` | `#EBE6D8` | Fond légèrement plus profond |
| `--card` | `#FFFFFF` | Cartes, boutons, modal |
| `--card-2` | `#FAF8F1` | Champ de formulaire, hover bouton |
| `--card-3` | `#F6F2E8` | Segments, fond ghost-hover |

### Encre (vert sapin presque noir)

| Token | Hex | Usage |
| --- | --- | --- |
| `--ink` | `#1B271F` | Texte principal |
| `--ink-2` | `#495248` | Texte secondaire |
| `--ink-3` | `#7C837B` | Texte tertiaire (muted) |
| `--ink-4` | `#A7ABA1` | Texte désactivé, jours hors-mois |

### Lignes

| Token | Hex |
| --- | --- |
| `--line` | `#E4DECE` |
| `--line-2` | `#D7CFBA` |
| `--line-3` | `#C7BEA4` |

### Forêt (primaire)

| Token | Hex | Usage |
| --- | --- | --- |
| `--forest` | `#2E4A39` | Vert sapin, accent par défaut |
| `--forest-2` | `#3A5A45` | Variation claire |
| `--forest-deep` | `#1E362A` | Sidebar (haut du dégradé) |
| `--forest-ink` | `#16291F` | Sidebar (bas du dégradé) |

### Sauge / mousse (secondaire)

| Token | Hex |
| --- | --- |
| `--sage` | `#748E76` |
| `--sage-2` | `#8BA48C` |
| `--sage-bg` | `#E7EDE0` |
| `--sage-bg2` | `#DDE6D4` |

### Bois / terracotta (accent chaud)

| Token | Hex | Usage |
| --- | --- | --- |
| `--wood` | `#B07A4C` | Liseré actif sidebar, badge warn |
| `--wood-2` | `#C2935F` | Liseré actif sidebar (variante) |
| `--wood-deep` | `#8A5A30` | Compteur courses, texte "worn" |
| `--wood-bg` | `#EFE3D2` | Fond ligne "à racheter" |

### Couleurs foyer (occupation calendrier)

Cinq personas, chacune avec une teinte profonde + un fond doux. À utiliser pour les barres de réservation et chips de foyer.

| Persona | Couleur | Fond | Nuance |
| --- | --- | --- | --- |
| P1 | `#3A5A45` | `#E4EBDF` | sapin |
| P2 | `#B07A4C` | `#F0E5D5` | bois |
| P3 | `#5C7488` | `#E2E8ED` | ardoise |
| P4 | `#8A5B6E` | `#EEE2E7` | myrtille |
| P5 | `#7A7F4B` | `#ECEBD9` | lichen |

### États (condition / stock)

| État | Texte | Fond | Usage |
| --- | --- | --- | --- |
| `ok` | `#5C7E5F` | `#E5EDE0` | Bon état, stock OK |
| `worn` | `#B07A4C` | `#F0E5D5` | Usé, stock moyen |
| `replace` | `#B0584A` | `#F2E0DC` | À remplacer, rupture |

### Accent dynamique

L'accent est piloté par le panneau **Tweaks** (couleur au choix parmi 5). Implémenté comme tokens dérivés via `color-mix(in oklab, …)` :

```css
:root {
  --accent:      var(--forest);       /* défaut */
  --accent-2:    var(--forest-2);
  --accent-deep: var(--forest-deep);
  --accent-bg:   var(--sage-bg);
}
```

```js
// Au runtime, le tweak applique :
const accentVars = {
  "--accent": value,
  "--accent-2":    `color-mix(in oklab, ${value}, white 12%)`,
  "--accent-deep": `color-mix(in oklab, ${value}, black 24%)`,
  "--accent-bg":   `color-mix(in oklab, ${value} 13%, var(--card))`,
};
```

**Palette des 5 accents proposés** : `#2E4A39` sapin · `#2C5159` épicéa bleuté · `#97653A` bois · `#4F6076` ardoise · `#6E4B5E` myrtille.

---

## 4. Densité (échelle dynamique)

Attribut HTML `data-density` sur la racine — `compact` | _défaut_ | `comfy`. Re-déclare les variables d'espacement et de rayon.

| Token | compact | regular | comfy |
| --- | --- | --- | --- |
| `--sp` (padding contenu) | 14 px | 20 px | 30 px |
| `--gap` | 10 px | 16 px | 22 px |
| `--row-pad` (lignes inventaire/courses) | 8 px | 13 px | 18 px |
| `--cell-h` (cellule calendrier mois) | 80 px | 104 px | 128 px |
| `--radius` | 9 px | **12 px** | 14 px |
| `--radius-sm` | 6 px | 8 px | 10 px |
| `--fs` | 14 px | 15 px | 16 px |

Sidebar passe de **246 px** à **212 px** en densité compact ; topbar de 72 px à 60 px.

---

## 5. Élévation & coins

```css
--sh-1: 0 1px 2px rgba(27,39,31,.05), 0 1px 1px rgba(27,39,31,.04); /* cartes, boutons */
--sh-2: 0 2px 6px rgba(27,39,31,.06), 0 8px 24px rgba(27,39,31,.06); /* hover de carte */
--sh-3: 0 12px 40px rgba(27,39,31,.16), 0 2px 8px rgba(27,39,31,.08); /* modal */
```

Rayons : `--radius` (cartes, segments, champs) ; `--radius-sm` (boutons compacts) ; `16 px` pour la modal ; `999px` pour chips et pilules.

---

## 6. Effets globaux

- **Selection** : `::selection { background: color-mix(in oklab, var(--accent) 22%, transparent); }`
- **Grain papier** (toggleable via `.grain` sur `.app-root`) : SVG `feTurbulence` en overlay fixed, `mix-blend-mode: multiply`, opacity `.5`. Recette dans `styles.css:105`.
- **Antialiasing** : `-webkit-font-smoothing: antialiased; text-rendering: optimizeLegibility;` sur `body`.
- **Scrollbars custom** : épaisseur 11 px, thumb `--line-2` avec border `--paper` pour effet "inset".

---

## 7. Layout — shell desktop

```
┌─ sidebar ──┬─────────── main ───────────────┐
│  246 px    │  topbar 72 px (sticky)        │
│  fixe      │ ──────────────────────────────│
│  forest    │  content : padding var(--sp),  │
│  gradient  │  inner max 1180 px centré       │
└────────────┴────────────────────────────────┘
```

- `.app-root` : `display: flex; height: 100vh; overflow: hidden;`
- `.sidebar` : largeur fixe, fond `linear-gradient(180deg, var(--forest-deep), var(--forest-ink))`, texte `#E8EFE6`, séparateur droit subtil via `::after`.
- `.main` : flex column ; `.topbar` 72 px (60 px compact) avec `backdrop-filter: blur(8px)` ; `.content` scrollable, `padding: var(--sp)`, contenu centré sur `1180 px`.
- **Breakpoint module** : `@media (max-width: 880px)` → grilles modules passent en 1 col (cf. `app.css:221`).

### Sidebar — détails

- Brand : glyph 34×34 (rond 9 px, fond `rgba(255,255,255,.06)`), nom **serif 19 px**, sous-titre **mono 9.5 px / .14em uppercase / 50% opacity**.
- Nav-item : `padding: 9px 11px; border-radius: 9px; font-size: 14px; font-weight: 500;` ; couleur `rgba(232,239,230,.72)` → blanc au hover.
- État actif : fond `rgba(255,255,255,.10)` **et** liseré gauche 3 px bois `--wood-2` via `::before`.
- Badge nav : pilule mono 10 px ; variante `.warn` sur fond `--wood`.
- Foot : **host-pill** "auto-hébergé" — point vert pulsant `#6FCF84` avec halo, texte mono 10 px.

---

## 8. Atomes

> Tous présents dans `design/prototype/styles.css` lignes 215+. À reproduire **à l'identique** en Vue (composants ou utilitaires Tailwind/Sass).

### Bouton — `.btn`

```
height: 38px; padding: 0 15px; border-radius: 10px;
border: 1px solid var(--line-2); background: var(--card);
font-size: 13.5px; font-weight: 600; box-shadow: var(--sh-1);
```

- `:hover` → `background: var(--card-2); border-color: var(--line-3);`
- `:active` → `transform: translateY(1px);`
- **Variantes** :
  - `.primary` — fond `--accent`, texte blanc, halo `0 2px 8px color-mix(in oklab, var(--accent) 30%, transparent)`.
  - `.ghost` — transparent, hover sur `--card-3`, pas d'ombre.
  - `.sm` — `height: 32px; padding: 0 11px; font-size: 12.5px; border-radius: 8px;`
  - `.icon` — carré (38 ou 32 en `.sm`), `justify-content: center; padding: 0;`
- SVG interne : 16 × 16 (14 dans `.sm`).

### Chip — `.chip`

Pilule 27 px haut, `padding: 0 10px`, `border-radius: 999px`, font 12 px / 600. État sélectionné `.on` = fond `--accent`, texte blanc. Point coloré optionnel `.dot` 8 px.

### Segmented control — `.seg`

Conteneur arrondi `9 px` sur fond `--card-3`, padding 3 px ; chaque bouton 5×13 px, font 12.5 px / 600. État actif `.on` : fond `--card`, ombre `--sh-1`. Sert pour vue calendrier (mois/semaine/liste).

### Carte — `.card`

`background: var(--card); border: 1px solid var(--line); border-radius: var(--radius); box-shadow: var(--sh-1);`. Base de toutes les listes (inventaire, courses, architecture).

### Tag d'état — `.tag`

22 px haut, `padding: 0 8px`, `border-radius: 6 px`, font 11 px / 600.
- `.tag.ok` — fond `--ok-bg`, texte `#3c573f`
- `.tag.worn` — fond `--worn-bg`, texte `#855526`
- `.tag.replace` — fond `--replace-bg`, texte `#8c3a2e`
- Pastille `.dot` 6 px facultative.

### Avatar

Cercle 26 px, `font-size: 11px; font-weight: 700; color: #fff; letter-spacing: -.02em;`. Le fond reçoit la couleur de la persona (`--p1` à `--p5`).

### Champs

- **Searchbox** : conteneur `38 px / border-radius 10 px / border --line-2 / box-shadow --sh-1`, largeur fixe 280 px, padding `0 13 px`, icône loupe + input transparent 13.5 px.
- **Field** (`.fld`) : `border: 1px solid var(--line-2); border-radius: 9px; padding: 9px 11px; font-size: 14px; background: var(--card-2);` — au focus : `border-color: var(--accent); background: var(--card);`.
- **Label** (`.fld-label`) : 12 px / 700, `color: var(--ink-2)`, margin `10px 0 6px`.
- **Stepper** : groupe carré 30 px sur `--card-3`, deux boutons + valeur centrale 700 / 14 px ; variante `.sm` = 26 px / 13 px.

### Now-pill (badge "actuellement")

`font-family: var(--mono); font-size: 9.5px; text-transform: uppercase; letter-spacing: .06em; background: var(--sage-bg); color: #3c573f; padding: 2px 7px; border-radius: 999px;`

---

## 9. Modal

- **Scrim** : `position: fixed; inset: 0; background: rgba(22,34,26,.42); backdrop-filter: blur(3px); display: grid; place-items: center; z-index: 1000; padding: 24px;`
- **Carte** : largeur 560 px, max-height 90vh, fond `--card`, `border-radius: 16 px`, ombre `--sh-3`.
- **Anim ouverture** : `opacity 0→1` + `translateY(12px) scale(.98) → none` sur 240 ms (`cubic-bezier(.22,.61,.36,1)`).
- **Structure** : `.modal-head` (22 px de padding haut) — `.modal-body` (gap 8 px, padding `8px 22px 18px`) — `.modal-foot` (border-top `--line`, actions alignées à droite, `padding: 16px 22px`).
- Encadrés feedback : `.modal-summary` (fond `--sage-bg`, succès), `.modal-err` (fond `--replace-bg`).

---

## 10. Modules

### Planning — calendrier mois / semaine / liste

**Mois** (`.cal-month`)
- En-tête `.cal-dow` : 7 colonnes, libellé mono 10.5 px uppercase ; le week-end (`nth-child(n+6)`) coloré en `--wood-deep`.
- Cellule (`.cal-cell`) : `padding: 7px 9px; min-height: var(--cell-h);`, hover `--card-3`, cellules hors-mois grisées.
- Jour courant : `--accent` plein rond 24 px (cf. `.cal-cell.today .cal-daynum`).
- Barres d'occupation (`.resbar`) : positionnées absolument sur la semaine, 19 px de haut, `border-radius: 6 px`, texte 11.5 px / 700, fond = couleur persona.

**Semaine** (`.cal-weekview`)
- En-tête sticky : jour de la semaine (mono) + date (serif 19 px). Colonne `.today` mise en avant (couleur `--accent`).
- Barres (`.wk-bar`) : 52 px de haut, `border-radius: 9 px`, deux lignes (nom + note), ombre `--sh-1` → `--sh-2` au hover.

**Liste** (`.cal-list`)
- Groupes par mois avec séparateur (titre serif 14 px + ligne).
- Ligne (`.list-row`) : 12×16 px, date à gauche (D serif 21 px + mois mono 10 px), nom (14.5 px / 700), note (12.5 px tertiaire), métadonnées droite. `.past` → `opacity: .55`.
- Indicateur "actuel" : `.now-pill` (cf. atomes).

**Bandeau statut** (`.occ-status` + `.status-card`) : cartes 11×16 px, point d'état 9 px, font 14 px. Variante `.subtle` sans ombre sur `--card-3`.

### Inventaire

- **Synthèse** (`.inv-summary`) : grille 4 colonnes ; chaque `.sumcard` montre une clé (`.sum-k` 10 px), une grosse valeur serif 30 px et un sous-texte ; variante `.hot` (fond `--replace-bg`) pour les articles à remplacer.
- **Catégorie** (`.inv-cat`) : header avec icône en `--accent`, titre serif 16 px, puis carte avec lignes (`.inv-row`) en grille `1fr 160px 130px auto`. Tag d'état cliquable (cycle Bon → Usé → À remplacer).
- **Empty state** (`.empty`) : 60 px de padding, texte tertiaire.

### Courses

- **Layout** (`.crs-layout`) : grille `1fr 320px` — tableau à gauche, liste de courses sticky à droite. Repasse en 1 col sous 880 px.
- **Ligne** (`.crs-row`) : grille `1fr 220px 110px`, padding `var(--row-pad) 16px`. Variante `.need` (fond bois doux) pour les articles à racheter.
- **Tag catégorie** (`.crs-cat`) : pastille 10.5 px / 700 uppercase, `color: var(--accent); background: var(--accent-bg);`.
- **Jauge** (`.gauge`) : barre 7 px arrondie, remplissage `.ok` / `.mid` / `.low` selon le stock, **trait vertical du seuil** 2 px en `--ink-3`.
- **Liste de courses** (`.crs-list-card`) : compteur serif 26 px en `--wood-deep`, checkboxes custom (`.crs-check-box` 19 px, fond `--sage` quand coché), ligne traversée + dimmed sur coché.

### Architecture (page portfolio)

- Largeur max 920 px ; **lede** 17 px / line-height 1.65 / `--ink-2`, mots-clés en gras `--ink`.
- **Diagramme** (`.arc-diagram`) : enclosure pointillée (`border: 1.5px dashed var(--line-3)`) représentant le VPS auto-hébergé ; nœuds (`.arc-node`) ronds 11 px, `min-width: 120px`. Connecteurs (`.arc-flow .arc-line`) = pointillés générés par `repeating-linear-gradient`.
- **Badge** (`.arc-badge`) : pilule sauge 11 px / 600, fond `--sage-bg`.
- **Principes** (`.arc-principles`) : grille 3 colonnes (1 col sous 880 px) ; cartes `padding: 20px`, icône 40×40 sur fond `--accent-bg` couleur `--accent`.
- **Stack** (`.arc-stack-row`) : grille `180px 1fr`, clé 13.5 px / 700, valeur 12.5 px / `--ink-2`.

---

## 11. Iconographie

Système d'icônes maison, **stroke 1.6 / viewBox 24 / linecap+linejoin round** — défini dans `design/prototype/icons.jsx`. Convention :

```jsx
const _P = { fill: "none", stroke: "currentColor", strokeWidth: 1.6, strokeLinecap: "round", strokeLinejoin: "round" };
```

Inventaire (extraits) : `calendar`, `box`, `cart`, `server`, `leaf`, `plus`, `chevL/R/D`, `search`, `check`, `x`, `edit`, `trash`, `grid`, `cols`, `list`, `user`, `users`, `alert`, `refresh`, `moon`, `pin`, `clock`, `linen`, `dish`, `gear`, `bath`, `download`, `lock`, `arrow`, `sparkle`, `sun`, `bed`.

Tailles courantes : **18 px** (nav), **16 px** (boutons), **14 px** (segmented control).

---

## 12. Motion

- **Transition baseline** : `.13s`–`.14s` pour hover/focus sur boutons, chips, champs, lignes.
- **Press feedback** : `transform: translateY(1px)` au `:active`.
- **Vue switch** : keyframe `viewIn` — `opacity 0→1` + `translateY(6px → 0)` sur 260 ms (`cubic-bezier(.22,.61,.36,1)`). Désactivé via `@media (prefers-reduced-motion: reduce)`.
- **Modal** : scrim 180 ms (fade) + carte 240 ms (slide-up + scale 0.98 → 1).

---

## 13. Tweaks (panneau de paramétrage)

Défauts définis dans `app.jsx` :

```js
const TWEAK_DEFAULTS = {
  density: "regular",       // compact | regular | comfy
  calView: "month",         // month | week | list
  accent:  "#2E4A39",       // l'un des 5 ACCENTS
  grain:   true             // overlay papier on/off
};
```

À porter en Vue : exposer ces choix dans un store Pinia ; `density` et `accent` se câblent par mutation de variables CSS sur `<html>` ou `.app-root` ; `calView` est juste un état de routing.

---

## 14. Reprise par le futur front Vue 3

Quand `appart-front` démarrera :

1. **Copier intégralement** `design/prototype/styles.css` puis `design/prototype/app.css` dans `src/assets/css/` (ou injecter dans le bootstrap Vite) — ce sont des tokens + composants nus, agnostiques de React.
2. **Charger les 3 polices Google** (cf. § 2) dans `index.html`.
3. **Recréer les composants** un à un en Vue 3 SFC en mappant 1:1 sur les classes existantes (`.btn`, `.chip`, `.card`, `.modal`, `.cal-month`, etc.). Les JSX du prototype servent de modèle structurel — ne pas porter la mécanique React, juste l'arbre DOM et l'état.
4. **État global** : un store `tweaks` (Pinia) + composables pour la persistance `localStorage` (clé `marmotte.route` déjà utilisée).
5. **Données mockées** disponibles dans `design/prototype/data.jsx` (foyers, réservations juin 2026, inventaire, courses) — utiles pour les premières maquettes avant câblage API.

---

## 15. Glossaire des fichiers prototype

| Fichier | Contenu |
| --- | --- |
| `Les Mélèzes.html` | Document hôte (charge polices, CSS, scripts Babel + React) |
| `styles.css` | Tokens (palette/typo/densité/elev.) + shell + atomes |
| `app.css` | Styles des modules (calendrier, modal, inventaire, courses, architecture) |
| `icons.jsx` | Bibliothèque d'icônes linéaires |
| `shell.jsx` | Sidebar, Topbar |
| `app.jsx` | Routage local, défauts Tweaks, application de l'accent dynamique |
| `tweaks-panel.jsx` | Panneau de réglages flottant |
| `planning.jsx`, `planning-views.jsx`, `dates.jsx` | Module Planning + modal de réservation |
| `inventaire.jsx` | Module Inventaire (synthèse, catégories, lignes) |
| `courses.jsx` | Module Courses (jauges + liste générée) |
| `architecture.jsx` | Page portfolio (diagramme, principes, stack) |
| `data.jsx` | Données fictives (juin 2026) |
| `HANDOFF.md` | README de la livraison Claude Design |
| `CHAT.md` | Transcript de la conversation de cadrage |
