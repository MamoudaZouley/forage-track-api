# ForageTrack API

API REST Laravel 13 pour le suivi des puits d'eau et supervisions de terrain.

## Technologies

- Laravel 13
- PHP 8.5
- MySQL
- Laravel Sanctum (authentification)

## Installation

```bash
git clone https://github.com/MamoudaZouley/forage-track-api.git
cd forage-track-api
composer install
cp .env.example .env
php artisan key:generate
```

Configurer `.env` avec vos credentials MySQL, puis :

```bash
php artisan migrate --seed
php artisan serve
```

## Comptes de test

| Email | Mot de passe | Rôle |
|---|---|---|
| admin@forage.ne | password123 | Admin |
| ibrahim@forage.ne | password123 | Utilisateur |

## Endpoints principaux

| Méthode | URL | Description |
|---|---|---|
| POST | /api/login | Connexion |
| GET | /api/dashboard | Tableau de bord |
| GET | /api/wells | Liste des puits |
| GET | /api/wells/{id} | Fiche d'un puits |
| GET | /api/alerts | Liste des alertes |
| PATCH | /api/alerts/{id}/resolve | Résoudre une alerte |