@props(['title', 'admin' => false])
<x-layout :title="$title">
    <div class="container account-shell">
        <nav class="account-navigation print-hide" aria-label="Your account">
            <a href="{{ route('account') }}" @if(request()->routeIs('account')) aria-current="page" @elseif(request()->routeIs('orders.show')) aria-current="location" @endif>
                <x-icon name="orders"/> My orders
            </a>
            <a href="{{ route('profile') }}" @if(request()->routeIs('profile')) aria-current="page" @endif>
                <x-icon name="customers"/> Edit profile
            </a>
            <form method="POST" action="{{ route('logout') }}">
                @csrf
                <button type="submit"><x-icon name="logout"/> Sign out</button>
            </form>
        </nav>
        <div class="account-content">{{ $slot }}</div>
    </div>
</x-layout>