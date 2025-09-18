# Changelog — Orange Chef

Toutes les nouveautés, correctifs et changements notables.

## [0.5.1] – 2025-09-18
### Corrigé
- Erreur Twig `|trans` (retire l’argument inexistant `default`).
- Route `/{_locale}/search/suggest` : suppression d’un double `{_locale}` qui cassait la compilation des routes.
- `UserType`: suppression de l’option non supportée `allowEmptyString` sur la contrainte `Length`.
- Doublon de clé `recipes` dans `messages.en.yaml`.

### Améliorations
- Autocomplétion nav : vérifs d’init + isole le JS pour éviter les collisions de sélecteurs.
- Widget d’aide : défilement interne, focus management, léger polissage visuel.

---

## [0.5.0] – 2025-09-18
### Ajouté
- **Autocomplétion recherche (page Recettes)** :
    - Endpoint `GET /{_locale}/search/suggest` → JSON `{ items: [{label,type,url}] }`.
    - Dropdown custom (vanilla JS) sous le champ : debounce, flèches ↑/↓, Enter, Échappe, clic.
    - Clic catégorie → liste filtrée ; clic recette → fiche recette.
- **Repository** :
    - `RecipeRepository::suggestByTitle(term, limit)`
    - `RecipeRepository::suggestCategories(term, limit)`

---

## [0.4.0] – 2025-09-18
### Ajouté
- **Internationalisation (FR/EN)** :
    - Nouvelles clés : `nav.*`, `recipes.*`, `filters.*`, `pagination.*`, `empty.*`.
    - Clés pour formulaire d’avis : « Quelques mots sur la recette… », « Votre note ».
    - `trans_default_domain 'messages'` dans les templates concernés.
- Commandes d’aide : `debug:translation` pour remonter les manquants.

---

## [0.3.0] – 2025-09-18
### Ajouté
- **Widget d’aide flottant** (`_partials/help_widget.html.twig`) :
    - Panel redimensionnable avec recherche, catégories, et liens rapides.
    - Mapping client `route:xxx → URL` localisée.
    - **Scroll** dans la liste des résultats.

---

## [0.2.0] – 2025-09-18
### Refonte UI/UX page Recettes
- Nouveau **hero** (titre + tagline + puces d’exemples).
- Factorisation en **partials** :
    - `recipe_filters` : recherche, catégorie, difficulté, tri, /page, bouton **Mode compact**.
    - `recipe_card` : image avec fallbacks par catégorie/mot-clé, badges, stats, rating.
    - `pagination` : pagination accessible avec fenêtre et ellipses.
- **Mode compact** (densité) : toggle + persistance `localStorage`.

### Contrôleur
- `RecipeController::index` :
    - Filtres `q`, `category`, `difficulty`.
    - Tri `newest|oldest|title`.
    - Pagination simple (`p`, `ps`) + transmission au template.

---

## [0.1.0] – 2025-09-18
### Base
- Squelette Symfony + routes principales (home, recettes, contact).
- Thème visuel (Bootswatch Litera + `styles/theme-citrus.css`).
- En-tête/nav avec sélecteur de langue FR/EN.
