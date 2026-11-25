@php($user = auth()->user())
<x-account-layout title="Edit profile">
    <section class="account-profile">
        <header class="page-header"><h1 class="icon-label"><x-icon name="customers"/> Edit profile</h1></header>
        <form class="panel form-stack" method="POST" action="{{ route('profile.update') }}">
            @csrf @method('PATCH')
            <label>Full name<input name="name" value="{{ old('name', $user->name) }}" autocomplete="name" required maxlength="100"></label>
            <label>Email<input type="email" name="email" value="{{ old('email', $user->email) }}" autocomplete="email" required maxlength="254"></label>
            <label>Phone<input type="tel" name="phone" value="{{ old('phone', $user->phone) }}" autocomplete="tel" maxlength="30"></label>
            <label>Current password<input type="password" name="current_password" autocomplete="current-password" required maxlength="72"></label>
            <label>New password (optional)<input type="password" name="password" autocomplete="new-password" minlength="12" maxlength="72"></label>
            <label>Confirm new password<input type="password" name="password_confirmation" autocomplete="new-password" minlength="12" maxlength="72"></label>
            <button class="button">Save profile</button>
        </form>
        <div class="actions"><a class="text-link" href="{{ route('account') }}">Back to your account</a></div>
    </section>
</x-account-layout>
