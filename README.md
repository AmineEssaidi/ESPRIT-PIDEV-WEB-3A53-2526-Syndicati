# Syndicati

## Description

Syndicati est une plateforme web Symfony de gestion residentielle pour les residences, appartements, syndics et communautes de voisinage. Elle centralise les services essentiels d'un complexe moderne : authentification securisee, gestion des residents, residences, appartements, evenements, reclamations, forum communautaire, messagerie, notifications, preferences de paiement, medias et administration.

Le projet repond a un probleme frequent dans les residences : les informations sont souvent dispersees entre appels, messages, papiers, preferences manuelles et reclamations difficiles a suivre. Syndicati regroupe ces flux dans une interface connectee, plus claire, plus securisee et plus accessible pour les residents, les syndics et les administrateurs.

Le projet web partage la meme logique metier et la meme base de donnees que l'application desktop JavaFX Syndicati.

## Technologies utilisees

Frontend :
- Twig
- HTML, CSS, JavaScript
- Symfony Asset Mapper / Importmap
- UX Turbo / Stimulus

Backend :
- PHP 8.2+
- Symfony 6.4
- Doctrine ORM / Doctrine Migrations
- Symfony Security
- Symfony Mailer / Notifier / Messenger
- Symfony Validator / Serializer / Translation

Base de donnees :
- MySQL ou MariaDB

Services et integrations :
- Infisical pour charger les variables d'environnement
- ImageKit pour les images et medias
- Google OAuth et Gmail OAuth
- WebAuthn / Passkeys
- TOTP 2FA et OTP email/SMS
- Twilio pour SMS / Verify
- hCaptcha
- LiveKit pour les salles video
- Discord Webhook pour le forum
- Gotenberg / DomPDF pour les exports PDF
- Gemini, Groq, Mistral et Ollama pour les fonctionnalites IA
- Google Custom Search pour la recherche web IA

## Prerequis

- PHP 8.2 ou plus
- Composer
- Symfony CLI
- MySQL ou MariaDB
- Node.js 18 ou plus
- Git
- Une installation PHP locale avec les extensions recommandees

Extensions PHP recommandees :
- `ctype`
- `iconv`
- `pdo_mysql`
- `intl`
- `openssl`
- `curl`
- `mbstring`
- `fileinfo`
- `gd`

## Installation

Cloner le depot :

```bash
git clone https://github.com/AmineEssaidi/ESPRIT-PI-3A53-2526-SYNDICATI-WEB.git
cd ESPRIT-PI-3A53-2526-Syndicati-WEB
```

Installer les dependances PHP :

```bash
composer install
```

Sous Windows, si PHP ou OpenSSL ne sont pas encore prets, lancer d'abord :

```bat
install-web-deps.bat
```

Ce script installe d'abord WinGet/App Installer si necessaire, puis installe PHP et Composer via WinGet. Si Windows bloque WinGet, il utilise automatiquement une version portable de PHP dans `tools/php`, active OpenSSL, ajoute PHP au PATH utilisateur, telecharge Composer si besoin, puis lance `composer install`.

Installer les dependances frontend :

```bash
npm install
```

Installer les assets Symfony:

```bash
php bin/console importmap:install
php bin/console assets:install public
```

Vider le cache :

```bash
php bin/console cache:clear
```

## Lancement

Avec Symfony CLI :

```bash
symfony serve
```

Alternative sans Symfony CLI :

```bash
php -S localhost:8000 -t public/
```

Ouvrir ensuite :

```text
http://localhost:8000
```

## Variables d'environnement

La configuration de demonstration utilise Infisical. Le fichier `.env` contient uniquement le bootstrap Infisical necessaire au lancement du projet universitaire.

Les secrets reels ne doivent pas etre ecrits directement dans le README. Ils sont charges depuis Infisical au demarrage de Symfony via `config/infisical-bootstrap.php`.

Variables chargees depuis Infisical :
- `APP_ENV`
- `APP_SECRET`
- `DATABASE_URL`
- `DEFAULT_URI`
- `MAILER_DSN`
- `MAILER_FROM_EMAIL`
- `MAILER_FROM_NAME`
- `MESSENGER_TRANSPORT_DSN`
- `GOOGLE_OAUTH_CLIENT_ID`
- `GOOGLE_OAUTH_CLIENT_SECRET`
- `GOOGLE_OAUTH_REDIRECT_URI`
- `GOOGLE_SEARCH_API_KEY`
- `GOOGLE_SEARCH_CX`
- `IMAGEKIT_PRIVATE_KEY`
- `IMAGEKIT_URL_ENDPOINT`
- `IMAGEKIT_ENABLED`
- `TWILIO_DSN`
- `TWILIO_VERIFY_SERVICE_SID`
- `LIVEKIT_URL`
- `LIVEKIT_API_KEY`
- `LIVEKIT_API_SECRET`
- `HCAPTCHA_SITE_KEY`
- `HCAPTCHA_SECRET_KEY`
- `RELYING_PARTY_NAME`
- `RELYING_PARTY_ID`
- `DISCORD_FORUM_WEBHOOK_URL`
- `GEMINI_API_KEY`
- `GEMINI_MODEL`
- `GROQ_API_KEY`
- `GROQ_MODEL`
- `MISTRAL_API_KEY`

Gotenberg est configure dans `config/packages/gotenberg.yaml` avec le client HTTP `gotenberg.client`.

## Fonctionnalites principales

- Authentification email et mot de passe
- Inscription, connexion, deconnexion et recuperation de mot de passe
- Connexion Google OAuth
- Gmail OAuth pour l'envoi d'emails
- 2FA avec application d'authentification TOTP
- OTP par email et SMS
- WebAuthn / Passkeys
- Face ID et biometrie applicative
- hCaptcha sur les formulaires sensibles
- Profil utilisateur avec avatar
- Onboarding resident
- Gestion des residents et relations entre residents
- Systeme de points / standing resident
- Gestion des residences
- Gestion des appartements
- Avis et recommandations d'appartements
- Prediction de maintenance et prix
- Gestion des evenements
- Participation aux evenements et generation de tickets
- Reclamations, reponses et suivi syndic
- Forum communautaire avec publications, commentaires, reactions, favoris, signalements et moderation
- Announcements sans commentaires
- Messagerie et conversations de groupe
- Notifications en temps reel
- Tableau de bord administrateur
- Live data API pour synchronisation avec l'application Java
- Change feed `/sync/changes` pour partager les changements entre web et desktop
- Upload et affichage des medias via ImageKit
- Exports PDF et QR codes
- Video conference via LiveKit
- Assistant IA Syndicati
- Recherche web IA
- Moderation de contenu
- Accessibilite : contraste eleve, reduction des animations, taille UI, commandes confortables, saisie vocale et police dyslexie-friendly

## Pages et modules principaux

- `/` : page d'accueil
- `/sign-in` : connexion
- `/sign-up` : inscription
- `/profile` : profil utilisateur
- `/settings` : preferences et accessibilite
- `/residence` : residences et appartements
- `/evenement` : evenements
- `/forum` : forum communautaire
- `/syndicat` : reclamations et espace syndic
- `/chat` : messagerie
- `/video-conference` : reunions video
- `/admin` : tableau de bord admin
- `/admin/super-dashboard` : dashboard avance

## Structure du projet

```text
assets/                 Sources frontend gerees par Asset Mapper
bin/                    Commandes Symfony
config/                 Configuration Symfony, Doctrine, Security, Infisical et services
livekit-docker/         Configuration locale liee a LiveKit
migrations/             Migrations Doctrine
public/                 Point d'entree public, assets, images, videos, modeles Face API
src/Controller/         Controleurs HTTP, API, admin, auth, forum, residence, syndic, video
src/Entity/             Entites Doctrine
src/Repository/         Requetes et acces aux donnees
src/Service/            Logique metier, IA, medias, notifications, OAuth, Twilio, LiveKit
templates/              Interfaces Twig frontend, admin, emails et securite
translations/           Fichiers de traduction
tests/                  Tests Symfony
tools/                  Outils locaux du projet
var/                    Cache et logs locaux, non versionnes
vendor/                 Dependances Composer, non versionnees
node_modules/           Dependances Node, non versionnees
```

## Dossiers medias

Les medias publics sont organises dans `public/` :

```text
public/event_images/
public/residence_images/
public/appartement_images/
public/profile_images/
public/forum_images/
public/commentaire_images/
public/reclamation_images/
public/reponse_images/
public/messaging_attachments/
public/models/
public/videos/
```

Les nouveaux uploads sont envoyes vers ImageKit quand `IMAGEKIT_ENABLED=true`.

## Nettoyage avant publication GitHub

Ne pas versionner :

- `vendor/`
- `node_modules/`
- `var/`
- `.idea/`
- `.vscode/`
- `.phpunit.cache/`
- `public/assets/`
- `assets/vendor/`
- `dist/`
- `target/`
- fichiers temporaires
- fichiers de logs
- fichiers `.env.local`, `.env.dev`, `.env.prod` ou autres fichiers contenant des secrets reels

Le fichier `.env` du projet est conserve pour le bootstrap Infisical de demonstration universitaire.

## Commandes utiles

Installation complete :

```bash
composer install
npm install
php bin/console importmap:install
php bin/console assets:install public
php bin/console cache:clear
```

Lancement :

```bash
symfony serve
```

Ou :

```bash
php -S localhost:8000 -t public/
```

## Demo

```text
Video : https://youtu.be/XC1lMSUlNxE?si=pORqu_waDPZ8eA7W
Captures : demo/
```

## Auteurs

- Mohamed Amine Essaidi
- Mohamed Rayen Kahloun
- Mariem Ben Hamza
- Mohamed Baha Hamdi
- Syrine Negra

Tuteurs:
- Ons Fadhel
- Ameni Hajri

Classe :

- 3A53

Annee universitaire :

- 2025-2026

Projet :

- ESPRIT PI 3A - Syndicati
