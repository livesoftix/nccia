# Login CAPTCHA on cPanel

The main and forensic login pages use Google's reCAPTCHA v2 checkbox, with server-side verification on all three login POST routes. CAPTCHA is opt-in until real keys are configured; `RECAPTCHA_ENABLED=false` means there is no CAPTCHA protection. Once enabled, missing configuration, missing/invalid tokens, wrong hostnames and provider failures block login. Existing password, rate-limit and MFA checks still apply. Each login attempt, including an MFA retry, needs a fresh CAPTCHA token.

1. Register a **reCAPTCHA v2 / I'm not a robot Checkbox** site at https://www.google.com/recaptcha/admin/create for `nccia.real-erp.net`. Add other actual login domains if used. Do not use v3/Enterprise-only keys with this integration.
2. Put the real key pair into the server's Laravel `.env`, then enable it:

```dotenv
RECAPTCHA_ENABLED=true
RECAPTCHA_SITE_KEY=your_public_site_key
RECAPTCHA_SECRET_KEY=your_private_secret_key
RECAPTCHA_ALLOWED_HOSTNAMES=nccia.real-erp.net,www.nccia.real-erp.net
```

The site key is fetched at runtime from `/api/auth/captcha`, so changing keys does not require a frontend rebuild. Never put the secret key into `VITE_*`, source control or chat. Register only hostnames you actually use; the server checks exact matches against Google's response, independently of the request Host header.

3. Upload `config/recaptcha.php`, `app/Http/Controllers/Auth/CaptchaController.php`, `app/Http/Middleware/VerifyRecaptcha.php`, the updated `routes/web.php`, `app/Http/Middleware/SecurityHeaders.php`, `public/.htaccess`, and the complete newly built `public/react` directory. Build with `npm --prefix react-app run build` when deploying source changes.
4. In the Laravel project folder run `php artisan config:clear` and `php artisan route:clear`. If your deployment uses cached routes, rebuild them with `php artisan route:cache`.
5. Verify both `/login` and `/forensic/login`: checkbox appears, submit stays disabled until solved, wrong password resets the widget, expired challenges cannot submit, MFA retry works, and successful login reaches the correct portal. Google may show an image or audio challenge depending on its risk assessment; the app does not force an image challenge every time.

The server needs outbound HTTPS access to `www.google.com` and browsers need access to the Google reCAPTCHA resources allowed by the updated CSP. This loads Google's third-party CAPTCHA on login pages. No application password or email is sent in the server verification request.

For local development use separate registered development keys/domains. Existing native/mobile clients also need to obtain and send a valid token when CAPTCHA is enabled; old APKs that do not send one cannot log in. This change targets the cPanel web deployment; native WebView integration needs separate validation.

Automated backend tests mock Google; live verification requires the real registered keys. References: https://developers.google.com/recaptcha/docs/display and https://developers.google.com/recaptcha/docs/verify.
