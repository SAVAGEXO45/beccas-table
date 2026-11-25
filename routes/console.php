<?php

use App\Models\{Payment, User};
use App\Services\Paystack;
use Illuminate\Support\Facades\{Artisan, Schedule, Validator};
use Illuminate\Validation\Rules\Password;

Artisan::command('restaurant:admin {email?}', function (?string $email = null) {
    $email = strtolower(trim($email ?? config('restaurant.admin_email', '')));
    if ($email === '') {
        $this->error('Set RESTAURANT_ADMIN_EMAIL or pass the administrator email to this command.');

        return 1;
    }
    if (User::where('email', $email)->exists()) {
        $this->error('That email already has an account. Choose a dedicated administrator email.');
        return 1;
    }

    $input = ['name' => $this->ask('Administrator name'), 'email' => $email, 'password' => $this->secret('New password')];
    $validator = Validator::make($input, [
        'name' => 'required|string|max:100',
        'email' => 'required|email|max:254|unique:users',
        'password' => ['required', 'max:72', Password::min(12)],
    ]);
    if ($validator->fails()) {
        foreach ($validator->errors()->all() as $error) {
            $this->error($error);
        }
        return 1;
    }
    $user = new User($validator->validated());
    $user->forceFill(['is_admin' => true])->save();
    $this->info('Administrator account created.');

    return 0;
})->purpose('Create a dedicated restaurant administrator');

Artisan::command('restaurant:reconcile-payments', function (Paystack $paystack) {
    if (!$paystack->configured()) {
        return;
    }
    $payments = Payment::where('method', 'paystack')->where('status', 'pending')
        ->where('created_at', '>=', now()->subDays(7))
        ->where('created_at', '<=', now()->subMinutes(15))
        ->where(fn ($query) => $query->whereNull('verified_at')->orWhere('verified_at', '<=', now()->subHours(2)))
        ->orderBy('verified_at')->limit(15)->get();
    foreach ($payments as $payment) {
        if (!$paystack->verify($payment)) {
            // Defer retries so an unavailable reference cannot monopolize this batch.
            Payment::whereKey($payment->id)->where('status', 'pending')->update(['verified_at' => now()]);
        }
    }
})->purpose('Reconcile recent incomplete Paystack payments');

Schedule::command('restaurant:reconcile-payments')->everyFifteenMinutes()->withoutOverlapping(10);

