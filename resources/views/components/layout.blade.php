@props(['title' => null, 'description' => null, 'admin' => false, 'image' => null])
@php
    $business = \App\Models\Setting::current();
    $name = $business['name'];
    $logo = $business['logo_photo_id'] ? \App\Models\Photo::find($business['logo_photo_id']) : null;
    $pageTitle = $title ? "$title | $name" : "$name | Nigerian & Continental Restaurant in Lekki";
    $summary = $description ?: $business['intro'];
    $cartCount = collect(session('cart', []))->sum(fn ($line) => is_array($line) ? $line['quantity'] : $line);
    $canonical = url()->current();
    $links = ['menu' => 'Menu', 'about' => 'About', 'gallery' => 'Gallery', 'contact' => 'Visit us'];
@endphp
<!doctype html>
<html lang="en-NG">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $pageTitle }}</title>
    <meta name="description" content="{{ $summary }}">
    <link rel="canonical" href="{{ $canonical }}">
    <meta property="og:type" content="website">
    <meta property="og:title" content="{{ $pageTitle }}">
    <meta property="og:description" content="{{ $summary }}">
    <meta property="og:url" content="{{ $canonical }}">
    @if ($image)<meta property="og:image" content="{{ url($image) }}">@endif
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body @if($admin) class="staff-page" @endif style="--charcoal:{{ $business['charcoal'] }};--gold:{{ $business['gold'] }};--ivory:{{ $business['ivory'] }}">
<a class="skip-link" href="#main">Skip to content</a>
@if($admin)
    <header class="staff-header">
        <a class="brand" href="{{ route('admin.dashboard') }}" aria-label="Admin dashboard">
            @if($logo)<img src="{{ $logo->url(480) }}" alt="" width="48" height="48">@endif
            <span>{{ $name }}</span>
        </a>
        <div class="header-auth-actions">
            <a class="staff-website-link" href="{{ route('home') }}">View website</a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button class="header-auth" type="submit">Sign out</button>
            </form>
            <button class="staff-nav-toggle header-auth" type="button" aria-expanded="false" aria-controls="staff-navigation" data-nav-toggle>
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true" focusable="false"><path d="M4 6h16M4 12h16M4 18h16"/></svg>
                Menu
            </button>
        </div>
    </header>
    <div class="staff-shell">
        <x-staff-navigation/>
        <main id="main" class="staff-main">
@else
<header class="site-header">
    <div class="container nav-inner">
        <a class="brand" href="{{ route('home') }}" aria-label="{{ $name }} home">
            @if ($logo)<img src="{{ $logo->url(480) }}" alt="" width="48" height="48">@endif
            <span>{{ $name }}</span>
        </a>
        <button type="button" class="nav-toggle" aria-expanded="false" aria-controls="site-menu" data-nav-toggle>Menu</button>
        <nav id="site-menu" class="main-nav" aria-label="Main navigation">
            @foreach ($links as $route => $label)
                <a href="{{ route($route) }}" @if(request()->routeIs($route, $route.'.*')) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
            <a href="{{ route('cart') }}">Cart @if($cartCount)<span class="cart-count">{{ $cartCount }}</span>@endif</a>
            @if(auth()->check() && !auth()->user()->is_admin)
                <a href="{{ route('account') }}">Account</a>
            @endif
            <a class="button small" href="{{ route('reservations.create') }}">Reserve a table</a>
        </nav>
        <div class="header-auth-actions">
            @auth
                @if(auth()->user()->is_admin)
                    <a class="header-auth" href="{{ route('admin.dashboard') }}">Admin dashboard</a>
                @endif
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button class="header-auth" type="submit">Sign out</button>
                </form>
            @else
                <a class="header-auth" href="{{ route('login') }}">Login</a>
            @endauth
        </div>
    </div>
</header>
    <main id="main">
@endif
    @if(session('status') || $errors->any())
        <div class="{{ $admin ? '' : 'container' }} messages">
        @if(session('status'))<p class="notice" role="status">{{ session('status') }}</p>@endif
        @if($errors->any())
            <div class="notice error" role="alert">
                <ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul>
            </div>
        @endif
        </div>
    @endif
    {{ $slot }}
</main>
@if($admin)
    </div>
@else
<footer class="site-footer">
    <div class="container footer-body">
        <div><a class="brand" href="{{ route('home') }}">{{ $name }}</a><p>{{ $business['footer'] }}</p></div>
        <div><h2>Visit</h2><address>{{ $business['address'] }}</address><a href="tel:{{ preg_replace('/[^+0-9]/', '', $business['phone']) }}">{{ $business['phone'] }}</a>@if($business['email'])<a href="mailto:{{ $business['email'] }}">{{ $business['email'] }}</a>@endif</div>
        <div><h2>Connect</h2><a href="{{ $business['whatsapp'] }}">WhatsApp</a><a href="{{ $business['instagram'] }}">Instagram {{ $business['instagram_label'] }}</a><a href="{{ $business['facebook'] }}">Facebook</a></div>
    </div>
    <div class="container footer-bottom">
      <span>&copy; {{ now()->year }} {{ $name }}</span>
      <nav aria-label="Footer"><a href="{{ route('faq') }}">FAQ</a><a href="{{ route('privacy') }}">Privacy</a><a href="{{ route('terms') }}">Terms</a></nav>
    </div>
</footer>
@endif
</body>
</html>



