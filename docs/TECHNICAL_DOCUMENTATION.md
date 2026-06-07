# Syndicati Web - Technical Documentation

## 1. Objective

Syndicati Web is a Symfony platform for managing residential communities. It centralizes residence data, apartment availability, events, participations, complaints, forum discussions, messaging, notifications, user onboarding, and secure account access.

The web app is designed to work with the Java desktop app through the same business domain and shared service contracts, especially for users, residences, events, forum content, media assets, and authentication features.

## 2. Technology Stack

| Layer | Technology |
| --- | --- |
| Backend | PHP 8.2+, Symfony 6.4 |
| MVC / Views | Symfony Controllers, Twig templates |
| Database | MySQL / MariaDB through Doctrine ORM |
| Frontend | Twig, CSS, JavaScript, Symfony Asset Mapper |
| Security | Symfony Security, WebAuthn, TOTP, OTP, Face ID |
| Media | ImageKit, local fallback uploads |
| AI | Groq / Gemini through configured providers |
| Communication | Symfony Mailer, Notifier, Twilio-compatible channels |
| Configuration | `.env` bootstrap plus Infisical secrets |

## 3. Architecture

The project follows Symfony MVC:

- Controllers receive HTTP requests, validate access, call services/repositories, and return Twig pages or JSON.
- Entities model the core business data and are persisted by Doctrine.
- Repositories provide database queries and domain-specific lookups.
- Services encapsulate media upload, notifications, AI, page status, authentication helpers, and shared logic.
- Twig templates render the user-facing web pages.
- Public JavaScript handles interactive UI, AJAX requests, voice input, live updates, and modals.

## 4. Main Modules

| Module | Purpose |
| --- | --- |
| Home | Landing experience, dynamic residence/event/forum highlights |
| Users | Signup, login, profile, onboarding, avatar upload, friends |
| Settings | Theme, accent, accessibility preferences, UI scale |
| Residences | Residences, apartments, reviews, admin management, PDFs |
| Events | Events, participation, admin CRUD, weather preview |
| Syndicat | Syndic details, complaints, responses, PDF export |
| Forum | Publications, comments, reactions, bookmarks, reports |
| Messaging | Conversations, direct/group messages, participants |
| Notifications | Notification list, read status, unread counters |
| Security | OTP, TOTP, WebAuthn/passkeys, biometrics, Face ID |
| AI Agent | Chat, navigation commands, contextual assistance |
| Admin | Dashboards, users, page status, maintenance tools |

## 5. Runtime Configuration

The project keeps only bootstrap configuration in `.env`. Real service keys and database secrets are loaded from Infisical when `INFISICAL_BOOTSTRAP=1`.

Required runtime categories:

- Symfony: `APP_ENV`, `APP_SECRET`
- Database: `DATABASE_URL`
- Media: `IMAGEKIT_PUBLIC_KEY`, `IMAGEKIT_PRIVATE_KEY`, `IMAGEKIT_URL_ENDPOINT`
- AI: `GROQ_API_KEY` and/or `GEMINI_API_KEY`
- Auth: Google OAuth, GitHub OAuth, WebAuthn configuration
- Communication: mailer DSN, SMS/notification credentials

## 6. Security Design

Syndicati uses layered authentication:

- Email/password login with session-based Symfony security.
- OTP login through email or phone where configured.
- TOTP authenticator app setup with QR code generation.
- WebAuthn/passkey registration and login.
- Face ID enrollment and authentication.
- Role-aware access for residents, syndics, and admins.

Sensitive operations are protected by role checks, session state, CSRF protection where applicable, and server-side ownership checks.

## 7. Media Handling

Images for events, residences, apartments, profiles, and forum publications are uploaded to ImageKit when enabled. The application stores the resulting URL or path in the database so both the web app and Java app can display the same media.

If ImageKit is disabled or unavailable, local upload folders may be used as a fallback during development.

## 8. AI and Automation

The web app includes AI assistance for:

- User chat and guidance.
- Navigation commands.
- Contextual answers based on configured services.
- Optional voice interaction and accessibility support.

AI providers are selected through environment variables and should fail gracefully when no provider key is configured.

## 9. Installation

```bash
composer install
symfony serve
```

Alternative local server:

```bash
php -S localhost:8000 -t public/
```

If frontend dependencies are required:

```bash
npm install
```

## 10. Operational Notes

- Do not commit `vendor/`, `node_modules/`, logs, cache, or generated uploads.
- Keep real production secrets out of documentation and examples.
- Use Infisical for shared test configuration.
- Run migrations before testing features that depend on new columns or tables.
- Clear Symfony cache after changing environment or service configuration.
