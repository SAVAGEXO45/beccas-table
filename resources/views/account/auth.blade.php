@php
    $screens = ['login' => ['Customer sign in', 'login'], 'admin-login' => ['Admin sign in', 'admin.login.submit'], 'register' => ['Create an account', 'register'], 'forgot' => ['Reset your password', 'password.email'], 'reset' => ['Choose a new password', 'password.update']];
    [$title, $target] = $screens[$mode];
    $isSignIn = in_array($mode, ['login', 'admin-login'], true);
@endphp
<x-layout :title="$title">
    <section class="container section auth-width">
        <header class="page-header"><h1>{{ $title }}</h1></header>
        <form class="panel form-stack" method="POST" action="{{ route($target) }}">
            @csrf
            @if($mode === 'reset')<input type="hidden" name="token" value="{{ $token }}">@endif
            @if($mode === 'register')
                <label>Full name<input name="name" autocomplete="name" value="{{ old('name') }}" required maxlength="100"></label>
                <label>Phone (optional)<input name="phone" type="tel" autocomplete="tel" value="{{ old('phone') }}" maxlength="30"></label>
            @endif
            <label>Email<input name="email" type="email" autocomplete="email" value="{{ old('email', $mode === 'reset' ? request('email') : '') }}" required maxlength="254"></label>
            @if($mode !== 'forgot')
                <label>Password<input name="password" type="password" autocomplete="{{ $isSignIn ? 'current-password' : 'new-password' }}" required maxlength="72" @unless($isSignIn) minlength="12" @endunless></label>
            @endif
            @if(in_array($mode, ['register', 'reset'], true))
                <label>Confirm password<input name="password_confirmation" type="password" autocomplete="new-password" required minlength="12" maxlength="72"></label>
                <small>Use at least 12 characters.</small>
            @endif
            <button class="button">{{ $mode === 'forgot' ? 'Send reset link' : $title }}</button>
        </form>
        <div class="actions">
        @if($mode === 'login')
            <a class="text-link" href="{{ route('admin.login') }}">Admin sign in</a>
            <a class="text-link" href="{{ route('register') }}">Create an account</a>
            <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>
        @elseif($mode === 'admin-login')
            <a class="text-link" href="{{ route('password.request') }}">Forgot password?</a>
        @else
            <a class="text-link" href="{{ route('login') }}">Return to sign in</a>
        @endif
        </div>
    </section>
</x-layout>
