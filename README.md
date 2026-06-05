# Syndicati Web

Symfony/Twig web application for the Syndicati residential community platform. It shares the same MySQL domain model as the JavaFX desktop app and provides the browser experience for residents, syndics, and admins.

## Stack

- PHP 8+, Symfony, Doctrine ORM, Twig
- Importmap/vanilla JavaScript, Bootstrap-compatible UI helpers
- MySQL/MariaDB
- ImageKit-backed media storage with local fallbacks
- Gmail SMTP/OAuth mail, Twilio SMS, Discord webhook announcements
- Direct AI services for assistant, forum feeling analysis, recommendations, and admin tooling

## Modules

- Frontend: landing, profile, forum, residence, syndicat/reclamation, events, messaging, notifications, settings
- Admin: dashboard, users, bans, forum moderation, residence/appartement/maintenance, events, syndicat/reclamation
- Security: password login, Google OAuth, WebAuthn, Face ID bridge, 2FA, activity logs
- Realtime-ish sync: lightweight polling/refresh endpoints for cross-app changes between web and JavaFX

## Setup

```bash
composer install
npm install
php bin/console doctrine:migrations:migrate
php -S 127.0.0.1:8000 -t public
```

Configuration is managed with Doppler for the production/demo setup. The repository only includes `.env.example` as a template; real credentials must stay out of GitHub.

Local setup with a manual env file:

```bash
cp .env.example .env.local
# Fill .env.local with your own private credentials
php bin/console cache:clear
```

Production/demo setup with Doppler:

```bash
doppler setup --project syndicati --config prd
doppler run -- php bin/console about
doppler run -- symfony server:start
```

On Windows, if `doppler` is not in PATH after installing it with Winget, reopen the terminal or use:

```powershell
& "$env:LOCALAPPDATA\Microsoft\WinGet\Links\doppler.exe" run -- php bin\console about
```

Keep secrets local or in Doppler/GitHub Actions secrets. Never commit `.env.local`, `.env.dev`, API keys, database passwords, or private service tokens.

## Useful Commands

```bash
php bin/console lint:container
php bin/console lint:twig templates
php bin/console doctrine:schema:validate
php bin/phpunit
```

## Notes

- Media fields may contain ImageKit URLs or legacy filenames; always render through `media_url()` / `ImagePathResolver`.
- Forum `Jeux Video` publications trigger Discord announcements on create/update.
- Messaging uses `conversation`, `conversation_participant`, `message`, and `message_attachment`; JavaFX reads the same tables.
- Use `APP_ENV=prod` and `APP_DEBUG=0` for realistic browsing performance checks.

## Project Shape

```text
src/Controller/        HTTP controllers and API endpoints
src/Entity/            Doctrine entities shared with Java database conventions
src/Repository/        Query helpers
src/Service/           Business logic, media, notifications, AI, messaging helpers
templates/             Twig UI for frontend and admin
public/frontend/js/    Browser-side modules
public/*_images/       Legacy local upload folders
```

Syndicati is designed as one product across two clients: this web app for browser access and the JavaFX app for the desktop experience.
