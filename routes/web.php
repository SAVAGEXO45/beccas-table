<?php

use App\Http\Controllers\Admin\{CatalogController, DashboardController, MealConfigurationController, OperationsController, SettingsController};
use App\Http\Controllers\{AuthController, OrderController, ReservationController, SiteController};
use Illuminate\Support\Facades\Route;

Route::get('/', [SiteController::class, 'home'])->name('home');
Route::view('/about', 'site.about')->name('about');
Route::get('/menu', [SiteController::class, 'menu'])->name('menu');
Route::get('/menu/photo-credits', [SiteController::class, 'photoCredits'])->name('menu.photo-credits');
Route::get('/menu/category/{category:slug}', [SiteController::class, 'menu'])->name('menu.category');
Route::get('/menu/meal/{meal:slug}', [SiteController::class, 'meal'])->name('menu.meal');
Route::get('/gallery', [SiteController::class, 'gallery'])->name('gallery');
Route::get('/storage/photos/{file}', [SiteController::class, 'photograph'])
    ->where('file', '[a-f0-9]{8}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{4}-[a-f0-9]{12}(?:-480|-960)?\.webp');
Route::view('/contact', 'site.contact')->name('contact');
Route::view('/faq', 'site.info', ['page' => 'faq', 'title' => 'Frequently asked questions'])->name('faq');
Route::view('/privacy', 'site.info', ['page' => 'privacy', 'title' => 'Privacy policy'])->name('privacy');
Route::view('/terms', 'site.info', ['page' => 'terms', 'title' => 'Terms and conditions'])->name('terms');
Route::get('/sitemap.xml', [SiteController::class, 'sitemap'])->name('sitemap');
Route::get('/robots.txt', [SiteController::class, 'robots'])->name('robots');
Route::get('/reservations', [ReservationController::class, 'create'])->name('reservations.create');
Route::post('/reservations', [ReservationController::class, 'store'])->middleware('throttle:6,1')->name('reservations.store');
Route::get('/reservations/confirmation', [ReservationController::class, 'confirmation'])->name('reservations.confirmation');
Route::get('/cart', [OrderController::class, 'cart'])->name('cart');
Route::get('/logout', fn () => to_route('home'));
Route::post('/cart/{meal}', [OrderController::class, 'updateCart'])->whereNumber('meal')->middleware('throttle:60,1,cart-update')->block()->name('cart.update');

Route::post('/payments/paystack/webhook', [OrderController::class, 'webhook'])->name('paystack.webhook');

Route::middleware('guest')->group(function () {
    Route::view('/register', 'account.auth', ['mode' => 'register'])->name('register');
    Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:6,1');
    Route::view('/login', 'account.auth', ['mode' => 'login'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::view('/admin/login', 'account.auth', ['mode' => 'admin-login'])->name('admin.login');
    Route::post('/admin/login', [AuthController::class, 'login'])->middleware('throttle:login')->name('admin.login.submit');
    Route::view('/forgot-password', 'account.auth', ['mode' => 'forgot'])->name('password.request');
    Route::post('/forgot-password', [AuthController::class, 'sendReset'])->middleware('throttle:5,1')->name('password.email');
    Route::get('/reset-password/{token}', fn (string $token) => view('account.auth', ['mode' => 'reset', 'token' => $token]))->name('password.reset');
    Route::post('/reset-password', [AuthController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    Route::get('/account', [SiteController::class, 'account'])->name('account');
    Route::view('/account/profile', 'account.profile')->name('profile');
    Route::patch('/account/profile', [AuthController::class, 'profile'])->middleware('throttle:8,1')->name('profile.update');
    Route::get('/checkout', [OrderController::class, 'checkout'])->block()->name('checkout');
    Route::post('/checkout', [OrderController::class, 'place'])->middleware('throttle:8,1,checkout-place')->block(20, 10)->name('checkout.place');
    Route::get('/account/orders/{order:number}', [OrderController::class, 'show'])->name('orders.show');
    Route::post('/account/orders/{order:number}/pay', [OrderController::class, 'pay'])->middleware('throttle:8,1,paystack-start')->name('orders.pay');
    Route::post('/account/orders/{order:number}/bank-transfer', [OrderController::class, 'notifyTransfer'])->middleware('throttle:12,1,bank-transfer-report')->name('orders.transfer.notify');
    Route::post('/account/orders/{order:number}/payment-status', [OrderController::class, 'refresh'])->middleware('throttle:8,1,paystack-refresh')->name('orders.refresh');
    Route::get('/payments/paystack/callback', [OrderController::class, 'callback'])->middleware('throttle:12,1,paystack-callback')->name('paystack.callback');
});

Route::prefix('admin')->name('admin.')->middleware(['auth', 'can:manage-restaurant'])->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');
    Route::view('/password', 'admin.password')->name('password.edit');
    Route::patch('/password', [AuthController::class, 'updateAdminPassword'])
        ->middleware('throttle:5,1')->block()->name('password.update');
    Route::get('/customers', [DashboardController::class, 'customers'])->name('customers.index');
    Route::get('/customers/{customer}', [DashboardController::class, 'customer'])->name('customers.show');
    Route::patch('/customers/{customer}', [DashboardController::class, 'updateCustomer'])->name('customers.update');
    Route::get('/orders', [OperationsController::class, 'orders'])->name('orders.index');
    Route::get('/orders/{order:number}', [OperationsController::class, 'order'])->name('orders.show');
    Route::patch('/orders/{order:number}', [OperationsController::class, 'updateOrder'])->name('orders.update');
    Route::patch('/orders/{order:number}/bank-transfer', [OperationsController::class, 'reviewTransfer'])->middleware('throttle:20,1,bank-transfer-review')->name('orders.transfer.review');
    Route::post('/orders/{order:number}/payment-status', [OrderController::class, 'refresh'])->middleware('throttle:12,1')->name('orders.refresh');
    Route::get('/payments', [OperationsController::class, 'payments'])->name('payments.index');
    Route::get('/reservations', [OperationsController::class, 'reservations'])->name('reservations.index');
    Route::patch('/reservations/{reservation}', [OperationsController::class, 'updateReservation'])->name('reservations.update');
    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings', [SettingsController::class, 'update'])->name('settings.update');
    Route::get('/settings/bank-transfer', [SettingsController::class, 'bank'])->name('settings.bank');
    Route::patch('/settings/bank-transfer', [SettingsController::class, 'updateBank'])->middleware('throttle:8,1,bank-transfer-settings')->name('settings.bank.update');

    Route::prefix('menu/meals/{meal}')->name('menu.meals.')->controller(MealConfigurationController::class)->group(function () {
        Route::get('/preview', 'preview')->name('preview');
        Route::get('/options', 'options')->name('options');
        Route::put('/options', 'saveOptions')->name('save-options');
        Route::get('/photos', 'photos')->name('photos');
        Route::post('/photos', 'upload')->name('upload');
        Route::put('/photos/order', 'order')->name('photo-order');
        Route::patch('/photos/{photo}', 'replace')->name('replace-photo');
        Route::delete('/photos/{photo}', 'detach')->name('detach-photo');
    });

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::get('/meal-categories/{category}/meals', [CatalogController::class, 'categoryMeals'])->name('category-meals');
        Route::get('/{section}', [CatalogController::class, 'index'])->name('index');
        Route::get('/{section}/new', [CatalogController::class, 'edit'])->name('create');
        Route::post('/{section}', [CatalogController::class, 'save'])->name('store');
        Route::get('/{section}/{id}/edit', [CatalogController::class, 'edit'])->whereNumber('id')->name('edit');
        Route::patch('/{section}/{id}', [CatalogController::class, 'save'])->whereNumber('id')->name('update');
        Route::delete('/{section}/{id}', [CatalogController::class, 'delete'])->whereNumber('id')->name('delete');
    });
});




