<?php

namespace Tests\Feature;

use App\Mail\RestaurantMessage;
use App\Models\{Category, Meal, Order, Payment, Reservation, Setting, User};
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Hash, Http, Mail, Storage};
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RestaurantTest extends TestCase
{
    use RefreshDatabase;

    private const TEST_SECRET = 'unit-test-signing-fixture';
    private const API = 'https://api.paystack.co/transaction/';

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('t', 32))]);
        Http::preventStrayRequests();
    }

    public function test_public_pages_display_business_information_and_safe_metadata(): void
    {
        $this->get('/')->assertOk()->assertSee("BECCA'S TABLE")->assertSee('Where Every Meal Feels Special.');
        $this->get('/about')->assertOk();
        $this->get('/menu')->assertOk()->assertSee('There are no meals available online');
        $this->get('/gallery')->assertOk();
        $this->get('/contact')->assertOk()->assertSee('12 Admiralty Way')->assertSee('tel:+2349031131536')->assertSee('google.com/maps/dir');
        $this->get('/reservations')->assertOk();
        $this->get('/faq')->assertOk();
        $this->get('/privacy')->assertOk();
        $this->get('/terms')->assertOk();
        $this->get('/sitemap.xml')->assertOk()->assertHeader('Content-Type', 'application/xml; charset=UTF-8')->assertSee('/menu');
        $this->get('/robots.txt')->assertOk()->assertSee('Sitemap:');
    }

    public function test_admin_shell_is_full_width_and_keeps_navigation_separate_from_public_pages(): void
    {
        $this->get('/')->assertOk()->assertSee('class="container nav-inner"', false)
            ->assertSee('class="site-footer"', false)->assertDontSee('staff-shell');

        $this->actingAs($this->admin());
        $this->get('/admin')->assertOk()
            ->assertSee('class="staff-shell"', false)->assertDontSee('container staff-shell')
            ->assertSee('aria-controls="staff-navigation"', false)->assertSee('id="staff-navigation"', false)
            ->assertSeeInOrder(['Operations', 'Orders', 'Catalog', 'Meals', 'Settings', 'Change password'])
            ->assertSee('View website')->assertSee('Sign out')->assertDontSee('class="site-footer"', false);
        $this->get('/admin/catalog/meals')->assertOk()
            ->assertSee('href="'.route('admin.catalog.index', 'meals').'" aria-current="page"', false);
        $this->get('/menu')->assertOk()->assertSee('class="site-header"', false)->assertDontSee('staff-shell');
    }

    public function test_customer_can_register_sign_out_sign_in_and_edit_profile(): void
    {
        Mail::fake();
        config(['restaurant.send_mail' => true]);
        $this->post('/register', [
            'name' => 'Test Customer', 'email' => 'CUSTOMER@example.test', 'phone' => '+234 900 123 4567',
            'password' => 'A long passphrase', 'password_confirmation' => 'A long passphrase', 'is_admin' => 1,
        ])->assertRedirect('/account');
        $user = User::where('email', 'customer@example.test')->firstOrFail();
        $this->assertFalse($user->is_admin);
        $this->assertTrue(Hash::check('A long passphrase', $user->password));
        $this->assertAuthenticatedAs($user);
        Mail::assertQueued(RestaurantMessage::class, fn ($mail) => $mail->hasTo($user->email));

        $this->get('/account')->assertOk()->assertSee('Test Customer')
            ->assertSee('action="'.route('logout').'"', false)
            ->assertSee('class="header-auth" type="submit"', false)
            ->assertDontSee('class="header-auth" href=', false);
        $this->get('/account/profile')->assertOk();
        $this->get('/admin')->assertForbidden();
        $this->patch('/account/profile', ['name' => 'New Name', 'email' => $user->email])
            ->assertSessionHasErrors('current_password');
        $this->patch('/account/profile', [
            'name' => 'New Name', 'email' => $user->email, 'current_password' => 'A long passphrase',
        ])->assertSessionHasNoErrors();
        $this->assertSame('New Name', $user->fresh()->name);
        $this->post('/logout')->assertRedirect('/');
        $this->assertGuest();
        $this->get('/')->assertOk()->assertSee('class="header-auth" href="'.route('login').'"', false)
            ->assertDontSee('action="'.route('logout').'"', false);
        $this->post('/login', ['email' => $user->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $user->email, 'password' => 'A long passphrase'])->assertRedirect('/account');
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_header_keeps_admin_sign_in_accessible_and_staff_sign_in_requires_an_admin(): void
    {
        $customer = User::factory()->create();
        $admin = $this->admin();

        $this->get('/')->assertOk()->assertSee('href="'.route('login').'">Login</a>', false)
            ->assertDontSee('Admin sign in');
        $this->get('/login')->assertOk()->assertSee('Customer sign in')
            ->assertSee('href="'.route('admin.login').'">Admin sign in</a>', false);
        $this->get('/admin/login')->assertOk()->assertSee('Admin sign in')
            ->assertSee('action="'.route('admin.login.submit').'"', false)
            ->assertDontSee('Create an account');

        $this->from('/admin/login')->post('/admin/login', ['email' => $customer->email, 'password' => 'password'])
            ->assertRedirect('/admin/login')->assertSessionHasErrors('email');
        $this->assertGuest();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
        $this->assertAuthenticatedAs($admin);
        $this->get('/admin')->assertOk()->assertSee('Admin dashboard')->assertSee('Sign out');
    }

    public function test_stale_browser_forms_reopen_a_safe_page_without_bypassing_csrf(): void
    {
        $this->app->bind(ValidateCsrfToken::class, function ($app) {
            return new class($app, $app['encrypter']) extends ValidateCsrfToken {
                protected function runningUnitTests()
                {
                    return false;
                }
            };
        });

        $this->get('/')->assertOk();
        $this->get('/logout')->assertRedirect('/');
        $this->post('/logout')->assertStatus(303)->assertRedirect('/');
        $this->post('/admin/login')->assertStatus(303)->assertRedirect('/admin/login');
        $this->from(url('/reservations'))->post('/reservations')->assertStatus(303)
            ->assertRedirect('/reservations')->assertSessionHas('status');
        $this->from('https://example.org/elsewhere')->post('/reservations')->assertStatus(303)->assertRedirect('/');
        $this->postJson('/logout')->assertStatus(419);

        $user = User::factory()->create();
        $this->actingAs($user)->post('/logout')->assertStatus(303)->assertRedirect('/');
        $this->assertAuthenticatedAs($user);
        $this->post('/logout', ['_token' => session()->token()])->assertRedirect('/');
        $this->assertGuest();
    }

    public function test_cart_quantities_use_current_prices_and_unavailable_items_can_be_removed(): void
    {
        $meal = $this->meal();
        $this->get('/menu?q=fixture')->assertOk()->assertSee($meal->name);
        $this->get(route('menu.category', $meal->category->slug))->assertOk()->assertSee($meal->name);
        $this->get(route('menu.meal', $meal->slug))->assertOk()->assertSee($meal->description);
        $this->post(route('cart.update', $meal), ['quantity' => 1, 'add' => 1, 'price' => 1])->assertRedirect('/cart');
        $this->post(route('cart.update', $meal), ['quantity' => 2, 'add' => 1])->assertSessionHas('cart', [$meal->id => 3]);
        $meal->update(['price' => 125500]);
        $this->get('/cart')->assertOk()->assertSee('₦3,765.00');
        $this->post(route('cart.update', $meal), ['quantity' => 31])->assertSessionHasErrors('quantity');
        $this->post(route('cart.update', 9999), ['quantity' => 1])->assertSessionHasErrors('cart');
        $meal->update(['available' => false]);
        $this->get('/cart')->assertOk()->assertSee('Unavailable.');
        $this->post(route('cart.update', $meal), ['quantity' => 0])->assertSessionHas('cart', []);
    }

    public function test_checkout_sets_server_amount_and_transitions_only_after_verified_payment(): void
    {
        config(['restaurant.paystack.secret_key' => self::TEST_SECRET, 'restaurant.send_mail' => true]);
        Mail::fake();
        $customer = User::factory()->create();
        $meal = $this->meal();
        $this->setting(['delivery_enabled' => true, 'delivery_areas' => ['QA area' => 80000], 'minimum' => 100000]);

        $this->actingAs($customer)->withSession(['cart' => [$meal->id => 2]])->get('/checkout')->assertOk()->assertSee('QA area');
        $reference = session('checkout_reference');
        Http::fake([self::API.'initialize' => Http::response([
            'status' => true, 'data' => ['reference' => $reference, 'authorization_url' => 'https://checkout.paystack.com/fixture'],
        ])]);
        $request = [
            'name' => 'Order Customer', 'phone' => '+234 900 123 4567', 'fulfilment' => 'delivery',
            'delivery_area' => 'QA area', 'address' => 'Test address', 'checkout_reference' => $reference,
            'amount' => 1, 'delivery_fee' => 0, 'email' => 'wrong@example.test',
        ];
        $this->post('/checkout', $request)->assertRedirect('https://checkout.paystack.com/fixture');
        $order = Order::sole();
        $this->assertSame(300000, (int) $order->total);
        $this->assertSame('pending_payment', $order->status);
        $this->assertSame($reference, $order->payment->reference);
        $this->assertSame(2, (int) $order->items()->sole()->quantity);
        Http::assertSent(fn ($outbound) => $outbound['amount'] === 300000 && $outbound['currency'] === 'NGN'
            && $outbound['email'] === $customer->email && $outbound['reference'] === $reference);
        $this->post('/checkout', $request)->assertRedirect(route('orders.show', $order));
        $this->assertDatabaseCount('orders', 1);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Continue to payment');

        $admin = $this->admin();
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'paid'])->assertSessionHasErrors('status');
        $this->patch(route('admin.orders.update', $order), ['status' => 'preparing'])->assertSessionHasErrors('status');
        $this->actingAs(User::factory()->create())->get(route('orders.show', $order))->assertForbidden();
        $this->post(route('orders.refresh', $order))->assertForbidden();

        $this->fakeVerification($order);
        $this->actingAs($customer)->get(route('paystack.callback', ['reference' => $reference]))->assertRedirect(route('orders.show', $order));
        $this->assertSame('paid', $order->fresh()->status);
        $this->get(route('paystack.callback', ['reference' => $reference]))->assertRedirect(route('orders.show', $order));
        $this->get(route('paystack.callback', ['reference' => 'unknown-order-reference']))->assertNotFound();
        $this->assertSame(1, $order->updates()->where('status', 'paid')->count());
        Http::assertSentCount(1);
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Print receipt');
        $this->actingAs($admin)->patch(route('admin.orders.update', $order), ['status' => 'confirmed'])->assertSessionHasNoErrors();
        $this->assertSame('confirmed', $order->fresh()->status);
        Mail::assertQueued(RestaurantMessage::class, fn ($mail) => $mail->title === 'Payment received' && $mail->hasTo($customer->email));
    }

    #[DataProvider('verificationOutcomes')]
    public function test_verification_protects_unpaid_orders(string $field, int|string $value, string $paymentStatus): void
    {
        config(['restaurant.paystack.secret_key' => self::TEST_SECRET]);
        $order = $this->pendingOrder();
        $this->fakeVerification($order, [$field => $value]);
        $this->actingAs($order->user)->post(route('orders.refresh', $order))->assertRedirect();
        $this->assertSame('pending_payment', $order->fresh()->status);
        $this->assertNull($order->fresh()->paid_at);
        $this->assertSame($paymentStatus, $order->payment()->sole()->status);
    }

    public static function verificationOutcomes(): array
    {
        return [
            'pending' => ['status', 'pending', 'pending'],
            'failed' => ['status', 'failed', 'failed'],
            'abandoned' => ['status', 'abandoned', 'abandoned'],
            'wrong amount' => ['amount', 42, 'mismatch'],
            'wrong currency' => ['currency', 'USD', 'mismatch'],
            'wrong reference' => ['reference', 'different-reference', 'mismatch'],
        ];
    }

    public function test_valid_webhook_is_processed_once_and_invalid_signatures_are_rejected(): void
    {
        config(['restaurant.paystack.secret_key' => self::TEST_SECRET, 'restaurant.send_mail' => true]);
        Mail::fake();
        $order = $this->pendingOrder();
        $this->fakeVerification($order);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $order->payment->reference]], JSON_THROW_ON_ERROR);
        $this->webhook($body, ['HTTP_X_PAYSTACK_SIGNATURE' => 'invalid'])->assertUnauthorized();
        Http::assertNothingSent();
        $legacy = ['HTTP_X_PAYSTACK_SIGNATURE' => hash_hmac('sha512', $body, self::TEST_SECRET)];
        $this->webhook($body, $legacy)->assertOk();
        $this->webhook($body, $legacy)->assertOk();
        Http::assertSentCount(1);
        $this->assertSame('paid', $order->fresh()->status);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertSame(1, $order->updates()->where('status', 'paid')->count());
        $this->assertCount(1, Mail::queued(RestaurantMessage::class, fn ($m) => $m->title === 'Payment received'));
    }

    public function test_timestamped_webhook_requires_current_signature_and_handles_rotation(): void
    {
        config(['restaurant.paystack.secret_key' => self::TEST_SECRET, 'restaurant.paystack.webhook_secrets' => ['whsec_previous_fixture', 'whsec_current_fixture']]);
        $order = $this->pendingOrder();
        $this->fakeVerification($order);
        $body = json_encode(['event' => 'charge.success', 'data' => ['reference' => $order->payment->reference]], JSON_THROW_ON_ERROR);
        $legacy = hash_hmac('sha512', $body, self::TEST_SECRET);
        $this->webhook($body, ['HTTP_X_PAYSTACK_SIGNATURE' => $legacy])->assertUnauthorized();

        $header = fn ($timestamp) => ['HTTP_PAYSTACK_SIGNATURE' => 't='.$timestamp.',v1=whk_last:'.str_repeat('a', 128).',v1=whk_current:'.hash_hmac('sha512', "$timestamp.$body", 'whsec_current_fixture')];
        $this->webhook($body, $header(now()->subMinutes(6)->timestamp))->assertUnauthorized();
        $this->webhook($body, $header(now()->timestamp))->assertOk();
        $this->assertSame('paid', $order->fresh()->status);
    }

    public function test_reservations_validate_dates_and_bookings_and_support_staff_updates(): void
    {
        Mail::fake();
        config(['restaurant.send_mail' => true]);
        $this->freezeTime();
        $date = now('Africa/Lagos')->addDays(2)->toDateString();
        $data = ['name' => 'Diner', 'email' => 'diner@example.test', 'phone' => '+234 900 123 4567', 'date' => $date, 'time' => '10:00', 'guests' => 2];
        $this->post('/reservations', array_replace($data, ['date' => '2021-03-04']))->assertSessionHasErrors('time');
        $this->post('/reservations', array_replace($data, ['time' => '21:30']))->assertSessionHasErrors('time');
        $this->post('/reservations', $data)->assertRedirect('/reservations/confirmation');
        $reservation = Reservation::sole();
        $this->get('/reservations/confirmation')->assertOk()->assertSee($reservation->code);
        $this->post('/reservations', array_replace($data, ['time' => '10:30']))->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 1);

        $this->actingAs($this->admin())->get('/admin/reservations')->assertOk()->assertSee($reservation->code);
        $this->patch(route('admin.reservations.update', $reservation), ['status' => 'approved'])->assertSessionHasNoErrors();
        $this->assertSame('approved', $reservation->fresh()->status);
        Mail::assertQueued(RestaurantMessage::class, fn ($mail) => $mail->title === 'Reservation approved');
        $this->patch(route('admin.reservations.update', $reservation), ['status' => 'cancelled'])->assertSessionHasNoErrors();
        $this->assertSame('cancelled', $reservation->fresh()->status);
        $this->post('/reservations', $data)->assertSessionHasNoErrors();
    }

    public function test_staff_can_manage_catalog_images_and_business_settings(): void
    {
        Storage::fake('public');
        $this->get('/admin')->assertRedirect('/admin/login');
        $this->actingAs($this->admin())->get('/admin')->assertOk()->assertSee('Verified sales');
        foreach (['orders', 'customers', 'payments', 'settings', 'catalog/meals', 'catalog/meals/new', 'catalog/photos/new'] as $page) {
            $this->get('/admin/'.$page)->assertOk();
        }
        $this->post('/admin/catalog/categories', ['name' => 'QA category'])->assertSessionHasNoErrors();
        $category = Category::sole();
        $this->post('/admin/catalog/photos', [
            'alt' => 'Rejected', 'image' => UploadedFile::fake()->create('picture.svg', 2, 'image/svg+xml'),
        ])->assertSessionHasErrors('image');
        $this->post('/admin/catalog/photos', [
            'alt' => 'QA photo', 'image' => UploadedFile::fake()->image('test.jpg', 1200, 800), 'show_in_gallery' => 1,
        ])->assertSessionHasNoErrors();
        $photo = \App\Models\Photo::sole();
        Storage::disk('public')->assertExists($photo->path);
        $this->assertStringStartsWith('RIFF', $this->get($photo->url(480))
            ->assertOk()->assertHeader('Content-Type', 'image/webp')->streamedContent());
        $this->get('/storage/photos/unknown.php')->assertNotFound();
        $this->get('/gallery')->assertOk()->assertSee('QA photo');

        $data = ['name' => 'QA meal', 'description' => 'Created through staff form', 'price' => '1400.25',
            'category_id' => $category->id, 'photo_id' => $photo->id, 'photos' => [$photo->id], 'available' => 1, 'featured' => 1];
        $this->post('/admin/catalog/meals', $data)->assertSessionHasNoErrors();
        $meal = Meal::sole();
        $this->assertSame(140025, $meal->price);
        $this->assertSame($photo->id, $meal->photos()->sole()->id);
        $this->patch("/admin/catalog/meals/{$meal->id}", array_replace($data, ['price' => '1450.75']))
            ->assertSessionHasNoErrors();
        $this->get('/menu')->assertOk()->assertSee('₦1,450.75');
        $this->delete("/admin/catalog/categories/{$category->id}")->assertSessionHasErrors('delete');

        $settings = Setting::findOrFail(1)->values();
        $settings = array_replace($settings, ['email' => 'contact@example.test', 'tagline' => 'Edited tagline', 'closed_dates' => '', 'delivery_areas' => "QA area | 1750.25", 'minimum' => '999.99', 'hero_photo_id' => $photo->id, 'delivery_enabled' => 1, 'capacity' => 12]);
        $this->patch('/admin/settings', $settings)->assertSessionHasNoErrors();
        $this->get('/')->assertOk()->assertSee('Edited tagline');
        $this->assertSame(175025, Setting::findOrFail(1)->values()['delivery_areas']['QA area']);

        $this->patch("/admin/catalog/photos/{$photo->id}", ['alt' => 'Replacement', 'image' => UploadedFile::fake()->image('new.png', 480, 360)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($photo->path);
        $replacement = $photo->fresh();
        Storage::disk('public')->assertExists($replacement->path);
        $this->delete("/admin/catalog/photos/{$photo->id}")->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($replacement->path);
        $this->assertNull($meal->fresh()->photo_id);
        $this->assertNull(Setting::findOrFail(1)->values()['hero_photo_id']);
        $this->delete("/admin/catalog/meals/{$meal->id}")->assertSessionHasNoErrors();
        $this->delete("/admin/catalog/categories/{$category->id}")->assertSessionHasNoErrors();
        $this->assertDatabaseCount('meals', 0);
    }

    public function test_checkout_requires_review_after_a_menu_price_change(): void
    {
        config(['restaurant.paystack.secret_key' => self::TEST_SECRET]);
        $meal = $this->meal();
        $this->actingAs(User::factory()->create())->withSession(['cart' => [$meal->id => 1]]);
        $this->get('/checkout')->assertSee('₦1,100.00');
        $reference = session('checkout_reference');
        $meal->update(['price' => 120000]);

        $this->post('/checkout', [
            'name' => 'Test Customer', 'phone' => '+234 900 123 4567',
            'fulfilment' => 'pickup', 'checkout_reference' => $reference,
        ])->assertRedirect('/checkout')->assertSessionHasErrors('cart');
        $this->assertDatabaseCount('orders', 0);
        Http::assertNothingSent();
        $this->get('/checkout')->assertSee('₦1,200.00');
        $this->assertNotSame($reference, session('checkout_reference'));
    }

    public function test_capacity_counts_peak_attendance_and_respects_original_reservation_duration(): void
    {
        $this->setting(['capacity' => 4, 'duration' => 60]);
        $date = now('Africa/Lagos')->addDays(2)->toDateString();
        $request = ['name' => 'Diner', 'email' => 'diner@example.test', 'phone' => '+234 900 123 4567', 'date' => $date, 'guests' => 2];
        $this->post('/reservations', $request + ['time' => '10:00'])->assertRedirect('/reservations/confirmation');
        $this->post('/reservations', $request + ['time' => '11:00'])->assertRedirect('/reservations/confirmation');
        $this->setting(['duration' => 90]);
        $this->post('/reservations', $request + ['time' => '10:30'])->assertRedirect('/reservations/confirmation');
        $this->post('/reservations', $request + ['time' => '11:30'])->assertSessionHasErrors('time');
        $this->assertDatabaseCount('reservations', 3);
    }

    public function test_mail_renders_with_business_branding_and_queues_in_the_database(): void
    {
        config(['restaurant.send_mail' => true, 'mail.from.address' => 'sender@example.test']);
        $html = (new RestaurantMessage('Payment received', 'Your receipt is ready.', 'https://example.test/account'))->render();
        $this->assertStringContainsString('Your receipt is ready.', $html);
        $this->assertStringContainsString('12 Admiralty Way', $html);
        $this->assertStringContainsString('#C5A15D', $html);
        RestaurantMessage::sendTo('diner@example.test', 'Payment received', 'Your receipt is ready.');
        $this->assertDatabaseCount('jobs', 1);
    }

    public function test_staff_setup_uses_the_configured_production_login_email(): void
    {
        $email = 'restaurant-admin@example.test';
        config(['restaurant.admin_email' => $email]);

        $this->artisan('restaurant:admin')
            ->expectsQuestion('Administrator name', 'Restaurant Administrator')
            ->expectsQuestion('New password', 'Disposable test password')
            ->expectsOutput('Administrator account created.')
            ->assertSuccessful();

        $admin = User::where('email', $email)->firstOrFail();
        $this->assertTrue($admin->is_admin);
        $this->assertTrue(Hash::check('Disposable test password', $admin->password));

        $this->artisan('restaurant:admin')->assertFailed();
        $this->assertSame(1, User::where('email', $email)->count());
    }

    public function test_staff_can_change_their_own_password_from_the_dashboard(): void
    {
        $admin = User::factory()->create([
            'is_admin' => true,
            'password' => 'Existing admin password',
            'remember_token' => 'old-remember-token',
        ]);
        $payload = [
            'current_password' => 'Existing admin password',
            'password' => 'New private password',
            'password_confirmation' => 'New private password',
        ];

        $this->get('/admin/password')->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get('/admin/password')->assertForbidden();
        $this->patch('/admin/password', $payload)->assertForbidden();

        $this->actingAs($admin)->get('/admin')->assertOk()->assertSee('Change password');
        $this->get('/admin/password')->assertOk()->assertSee('Confirm new password');
        $this->patch('/admin/password', array_replace($payload, ['password_confirmation' => 'Wrong confirmation']))
            ->assertSessionHasErrors('password');
        $this->patch('/admin/password', array_replace($payload, ['current_password' => 'Wrong password']))
            ->assertSessionHasErrors('current_password');
        $this->assertTrue(Hash::check('Existing admin password', $admin->fresh()->password));

        \Illuminate\Support\Facades\DB::table('sessions')->insert([
            'id' => 'separate-admin-session', 'user_id' => $admin->id, 'payload' => '', 'last_activity' => now()->timestamp,
        ]);
        $this->patch('/admin/password', $payload)->assertRedirect('/admin/password')
            ->assertSessionHas('status', 'Password changed.');
        $updated = $admin->fresh();
        $this->assertTrue(Hash::check('New private password', $updated->password));
        $this->assertNotSame('old-remember-token', $updated->remember_token);
        $this->assertDatabaseMissing('sessions', ['id' => 'separate-admin-session']);
        $this->assertAuthenticatedAs($updated);
        $this->get('/admin')->assertOk();

        $this->post('/logout')->assertRedirect('/');
        $this->post('/login', ['email' => $admin->email, 'password' => 'Existing admin password'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => $admin->email, 'password' => 'New private password'])->assertRedirect('/admin');
    }

    public function test_meal_photos_display_without_credit_on_public_and_admin_pages(): void
    {
        $meal = $this->meal();
        $primary = \App\Models\Photo::create([
            'path' => 'photos/primary.webp', 'width' => 1600, 'height' => 1200,
            'alt' => 'Primary plate photo', 'credit' => 'Private legacy source', 'show_in_gallery' => true,
        ]);
        $secondary = \App\Models\Photo::create([
            'path' => 'photos/secondary.webp', 'width' => 1600, 'height' => 1200,
            'alt' => 'Alternate plate photo', 'credit' => 'Another private source',
        ]);
        $meal->update(['photo_id' => $primary->id]);
        $meal->photos()->sync([$primary->id, $secondary->id]);

        foreach (['/menu', route('menu.meal', $meal->slug)] as $url) {
            $response = $this->get($url)->assertOk()->assertSee('Alternate plate photo')->assertDontSee('private source');
            $this->assertSame(1, substr_count($response->getContent(), 'alt="Primary plate photo"'));
        }
        $this->get('/gallery')->assertOk()->assertSee('Primary plate photo')->assertDontSee('Private legacy source');
        $this->actingAs($this->admin())->get('/admin/catalog/photos')->assertOk()
            ->assertSee('Alternate plate photo')->assertDontSee('Another private source');
        $this->get("/admin/catalog/photos/{$primary->id}/edit")->assertOk()
            ->assertDontSee('name="credit"', false)->assertDontSee('Private legacy source');
    }

    private function meal(): Meal
    {
        $category = Category::create(['name' => 'Fixture category', 'slug' => 'fixture-category']);

        return Meal::create([
            'category_id' => $category->id, 'name' => 'Fixture meal', 'slug' => 'fixture-meal',
            'description' => 'Test description', 'price' => 110000, 'available' => true,
        ]);
    }

    private function admin(): User
    {
        return User::factory()->create(['is_admin' => true]);
    }

    public function test_customer_order_history_shows_totals_statuses_and_only_their_orders(): void
    {
        $order = $this->pendingOrder();
        $order->items()->create(['name' => 'Party Jollof Rice', 'quantity' => 3, 'unit_price' => 30000]);
        $order->items()->create(['name' => 'Chapman', 'quantity' => 1, 'unit_price' => 35000]);
        $other = User::factory()->create();
        $otherOrder = $other->orders()->create(array_replace($order->only([
            'status', 'name', 'email', 'phone', 'fulfilment', 'subtotal', 'delivery_fee', 'total', 'currency',
        ]), ['number' => 'BT-OTHER']));

        $this->get('/account')->assertRedirect('/login');
        $this->actingAs($order->user)->get('/account')->assertOk()
            ->assertSee('Order history')
            ->assertSee('aria-current="page"', false)
            ->assertSee('View order details for '.$order->number)
            ->assertSee('status-indicator--pending', false)
            ->assertSee('Paystack')
            ->assertSee('₦1,250.00')
            ->assertDontSee($otherOrder->number)
            ->assertViewHas('orders', fn ($orders) => $orders->count() === 1 && (int) $orders->first()->meal_quantity === 4);

        $this->get(route('orders.show', $order))->assertOk()
            ->assertSee('aria-current="location"', false)
            ->assertSee('Party Jollof Rice')->assertSee('Qty 3')
            ->assertSee('Payment reference '.$order->payment->reference)
            ->assertSee('Continue to payment');
        $this->get(route('orders.show', $otherOrder))->assertForbidden();
        $this->get('/account/profile')->assertOk()->assertSee('Save profile');
    }

    private function pendingOrder(): Order
    {
        $user = User::factory()->create();
        $order = $user->orders()->create([
            'number' => 'BT-FIXTURE', 'status' => 'pending_payment', 'name' => $user->name, 'email' => $user->email,
            'phone' => '+234 900 111 2222', 'fulfilment' => 'pickup', 'subtotal' => 125000, 'delivery_fee' => 0, 'total' => 125000, 'currency' => 'NGN',
        ]);
        $order->payment()->create(['reference' => 'BT-payment-test', 'status' => 'pending']);

        return $order;
    }

    private function fakeVerification(Order $order, array $changes = []): void
    {
        Http::fake([self::API.'verify/'.$order->payment->reference => Http::response([
            'status' => true,
            'data' => array_replace([
                'status' => 'success', 'reference' => $order->payment->reference,
                'amount' => $order->total, 'currency' => $order->currency, 'id' => 123456, 'channel' => 'card',
            ], $changes),
        ])]);
    }

    private function webhook(string $body, array $headers)
    {
        return $this->call('POST', '/payments/paystack/webhook', [], [], [], $headers + ['CONTENT_TYPE' => 'application/json'], $body);
    }

    private function setting(array $changes): void
    {
        $setting = Setting::findOrFail(1);
        $setting->update(['content' => array_replace($setting->values(), $changes)]);
    }
}






