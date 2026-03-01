# OAuth (Gmail) integration

This folder documents the OAuth structure used for Gmail API sending. The actual code lives in:

- **Entity:** `src/Entity/OAuth/OAuth.php` – maps to the `OAUTH` table (access_token, refresh_token, user_id, expires_at, etc.).
- **Repository:** `src/Repository/OAuth/OAuthRepository.php` – find by user, by user id, by scope.
- **Controller:** `src/Controller/OAuth/OAuthController.php` – routes:
  - `GET /oauth/gmail/connect` – redirects to Google consent (requires login).
  - `GET /oauth/gmail/callback` – exchanges code and stores tokens in `OAUTH`.
  - `POST /oauth/gmail/disconnect` – removes the current user’s OAuth record.
- **Services:** `src/Service/OAuth/`
  - `GoogleOAuthService.php` – build Google client, auth URL, exchange code, refresh token, get valid OAuth for user.
  - `GmailOAuthMailer.php` – send `Symfony\Component\Mime\Email` via Gmail API using tokens from `OAUTH`.

## Setup

1. Create OAuth 2.0 credentials (Web application) in [Google Cloud Console](https://console.cloud.google.com/apis/credentials). Add redirect URI: `https://yourdomain.com/oauth/gmail/callback`.
2. In `.env` set:
   - `GOOGLE_OAUTH_CLIENT_ID=...`
   - `GOOGLE_OAUTH_CLIENT_SECRET=...`
   - `GOOGLE_OAUTH_REDIRECT_URI=https://yourdomain.com/oauth/gmail/callback`
   - `MAILER_OAUTH_USER_ID=<id_user>` of the user whose Gmail should send app mail (e.g. 2FA OTP). That user must visit “Connect Gmail” (link to `oauth_gmail_connect`) once to authorize.
3. In the profile (or settings) add a “Connect Gmail” link to `oauth_gmail_connect` so the chosen user can complete the flow. After that, `TwoFactorService::sendCode()` will use Gmail API when `GmailOAuthMailer::isAvailable()` is true.

## Flow

- User clicks “Connect Gmail” → `OAuthController::gmailConnect()` → redirect to Google → user consents → callback → `GoogleOAuthService::exchangeCodeAndStore()` → row in `OAUTH` for that user.
- When sending mail, `TwoFactorService` calls `GmailOAuthMailer::isAvailable()`. If true, it uses `GmailOAuthMailer::send()` (Gmail API); otherwise it uses the default SMTP mailer.
