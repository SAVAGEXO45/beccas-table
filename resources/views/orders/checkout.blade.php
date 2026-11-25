@php($business = \App\Models\Setting::current())
<x-layout title="Checkout">
    <section class="container section">
        <header class="page-header"><h1>Checkout</h1><p>Review your order and choose how to receive it.</p></header>
        <div class="checkout-grid">
            <form class="panel form-stack" method="POST" action="{{ route('checkout.place') }}" data-checkout>
                @csrf
                <input type="hidden" name="checkout_reference" value="{{ $reference }}">
                <h2>Contact details</h2>
                <label>Full name<input name="name" autocomplete="name" value="{{ old('name', auth()->user()->name) }}" maxlength="100" required></label>
                <label>Receipt email<input type="email" name="receipt_email" autocomplete="email" value="{{ old('receipt_email', auth()->user()->email) }}" maxlength="254" required></label>
                <label>Phone<input type="tel" name="phone" autocomplete="tel" value="{{ old('phone', auth()->user()->phone) }}" maxlength="30" required></label>
                <fieldset>
                    <legend>Fulfilment</legend>
                    <label class="check"><input type="radio" name="fulfilment" value="pickup" @checked(old('fulfilment', 'pickup') === 'pickup')> Restaurant pickup</label>
                    <p data-pickup>{{ $business['pickup'] }}</p>
                    @if($business['delivery_enabled'])
                        <label class="check"><input type="radio" name="fulfilment" value="delivery" @checked(old('fulfilment') === 'delivery')> Delivery</label>
                        <div class="form-stack" data-delivery>
                            <label>Delivery area<select name="delivery_area"><option value="">Select an area</option>@foreach($business['delivery_areas'] as $area => $fee)<option value="{{ $area }}" data-fee="{{ $fee }}" @selected(old('delivery_area') == $area)>{{ $area }} · {{ \App\Models\Setting::money($fee) }}</option>@endforeach</select></label>
                            <label><span class="icon-label"><x-icon name="pin"/> Delivery address</span><textarea name="address" rows="3" maxlength="500" autocomplete="street-address">{{ old('address') }}</textarea></label>
                            <label>Delivery instructions (optional)<textarea name="delivery_instructions" rows="2" maxlength="1000">{{ old('delivery_instructions') }}</textarea></label>
                        </div>
                    @endif
                </fieldset>
                <fieldset class="payment-options">
                    <legend><span class="icon-label"><x-icon name="wallet"/> Payment method</span></legend>
                    <label class="payment-choice">
                        <input type="radio" name="payment_method" value="paystack" required @disabled(!$paystackReady) @checked($paystackReady && old('payment_method', 'paystack') === 'paystack')>
                        <span><strong class="icon-label"><x-icon name="payments"/> Pay Online</strong><small>{{ $paystackReady ? 'Pay securely through Paystack.' : 'Online payment is currently unavailable.' }}</small></span>
                    </label>
                    <label class="payment-choice">
                        <input type="radio" name="payment_method" value="bank_transfer" required @disabled(!$bank['enabled']) @checked($bank['enabled'] && old('payment_method', $paystackReady ? 'paystack' : 'bank_transfer') === 'bank_transfer')>
                        <span><strong class="icon-label"><x-icon name="bank"/> Bank Transfer</strong><small>{{ $bank['enabled'] ? 'Transfer directly to the restaurant.' : 'Bank transfer is currently unavailable.' }}</small></span>
                    </label>
                </fieldset>
                @if($bank['enabled'])
                    <div class="bank-instructions" data-bank-instructions hidden>
                        <h3 class="icon-label"><x-icon name="bank"/> Becca’s Table bank details</h3>
                        <x-bank-details :bank="$bank"/>
                        <p>Your order reference and final transfer amount will appear after you place the order.</p>
                    </div>
                @endif
                @if($paystackReady || $bank['enabled'])
                    <button class="button" data-place-order type="submit">Place order</button>
                    <span role="status" data-checkout-status hidden></span>
                @else
                    <p class="notice">Payment is currently unavailable. <a class="text-link" href="{{ $business['whatsapp'] }}">Contact the restaurant on WhatsApp</a>.</p>
                @endif
                <a class="text-link" href="{{ route('cart') }}">Return to cart</a>
            </form>
            <aside class="panel summary" data-summary data-subtotal="{{ $lines->sum('total') }}">
                <h2 class="icon-label"><x-icon name="orders"/> Order summary</h2>
                <ul class="checkout-items">
                    @foreach($lines as $line)
                        <li>
                            <div class="checkout-item">
                                @if($line['meal']->photo)
                                    <img src="{{ $line['meal']->photo->url(480) }}" alt="" width="56" height="56" loading="lazy" decoding="async">
                                @endif
                                <div>
                                    <strong class="icon-label"><x-icon name="meals"/> {{ $line['meal']->name }}</strong>
                                    @if($line['options'])<small>{{ implode(' · ', $line['options']) }}</small>@endif
                                    <small>{{ \App\Models\Setting::money($line['unit_price']) }} each</small>
                                </div>
                                <strong>{{ \App\Models\Setting::money($line['total']) }}</strong>
                            </div>
                            <div class="checkout-item-controls">
                                <form class="filter-form inline" method="POST" action="{{ route('cart.update', $line['id']) }}">
                                    @csrf
                                    <input type="hidden" name="line" value="{{ $line['key'] }}">
                                    <input type="hidden" name="return_to_checkout" value="1">
                                    <label><span class="icon-label"><x-icon name="quantity"/> Quantity</span><input type="number" name="quantity" aria-label="Quantity for {{ $line['meal']->name }}" min="1" max="30" value="{{ $line['quantity'] }}" required></label>
                                    <button class="button small outline" type="submit">Update</button>
                                </form>
                                <form method="POST" action="{{ route('cart.update', $line['id']) }}">
                                    @csrf
                                    <input type="hidden" name="line" value="{{ $line['key'] }}">
                                    <input type="hidden" name="quantity" value="0">
                                    <input type="hidden" name="return_to_checkout" value="1">
                                    <button class="text-link" type="submit" aria-label="Remove {{ $line['meal']->name }}">Remove</button>
                                </form>
                            </div>
                        </li>
                    @endforeach
                </ul>
                <dl class="hours">
                    <div><dt class="icon-label"><x-icon name="orders"/> Subtotal</dt><dd>{{ \App\Models\Setting::money($lines->sum('total')) }}</dd></div>
                    <div><dt class="icon-label"><x-icon name="truck"/> Delivery fee</dt><dd data-fee-display>{{ \App\Models\Setting::money(0) }}</dd></div>
                    <div class="summary-total"><dt class="icon-label"><x-icon name="wallet"/> Total</dt><dd data-total-display>{{ \App\Models\Setting::money($lines->sum('total')) }}</dd></div>
                </dl>
                @if($business['minimum'])<p>Minimum subtotal {{ \App\Models\Setting::money($business['minimum']) }}</p>@endif
            </aside>
        </div>
    </section>
</x-layout>

