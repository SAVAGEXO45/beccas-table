@php($business = \App\Models\Setting::current())
<x-layout title="Reservation details">
    <section class="container section narrow">
        <header class="page-header"><h1>Reservation details</h1></header>
        <div class="panel">
            <h2>{{ \App\Models\Order::label($reservation->status) }}</h2>
            <p>{{ $reservation->code }}</p><p>{{ \Carbon\CarbonImmutable::parse($reservation->date)->format('j F Y') }} at {{ substr($reservation->time, 0, 5) }} Lagos time · {{ $reservation->guests }} {{ Str::plural('guest', $reservation->guests) }}</p>
            @if($reservation->status === 'pending')<p>Your request is awaiting confirmation.</p>@endif
            <a class="button" href="{{ $business['whatsapp'].(str_contains($business['whatsapp'], '?') ? '&' : '?').'text='.rawurlencode("Hello, I have a question about reservation {$reservation->code}.") }}">Ask on WhatsApp</a>
        </div>
    </section>
</x-layout>
