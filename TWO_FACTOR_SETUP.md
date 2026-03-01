# Two-Factor Authentication (2FA) Setup Guide

## What Was Installed

✅ **Symfony Mailer** - Already installed  
✅ **Custom 2FA Service** - Email-based OTP (One-Time Password) authentication  
✅ **User Entity Updated** - Added `twoFactorEnabled`, `totpSecret`, `authCode`, `authCode_expires_at` fields  
✅ **2FA Controllers** - Verification, enable/disable endpoints  
✅ **Email Templates** - OTP code email template  

## Next Steps

### 1. Install Dependencies (if needed)

Symfony Mailer is already installed. If you want to use scheb/2fa-bundle features later:

```bash
composer require scheb/2fa-bundle
```

**Note:** The current implementation uses a custom 2FA flow that works with your session-based authentication. scheb/2fa-bundle is optional for future TOTP (authenticator app) support.

### 2. Configure Mailer

Update `.env` with your SMTP settings:

```env
# For production (Gmail example)
MAILER_DSN=smtp://your-email@gmail.com:your-app-password@smtp.gmail.com:587

# For testing (emails won't be sent)
MAILER_DSN=null://null

# Email sender info
MAILER_FROM_EMAIL=noreply@yourdomain.com
MAILER_FROM_NAME=Horizon
```

**Gmail Setup:**
1. Enable 2-Step Verification
2. Generate an App Password: https://myaccount.google.com/apppasswords
3. Use: `smtp://your-email@gmail.com:app-password@smtp.gmail.com:587`

**Other Providers:**
- **SendGrid:** `smtp://apikey:YOUR_API_KEY@smtp.sendgrid.net:587`
- **Mailgun:** `smtp://postmaster@YOUR_DOMAIN.mailgun.org:YOUR_PASSWORD@smtp.mailgun.org:587`
- **Amazon SES:** `smtp://USERNAME:PASSWORD@email-smtp.REGION.amazonaws.com:587`

### 3. Run Database Migration

The User entity has new fields. Create and run a migration:

```bash
php bin/console make:migration
php bin/console doctrine:migrations:migrate
```

Or manually add the columns if you prefer:
```sql
ALTER TABLE user 
ADD COLUMN twoFactorEnabled TINYINT(1) DEFAULT 0 NOT NULL,
ADD COLUMN totpSecret VARCHAR(255) NULL;
```

### 4. Test the Integration

1. **Enable 2FA for a user:**
   - Sign in → Go to Profile → Enable 2FA (or visit `/2fa/enable`)

2. **Test Login Flow:**
   - Sign out
   - Sign in with email/password
   - If 2FA is enabled, you'll be redirected to `/2fa/verify`
   - Check email for 6-digit code
   - Enter code → Complete login

3. **Resend Code:**
   - On `/2fa/verify` page, click "Resend Code"

## How It Works

### Flow:
1. User signs in with email/password
2. System checks if `twoFactorEnabled = true`
3. If enabled:
   - Generate 6-digit OTP code
   - Store in `authCode` (expires in 15 minutes)
   - Send email with code
   - Redirect to `/2fa/verify`
   - User enters code
   - Verify code → Complete login
4. If disabled: Normal login proceeds

### Database Fields:
- `authCode` (varchar 50, nullable) - Stores the OTP code
- `authCode_expires_at` (datetime, nullable) - Code expiration time
- `twoFactorEnabled` (boolean) - Whether 2FA is enabled for this user
- `totpSecret` (varchar 255, nullable) - Reserved for future TOTP (authenticator app) support

### Files Created:
- `src/Service/TwoFactor/TwoFactorService.php` - Core 2FA logic
- `src/Service/TwoFactor/EmailAuthCodeMailer.php` - Email sender (for scheb/2fa-bundle compatibility)
- `src/Controller/TwoFactor/TwoFactorController.php` - 2FA routes
- `templates/security/two_factor_login.html.twig` - Verification page
- `templates/emails/two_factor.html.twig` - OTP email template
- `templates/security/two_factor_enable.html.twig` - Enable 2FA page
- `templates/security/two_factor_disable.html.twig` - Disable 2FA page

## Routes

- `/2fa/verify` - Verify OTP code (GET/POST)
- `/2fa/resend` - Resend OTP code (POST)
- `/2fa/enable` - Enable 2FA (GET/POST)
- `/2fa/disable` - Disable 2FA (GET/POST)

## Future Enhancements

- **TOTP Support:** Add authenticator app (Google Authenticator, Authy) support using `totpSecret`
- **Trusted Devices:** Remember devices for 30 days
- **Backup Codes:** Generate recovery codes
- **SMS 2FA:** Add SMS provider support

## Troubleshooting

**Emails not sending:**
- Check `MAILER_DSN` in `.env`
- For localhost testing, use `MAILER_DSN=null://null` (emails logged to console)
- Check Symfony profiler → Mailer section to see sent emails

**Code expired:**
- Codes expire after 15 minutes
- Click "Resend Code" to get a new one

**2FA not triggering:**
- Ensure `twoFactorEnabled = true` in database
- Check User entity has `isTwoFactorEnabled()` returning true
