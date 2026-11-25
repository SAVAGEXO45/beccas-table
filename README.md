# Becca’s Table

Laravel 12 application for the Nigerian and Continental restaurant in Lekki Phase 1. The application includes the public website, customer accounts and order tracking, server-priced carts, Paystack-hosted checkout, reservation requests, and a staff dashboard.

## Run locally

Use PHP 8.3 or newer, Composer 2, MySQL 8.0 or 8.4, and Node.js 22 or newer. Enable the PHP extensions required by [Laravel](https://laravel.com/docs/12.x/deployment#server-requirements), plus PDO MySQL, GD with WebP support, and cURL. PDO SQLite is required for the default test database.

1. Extract the archive. Open a terminal in the directory containing `artisan`.
2. Create an empty, dedicated MySQL database and a database user with permissions on that database.
3. Copy `.env.example` to `.env`. Set `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`, and `APP_URL` for your environment.
4. Install dependencies, migrate and start the application.

```bash
composer install
npm ci
npm run build
php artisan key:generate
php artisan migrate
php artisan storage:link
php artisan serve
```

Open `http://localhost:8000`. Start `php artisan queue:work` in another terminal when testing mail. Use `npm run dev` alongside the PHP server when editing frontend assets. On Windows, run `npm.cmd` if PowerShell blocks the npm script shim. The storage link lets the web server serve uploads directly. On Windows hosts where symlinks are unavailable, Laravel serves the optimized photo variants through the same public URLs.

## Import the restaurant menu

Run these after migrating the local MySQL database:

```bash
php artisan db:seed --class=RestaurantMenuSeeder
php artisan db:seed --class=RestaurantMenuPhotographySeeder
```

The first command adds the eight categories, 60 dishes and their suggested menu options. The second imports the bundled illustrative photographs. The imports preserve existing prices and attached photographs. Confirm prices in **Meals** before allowing online orders for each dish.

See the [menu implementation report](docs/MENU-IMPLEMENTATION.md) for local verification, photo management and the owner information still needed.

## Set up the staff account

```bash
php artisan restaurant:admin
```

Set `RESTAURANT_ADMIN_EMAIL` in your server's private `.env` file. The setup command uses that email when run without an argument. Enter the name and password at the interactive prompts. Sign in at `/login` and open `/admin`. From the dashboard, choose **Change password** (`/admin/password`) and enter your current password and the new password twice. The current session stays signed in and other sessions are signed out. Public registration creates customer accounts. The administrator can disable customer sign-in and review order history from **Customers**.

1. In **Categories**, create the approved food categories. Add meals and NGN prices in **Meals**, then enable availability and select any homepage features.
2. In **Photos**, upload approved photographs and enter descriptive alt text. In **Meals**, choose a main photograph and select additional photographs. The menu card shows the main image and one alternate. The meal page shows the full set. Choose the hero, About and brand photographs in **Website settings**. Add genuine testimonials with permission to publish.
3. Configure pickup instructions, service areas, fees and minimum subtotal if the restaurant offers delivery. Delivery starts disabled.
4. Confirm opening hours, the reservation duration and lead time, closure dates and guest capacity. Reservation requests hold capacity until the scheduled time passes, or until staff reject or cancel them. The initial duration is 90 minutes, minimum lead time is 60 minutes, and the window is 60 days. Leaving capacity blank allows one overlapping request at a time.
5. Publish the approved privacy policy and terms under **Website settings**. Those pages currently provide the business contact until approved copy is entered. The default FAQ is editable.

The homepage uses the restaurant logo by default. Staff can select a homepage hero image in **Website settings**. Each menu category uses the lead photograph of a dish in the current results. The public gallery shows the primary photos of menu dishes and puts photos marked for the gallery first. The application starts without sales, bookings, or customer accounts. The menu seed above adds the catalog for review.

For an isolated local workflow, `php artisan db:seed --class=LocalMenuSeeder` creates a visibly labelled test menu entry. Use a separate local database for this data.

## Payments

Set the restaurant owner’s test keys in the private environment, using `PAYSTACK_SECRET_KEY` and optionally `PAYSTACK_PUBLIC_KEY`. The public key is reserved for future client-side integration. This checkout uses server-side initialization and Paystack’s hosted page. With the secret key unset, checkout displays the restaurant’s contact link. Keep `PAYSTACK_PAYMENT_URL=https://api.paystack.co`.

In the Paystack dashboard for the matching environment, set the webhook to `https://YOUR_DOMAIN/payments/paystack/webhook`. For accounts using the timestamped `Paystack-Signature` header, set `PAYSTACK_WEBHOOK_SECRET` to the matching `whsec_` secret. During rotation it accepts comma-separated old and new secrets. Leave it empty only if the account uses the legacy `X-Paystack-Signature` header. The application supplies the callback URL directly to Paystack. A reachable HTTPS staging environment is needed for actual webhook deliveries.

The server matches the provider’s status, amount, currency and reference to the stored order. Callbacks and signed webhooks use the same verification path. The reconciliation command checks recent unfinished payments when the scheduler runs. Staff can recheck an individual payment from its order. The payment record stores the reference, verified provider ID and channel. Refunds and chargebacks are handled in the merchant’s Paystack dashboard and require staff reconciliation. Amounts in the dashboard represent verified gross payments.

Use the owner’s settlement account for both setup and launch. Enter secrets through the private server configuration. Keep live keys out of local test environments, source control and communications. Run an end-to-end check with the owner’s test credentials on staging before live activation.

[Accept payments](https://paystack.com/docs/payments/accept-payments/) · [Verify payments](https://paystack.com/docs/payments/verify-payments/) · [Webhooks](https://paystack.com/docs/payments/webhooks/)

## Email

Set `MAIL_MAILER=smtp`, `MAIL_SCHEME`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, and `MAIL_FROM_ADDRESS` to values approved by the mail provider. Use the supplied business email as the sender only if the provider authorizes it. Enable `RESTAURANT_SEND_MAIL=true` and run the queue worker. This enables registration notices, order placement, payment receipts, fulfilment status, reservation updates and password reset email. New paid order and reservation notifications also go to the configured business email.

## Verification

Run `php artisan test` with the default isolated SQLite configuration. To repeat against MySQL, set the `DB_*` variables to an empty disposable test database. **The tests refresh the configured database.** Run `npm run build` to verify frontend compilation.

See [verification results](docs/VERIFICATION.md) and the [NairaHost deployment guide](docs/NAIRAHOST.md).

## Photography

Use approved restaurant photography or appropriately licensed images. Keep required attribution on the Photo acknowledgments page.




