# 🍊 Orange Chef

Petit portail de recettes autour de l’orange, avec recherche filtrée, autocomplétion, i18n et un widget d’aide intégré.

---

## 🚀 Stack & prérequis

- PHP ≥ 8.1 (Symfony 6.4+/7.x)
- Composer
- Base de données (MySQL/MariaDB/PostgreSQL)
- Extensions PHP courantes (intl, pdo_*, mbstring, etc.)
- Node **non obligatoire** (assets gérés via CDN + AssetMapper/importmap)

CDN : Bootswatch Litera + Bootstrap Icons.  
CSS local : `public/styles/theme-citrus.css`.

---

## 🧩 Installation

```bash
git clone <repo>
cd OrangeChef

composer install
cp .env .env.local      # puis ajuste DATABASE_URL

# Base + migrations
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate -n

# Démarrage
symfony serve -d        # ou: php -S 127.0.0.1:8000 -t public
# http://127.0.0.1:8000/fr/
