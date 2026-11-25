@php
    $business = \App\Models\Setting::current();
    $today = now('Africa/Lagos');
@endphp
<x-layout title="Table reservations">
    <section class="container section">
        <header class="page-header"><p class="eyebrow">Dine in Lekki Phase 1</p><h1>Reserve a table</h1><p>Send your preferred time and party size. The restaurant will review your request.</p></header>
        <div class="checkout-grid">
            @if($business['reservations_enabled'])
                <form class="panel form-stack" method="POST" action="{{ route('reservations.store') }}">
                    @csrf
                    <div class="form-row">
                        <label>Full name<input name="name" value="{{ old('name', auth()->user()?->name) }}" autocomplete="name" maxlength="100" required></label>
                        <label>Phone<input type="tel" name="phone" value="{{ old('phone', auth()->user()?->phone) }}" autocomplete="tel" maxlength="30" required></label>
                    </div>
                    <label>Email<input type="email" name="email" value="{{ old('email', auth()->user()?->email) }}" autocomplete="email" maxlength="254" required></label>
                    <div class="form-row">
                        <label>Date<input type="date" name="date" min="{{ $today->toDateString() }}" max="{{ $today->copy()->addDays($business['max_days'])->toDateString() }}" value="{{ old('date') }}" required></label>
                        <label>Time (Lagos)<input type="time" name="time" value="{{ old('time') }}" required></label>
                        <label>Guests<input type="number" name="guests" min="1" max="{{ min(100, $business['capacity'] ?? 100) }}" value="{{ old('guests', 2) }}" required></label>
                    </div>
                    <label>Special requests (optional)<textarea name="requests" rows="4" maxlength="1000">{{ old('requests') }}</textarea></label>
                    <button class="button">Request reservation</button>
                </form>
            @else
                <p class="notice">Online reservations are currently unavailable. Contact the restaurant to ask about a table.</p>
            @endif
            <aside class="panel summary"><h2>Opening hours</h2><x-hours :hours="$business['hours']"/><p>{{ $business['address'] }}</p>
                <a class="text-link" href="{{ $business['whatsapp'] }}">Ask about a reservation on WhatsApp</a>
            </aside>
        </div>
    </section>
</x-layout>
