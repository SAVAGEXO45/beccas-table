<?php

namespace App\Http\Controllers;

use App\Mail\RestaurantMessage;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{Auth, DB, Hash, Password};
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rules\Password as PasswordRule;

class AuthController extends Controller
{
    public function register(Request $request)
    {
        $request->merge(['email' => strtolower((string) $request->string('email'))]);
        $user = User::create($request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|max:254|unique:users',
            'phone' => ['nullable', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]));
        Auth::login($user);
        $request->session()->regenerate();
        RestaurantMessage::sendTo($user->email, 'Welcome', 'Your account is ready. Sign in to place an order and follow its progress.');

        return redirect()->intended(route('account'));
    }

    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => 'required|email|max:254', 'password' => 'required|string|max:72']);
        $credentials['email'] = strtolower($credentials['email']);

        $credentials['is_disabled'] = false;
        if ($request->routeIs('admin.login.submit')) {
            $credentials['is_admin'] = true;
        }

        if (!Auth::attempt($credentials)) {
            return back()->withErrors(['email' => 'The email or password is incorrect.'])->onlyInput('email');
        }

        $request->session()->regenerate();
        return redirect()->intended(route($request->user()->is_admin ? 'admin.dashboard' : 'account'));
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }

    public function profile(Request $request)
    {
        $user = $request->user();
        $request->merge(['email' => strtolower((string) $request->string('email'))]);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:254', Rule::unique('users')->ignore($user)],
            'phone' => ['nullable', 'regex:/^[+0-9 ()-]{7,30}$/'],
            'current_password' => 'required|current_password',
            'password' => ['nullable', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]);

        if ($data['email'] !== $user->email) {
            $user->email_verified_at = null;
        }
        $user->update(collect($data)->except('current_password')->reject(fn ($value, $key) => $key === 'password' && !$value)->all());
        $request->session()->regenerate();

        return back()->with('status', 'Profile updated.');
    }

    public function updateAdminPassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => 'required|string|max:72',
            'password' => [
                'bail', 'required', 'string', 'confirmed', 'different:current_password', PasswordRule::min(12),
                function (string $attribute, string $value, Closure $fail): void {
                    if (strlen($value) > 72 || str_contains($value, chr(0))) {
                        $fail('Use a password of at most 72 bytes without null characters.');
                    }
                },
            ],
        ]);

        $admin = DB::transaction(function () use ($data, $request) {
            $admin = User::lockForUpdate()->findOrFail($request->user()->id);
            abort_unless($admin->is_admin && !$admin->is_disabled, 403);
            if (!Hash::check($data['current_password'], $admin->password)) {
                throw ValidationException::withMessages(['current_password' => 'The current password is incorrect.']);
            }

            $admin->forceFill(['password' => $data['password'], 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $admin->id)->delete();

            return $admin;
        }, 3);

        Auth::setUser($admin);
        $request->session()->regenerate(true);

        return redirect()->route('admin.password.edit')->with('status', 'Password changed.');
    }

    public function sendReset(Request $request)
    {
        $email = strtolower($request->validate(['email' => 'required|email|max:254'])['email']);
        if (config('restaurant.send_mail')) {
            Password::sendResetLink(['email' => $email, 'is_disabled' => false]);
        }

        return back()->with('status', 'If the account is eligible and email is available, a reset link will be sent.');
    }

    public function reset(Request $request)
    {
        $request->merge(['email' => strtolower((string) $request->string('email'))]);
        $credentials = $request->validate([
            'token' => 'required|string|max:128', 'email' => 'required|email|max:254',
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)],
        ]);

        $status = Password::reset($credentials + ['is_disabled' => false], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
        });

        return $status === Password::PASSWORD_RESET
            ? redirect()->route('login')->with('status', __($status))
            : back()->withErrors(['email' => __($status)])->onlyInput('email');
    }
}

