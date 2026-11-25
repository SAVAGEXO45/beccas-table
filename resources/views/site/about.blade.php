@php($business = \App\Models\Setting::current())
<x-layout title="About">
    <section class="container section">
        <header class="page-header"><p class="eyebrow">About {{ $business['name'] }}</p><h1>{{ $business['about_heading'] }}</h1></header>
        <div class="split">
            <div class="prose-content"><p>{{ $business['about'] }}</p><p>{{ $business['reasons'] }}</p><a class="button" href="{{ route('reservations.create') }}">Reserve a table</a></div>
            <x-photo :photo="$business['about_photo_id'] ? \App\Models\Photo::find($business['about_photo_id']) : null" placeholder="Restaurant photography coming soon"/>
        </div>
    </section>
</x-layout>
