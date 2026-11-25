<x-layout title="Your cart">
    <section class="container section narrow">
        <header class="page-header"><h1>Your cart</h1></header>
        @forelse($lines as $line)
            <article class="cart-row">
                <div><h2>{{ $line['meal']?->name ?: 'Removed meal' }}</h2>@if($line['options'])<small>{{ implode(' · ', $line['options']) }}</small>@endif<p>{{ $line['available'] ? \App\Models\Setting::money($line['total']) : 'Unavailable. Remove this meal to continue.' }}</p></div>
                <div class="actions">
                    @if($line['available'])
                        <form method="POST" class="filter-form inline" data-cart-quantity action="{{ route('cart.update', $line['id']) }}">
                            @csrf <input type="hidden" name="line" value="{{ $line['key'] }}">
                            <label>Quantity<input aria-label="Quantity for {{ $line['meal']->name }}" type="number" name="quantity" value="{{ $line['quantity'] }}" min="1" max="30" required></label>
                            <button class="button small outline">Update</button>
                        </form>
                    @endif
                    <form method="POST" action="{{ route('cart.update', $line['id']) }}">
                        @csrf <input type="hidden" name="line" value="{{ $line['key'] }}"><input type="hidden" name="quantity" value="0"><button class="text-link">Remove</button>
                    </form>
                </div>
            </article>
        @empty
            <p>Your cart is empty.</p>
        @endforelse
        @if($lines->isNotEmpty())<p class="price">Subtotal {{ \App\Models\Setting::money($lines->sum('total')) }}</p><p>Pickup total {{ \App\Models\Setting::money($lines->sum('total')) }}. Any delivery charge is shown at checkout.</p>@endif
        <div class="actions">
            @if($lines->isNotEmpty() && !$lines->contains('available', false))<a class="button" href="{{ route('checkout') }}">Continue to checkout</a>@endif
            <a class="button outline" href="{{ route('menu') }}">Browse the menu</a>
        </div>
    </section>
</x-layout>


