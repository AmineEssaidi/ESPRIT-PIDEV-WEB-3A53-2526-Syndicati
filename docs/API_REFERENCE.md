# Syndicati Web - API Reference

This document lists the main web routes and JSON endpoints. Some routes render Twig pages, while others return JSON for interactive UI features.

## 1. Public and User Pages

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/` | Home page with dynamic residence, event, and forum highlights |
| GET | `/our-team` | Team page |
| GET | `/contact` | Contact page |
| GET/POST | `/signup`, `/sign-up` | Account creation |
| GET/POST | `/sign-in` | Login page and credential login |
| GET | `/logout` | User logout |
| GET/POST | `/profile` | Profile display and update |
| POST | `/profile/avatar-upload` | Upload profile avatar |
| GET/POST | `/settings` | User settings page |
| POST | `/settings/update` | Persist theme, accessibility, and UI preferences |

## 2. Residences and Apartments

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/residence/` | Residence listing |
| GET | `/residence/admin` | Admin residence management |
| GET/POST | `/residence/admin/ajouter` | Add residence |
| GET/POST | `/residence/admin/{id}/modifier` | Edit residence |
| POST | `/residence/admin/{id}/supprimer` | Delete residence |
| GET/POST | `/residence/{id}/appartement/new` | Add apartment |
| GET/POST | `/residence/appartement/{id}/edit` | Edit apartment |
| POST | `/residence/appartement/{id}/delete` | Delete apartment |
| GET | `/residence/{id}/details` | Residence details |
| GET | `/residence/{id}/pdf` | Residence PDF export |

## 3. Events and Participations

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/evenement/` | Event listing |
| GET | `/evenement/admin` | Admin event management |
| GET/POST | `/evenement/new` | Create event |
| GET | `/evenement/{id}` | Event details |
| GET/POST | `/evenement/{id}/edit` | Edit event |
| POST | `/evenement/{id}/delete` | Delete event and related participation data |
| GET/POST | `/participation/new/{id}` | Join an event |
| GET | `/participation/ticket/{id}` | Participation ticket |
| POST | `/evenement/weather-preview` | Weather preview for event planning |

## 4. Syndic and Complaints

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/syndicat` | Syndic list |
| GET | `/syndicat/{id}/details` | Syndic details |
| GET | `/syndicat/{id}/pdf` | Syndic PDF export |
| GET | `/syndicat/reclamation/` | Complaint list |
| GET/POST | `/syndicat/reclamation/new` | Submit complaint |
| GET | `/syndicat/reclamation/{id}` | Complaint details |
| GET/POST | `/syndicat/reclamation/{id}/edit` | Edit complaint |
| POST | `/syndicat/reclamation/{id}` | Delete complaint |
| POST | `/api/syndicat/reponse/add` | Add complaint response |
| GET | `/api/syndicat/reponse/{id}/list` | List complaint responses |
| POST | `/api/syndicat/reponse/{id}/update` | Update complaint response |
| POST | `/api/syndicat/reclamation/{id}/delete-image` | Delete complaint image |

## 5. Forum

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/forum` | Forum home |
| GET | `/forum/ajax/list` | Fetch forum publications |
| POST | `/publication/new` | Create publication |
| GET/POST | `/publication/{id}/edit` | Edit publication |
| POST | `/publication/{id}/delete` | Delete publication |
| POST | `/api/forum/publication/update/{id}` | AJAX publication update |
| GET | `/forum/comment/list/{publicationId}` | List comments |
| POST | `/forum/comment/add` | Add comment when allowed |
| POST | `/forum/comment/edit/{id}` | Edit comment |
| POST | `/forum/comment/delete/{id}` | Delete comment |
| POST | `/forum/reaction/publication/{id}/toggle` | Toggle like/dislike |
| POST | `/forum/reaction/publication/{id}/emoji` | Add emoji reaction |
| POST | `/forum/reaction/publication/{id}/report` | Report publication |
| GET | `/forum/feeling/publication/{id}` | AI feeling summary |

## 6. Messaging and Notifications

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/api/messaging/me` | Current messaging identity |
| GET | `/api/messaging/conversations` | List conversations |
| GET | `/api/messaging/messages/{id}` | Conversation messages |
| POST | `/api/messaging/send` | Send message |
| GET | `/api/messaging/friends` | Available contacts |
| POST | `/api/messaging/create-group` | Create group conversation |
| POST | `/api/messaging/manage-participant` | Add or remove participant |
| GET | `/api/notifications` | Notification list |
| POST | `/api/notifications/{id}/read` | Mark notification as read |
| POST | `/api/notifications/read-all` | Mark all notifications as read |

## 7. Security and Authentication APIs

| Method | Route | Purpose |
| --- | --- | --- |
| GET/POST | `/2fa/verify` | Verify second factor |
| POST | `/2fa/totp/generate` | Generate authenticator QR setup |
| POST | `/2fa/totp/verify` | Verify authenticator code |
| POST | `/2fa/totp/remove` | Remove authenticator setup |
| GET | `/2fa/status` | Read 2FA channel status |
| POST | `/2fa/login/totp/start` | Start TOTP login |
| POST | `/2fa/login/totp/verify` | Verify TOTP login |
| POST | `/webauthn/register/options` | Create WebAuthn registration challenge |
| POST | `/webauthn/register/verify` | Verify WebAuthn registration |
| POST | `/webauthn/login/options` | Create WebAuthn login challenge |
| POST | `/webauthn/login/verify` | Verify WebAuthn login |
| POST | `/face/enroll` | Enroll Face ID |
| POST | `/face/auth` | Authenticate with Face ID |
| GET | `/face/status` | Face ID enrollment status |
| POST | `/face/remove` | Remove Face ID |

## 8. AI and Live APIs

| Method | Route | Purpose |
| --- | --- | --- |
| POST | `/ai/chat` | AI chat response |
| GET | `/ai/bootstrap` | AI frontend bootstrap data |
| POST | `/ai/navigate` | AI navigation command |
| POST | `/syndicati/agent/execute` | Native agent execution |
| GET | `/syndicati/agent/bootstrap` | Native agent bootstrap |
| GET | `/api/live/snapshot` | Live dashboard snapshot |
| POST | `/api/log/event` | Client activity logging |
| GET | `/api/session/status` | Session health and user state |

## 9. Admin Routes

| Method | Route | Purpose |
| --- | --- | --- |
| GET | `/admin` | Admin dashboard |
| GET | `/admin/dashboard/live` | Live admin metrics |
| GET | `/admin/users` | User management |
| GET/POST | `/admin/users/{id}/edit` | Edit user |
| POST | `/admin/users/{id}/delete` | Delete user |
| POST | `/admin/users/{id}/ban` | Ban or unban user |
| GET/POST | `/admin/page-status` | Page availability controls |
| GET | `/admin/maintenance` | Maintenance tools |
