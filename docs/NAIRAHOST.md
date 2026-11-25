# Deploying Becca’s Table on NairaHost

NairaHost advertises PHP 8.3, Composer and Artisan CLI on its Laravel hosting. Confirm the selected plan offers the PHP version and extensions below, custom domain document roots, and a way to run scheduled PHP commands. Check both the cPanel domain runtime and CLI with `php -v`. [NairaHost Laravel hosting](https://nairahost.com.ng/laravel-hosting) · [NairaHost Laravel deployment guide](https://nairahost.com.ng/knowledgebase/31/how-to-move-a-laravel-php-application-to-nairahost.html)

## Prepare an isolated site

1. Choose the approved domain and create a dedicated cPanel site or subdomain with its own application directory. Point the domain’s document root at this application’s `public/` directory. Keep the rest of the application outside every web document root. If the account cannot assign this root, have the host configure the domain before proceeding.
2. Create a dedicated MySQL database and user with `utf8mb4`. Use the provider’s actual connection settings. The database must support InnoDB transactions and row locks. MySQL 8.4.13 was used for verification.
3. Use PHP 8.3 or newer with Ctype, cURL, DOM, Fileinfo, Filter, Hash, Mbstring, OpenSSL, PCRE, PDO, PDO MySQL, Session, Tokenizer, XML, and GD with WebP. EXIF is optional and handles camera orientation on JPEG uploads. Confirm `upload_max_filesize` is at least 6 MB, `post_max_size` is at least 8 MB, and the host can decode uploads up to 5,000 pixels per side. A PHP memory limit of 256 MB is suitable for image processing.
4. Install the domain’s TLS certificate and configure cPanel or the web server to redirect HTTP to HTTPS. Keep the server time synchronized for Paystack signatures. Keep the application code readable by PHP and grant write access to the application user for `storage/` and `bootstrap/cache/`.

## Install and configure

Upload the extracted source including `public/.htaccess` and `public/build`. If building assets from source, run `npm ci` and `npm run build` on a compatible local build machine before upload. Build artifacts are included in the source archive. From the application directory on the server:

```bash
composer install --no-dev --prefer-dist --optimize-autoloader --no-interaction
composer check-platform-reqs --no-dev
```

Copy `.env.example` to a private `.env` and set:

```dotenv
APP_ENV=production
APP_DEBUG=false
APP_URL=https://YOUR_DOMAIN
SESSION_DRIVER=database
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=true
SESSION_SAME_SITE=lax
CACHE_STORE=database
QUEUE_CONNECTION=database
RESTAURANT_ADMIN_EMAIL=
```

Add the dedicated database settings. Restrict read access to `.env` to the application user. Generate `APP_KEY` once on a new installation. Keep it stable across deployments and store an encrypted backup.

```bash
php artisan key:generate
php artisan migrate --force
php artisan storage:link
php artisan restaurant:admin
php artisan optimize
```

Generate the key and create the admin on first install only. Set the administrator email in the server's private `.env` file before running the command. The command prompts for the administrator name and password. Staff can sign in at `/login` and change their password under **Dashboard → Change password**. On later releases, back up MySQL and uploaded photos, deploy the release, apply migrations, refresh caches with `php artisan optimize`, and restart queue workers. If the host disallows public storage symlinks, Laravel can serve validated WebP variants through the same photo URLs. Confirm an uploaded photo loads from the actual domain. Verify the domain serves the application's `public/index.php`. Verify that `/up` returns success and that the gallery can show a photo uploaded through the dashboard.

## Schedule payments and notifications

Configure one scheduler cron job for this application. Replace the PHP binary and application path with the verified server paths. Use a private application log path if cron output is redirected.

```cron
* * * * * cd /absolute/path/to/beccas-table && /absolute/path/to/php artisan schedule:run
```

Prefer a supervised worker for email. Run it from the application directory.

```bash
php artisan queue:work database --sleep=3 --tries=5 --timeout=30
```

If the plan offers cron only, run a short queue worker with an exclusive lock or the host’s equivalent. An example for hosts with `flock` is below.

```cron
* * * * * cd /absolute/path/to/beccas-table && flock -n storage/queue-worker.lock /absolute/path/to/php artisan queue:work database --stop-when-empty --max-time=50 --timeout=30 --tries=5
```

Run `php artisan queue:failed` to inspect failed job IDs, and retry recoverable failures after fixing the cause. Regularly verify that scheduler and worker jobs are running. The scheduler reconciles recent incomplete Paystack payments.

## Production acceptance

- Confirm the client-owned Paystack merchant, settlement account, configured channels and HTTPS webhook endpoint. Complete the staged test-mode cases for success, abandonment and delayed webhooks. Check matching order totals, one paid status transition, a receipt, and a staff notification.
- Configure production SMTP privately. Confirm recipients receive registration, payment and reservation messages from the authorized sender and that reset links use the correct domain.
- Confirm approved menu prices, pickup instructions, delivery settings, booking capacity, imagery, genuine testimonials, privacy text and terms with the restaurant. Check the checkout and reservation forms from an Android phone and an iPhone.
- Enable the owner’s live credentials only after staging succeeds and launch is authorized. Confirm the production webhook accepts signatures for the selected mode. Rebuild configuration cache after environment changes and restart queue workers. Verify a separately authorized live transaction and confirm its settlement in the owner’s Paystack dashboard.
- Verify dated database and media backups and a documented restore process. Keep the application files, database user, logs and cron jobs separate from any other hosted websites.

[Laravel deployment requirements](https://laravel.com/docs/12.x/deployment) · [Paystack webhook documentation](https://paystack.com/docs/payments/webhooks/)




