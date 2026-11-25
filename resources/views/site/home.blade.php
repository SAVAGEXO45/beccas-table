@php
    $business = \App\Models\Setting::current();
    $homePhotos = \App\Models\Photo::whereIn('id', array_filter([$business['hero_photo_id'], $business['about_photo_id']]))->get()->keyBy('id');
    $hero = $homePhotos->get($business['hero_photo_id']);
    $aboutPhoto = $homePhotos->get($business['about_photo_id']);
@endphp
<x-layout :image="$hero?->url() ?: asset('media/beccas-table-hero-1536.webp')">
    <section class="hero">
        <div class="container hero-inner">
            <div class="hero-copy">
                <p class="eyebrow">Nigerian &amp; Continental dining</p>
                <h1>{{ mb_strtoupper($business['name']) }}</h1>
                <p class="hero-tagline">{{ $business['tagline'] }}</p>
                <div class="actions">
                    <a class="button" href="{{ route('menu') }}">Explore the menu</a>
                    <a class="button inverse" href="{{ route('reservations.create') }}">Reserve a table</a>
                </div>
            </div>
            @if($hero)
                <x-photo class="hero-photo" :photo="$hero" :eager="true" sizes="(max-width: 720px) 100vw, 48vw"/>
            @else
                <x-brand-artwork :name="$business['name']"/>
            @endif
        </div>
    </section>
    <section class="container section introduction">
        <div>
            <p class="eyebrow">Welcome to {{ $business['name'] }}</p>
            <h2>{{ $business['intro_heading'] }}</h2>
        </div>
        <div>
            <p>{{ $business['intro'] }}</p>
            <a class="text-link" href="{{ route('about') }}">About the restaurant</a>
        </div>
    </section>
    <section class="section menu-feature">
        <div class="container">
            <div class="section-header">
                <div><p class="eyebrow">From the kitchen</p><h2>Explore our menu</h2></div>
                <a class="text-link" href="{{ route('menu') }}">See the full menu</a>
            </div>
            @if($categories->isNotEmpty())
                <nav class="filters" aria-label="Food categories">
                    @foreach($categories as $category)<a href="{{ route('menu.category', $category->slug) }}">{{ $category->name }}</a>@endforeach
                </nav>
            @endif
            @if($featured->isNotEmpty())<x-meal-list :meals="$featured"/>
            @else<p>Browse the menu for dishes, portions and current ordering availability.</p><a class="text-link" href="{{ $business['whatsapp'] }}">Ask on WhatsApp</a>@endif
        </div>
    </section>
    <section class="container section split">
        @if($aboutPhoto)<x-photo :photo="$aboutPhoto"/>@endif
        <div>
            <p class="eyebrow">Our restaurant</p>
            <h2>{{ $business['about_heading'] }}</h2>
            <p>{{ $business['about'] }}</p>
            <p>{{ $business['reasons'] }}</p>
        </div>
    </section>
    @if($gallery->isNotEmpty())
        <section class="container section"><div class="section-header"><h2>From the gallery</h2><a class="text-link" href="{{ route('gallery') }}">View gallery</a></div>
            <div class="meal-grid">@foreach($gallery as $photo)<x-photo :photo="$photo"/>@endforeach</div>
        </section>
    @endif
    @if($testimonials->isNotEmpty())
        <section class="container section"><h2>From our guests</h2><div class="meal-grid">
            @foreach($testimonials as $testimonial)<figure class="panel"><blockquote>“{{ $testimonial->quote }}”</blockquote><figcaption>{{ $testimonial->name }}</figcaption></figure>@endforeach
        </div></section>
    @endif
    <section class="booking-band"><div class="container section split">
        <div><p class="eyebrow">Dine with us</p><h2>Make time for a meal together.</h2><a class="button" href="{{ route('reservations.create') }}">Request a table</a></div>
        <div><p>{{ $business['address'] }}</p><x-hours :hours="$business['hours']"/><a class="text-link" href="{{ route('contact') }}">Get directions</a></div>
    </div></section>
</x-layout>


