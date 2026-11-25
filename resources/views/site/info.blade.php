@php($business = \App\Models\Setting::current())
<x-layout :title="$title">
    <section class="container section narrow">
        <header class="page-header"><h1>{{ $title }}</h1></header>
        @if($business[$page])
            <div class="prose-content whitespace-pre-line">{{ $business[$page] }}</div>
        @elseif($page === 'faq')
            <details><summary>Where are you located?</summary><p>{{ $business['address'] }}. See our <a class="text-link" href="{{ route('contact') }}">opening hours and directions</a>.</p></details>
            <details><summary>How do I book a table?</summary><p><a class="text-link" href="{{ route('reservations.create') }}">Send a reservation request</a>. The restaurant reviews requests before confirming them.</p></details>
            <details><summary>Can I collect my order?</summary><p>Choose pickup at checkout. {{ $business['pickup'] }}</p></details>
            <details><summary>Do you offer delivery?</summary><p>{{ $business['delivery_enabled'] ? 'Choose an available delivery area at checkout to see its fee.' : 'Online orders are currently available for pickup.' }}</p></details>
        @else
            @if($business['email'])
                <p>Contact the restaurant at <a class="text-link" href="mailto:{{ $business['email'] }}">{{ $business['email'] }}</a> for the current {{ strtolower($title) }}.</p>
            @else
                <p><a class="text-link" href="{{ route('contact') }}">Contact the restaurant</a> for the current {{ strtolower($title) }}.</p>
            @endif
        @endif
        @if($page === 'terms' && is_file(storage_path('app/private/menu-stock-credits.json')))
            <p><a class="text-link" href="{{ route('menu.photo-credits') }}">Image licenses</a></p>
        @endif
    </section>
</x-layout>
