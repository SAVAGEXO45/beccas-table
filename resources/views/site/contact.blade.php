@php($business = \App\Models\Setting::current())
<x-layout title="Visit us">
    <section class="container section">
        <header class="page-header"><p class="eyebrow">Lekki Phase 1, Lagos</p><h1>Visit us</h1></header>
        <div class="split">
            <div class="panel">
                <h2>Find the restaurant</h2>
                <address>{{ $business['address'] }}</address>
                <div class="actions"><a class="button" href="https://www.google.com/maps/dir/?api=1&amp;destination={{ rawurlencode($business['address']) }}">Get directions</a></div>
                <p><a class="text-link" href="tel:{{ preg_replace('/[^+0-9]/', '', $business['phone']) }}">{{ $business['phone'] }}</a></p>
                @if($business['email'])<p><a class="text-link" href="mailto:{{ $business['email'] }}">{{ $business['email'] }}</a></p>@endif
                <p><a class="text-link" href="{{ $business['whatsapp'] }}">Message us on WhatsApp</a></p>
            </div>
            <div class="panel"><h2>Opening hours</h2><x-hours :hours="$business['hours']"/></div>
        </div>
    </section>
</x-layout>
