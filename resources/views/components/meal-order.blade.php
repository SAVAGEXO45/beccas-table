@props(['meal', 'preview' => false])
@php
    $available = $meal->canOrder();
    $variations = $meal->variations->where('available', true);
    $addons = $meal->addons->where('available', true);
@endphp
<div class="menu-order">
    <div class="meal-actions">
        <p class="menu-price">
            @if($meal->variations->isNotEmpty())<small>From</small>@endif
            <strong>{{ \App\Models\Setting::money($meal->displayPrice()) }}</strong>
        </p>
        @if(!$meal->available || ($meal->variations->isNotEmpty() && $variations->isEmpty()))
            <span class="status-tag">Sold out</span>
        @elseif($available)
            <span class="status-tag">Available</span>
        @endif
    </div>
    @unless($meal->price_confirmed)<p class="menu-price-note">Suggested pricing · pending confirmation</p>@endunless
    @if($available && !$preview)
        <form class="form-stack" action="{{ route('cart.update', $meal) }}" method="POST" data-meal-order data-base-price="{{ $meal->price }}">
            @csrf
            <input type="hidden" name="add" value="1">
            @if($variations->isNotEmpty())
                <label>Size or variation
                    <select name="variation" required>
                        @foreach($variations as $variation)
                            <option value="{{ $variation->id }}" data-price="{{ $variation->price }}">{{ $variation->label() }} · {{ \App\Models\Setting::money($variation->price) }}</option>
                        @endforeach
                    </select>
                </label>
            @endif
            @if($addons->isNotEmpty())
                <details class="meal-extras"><summary>Optional extras</summary>
                    @foreach($addons as $addon)
                        <label class="check"><input type="checkbox" name="addons[]" value="{{ $addon->id }}" data-price="{{ $addon->price }}">{{ $addon->name }} +{{ \App\Models\Setting::money($addon->price) }}</label>
                    @endforeach
                </details>
            @endif
            <div class="meal-actions">
                <label>Quantity<input name="quantity" type="number" value="1" min="1" max="30" required></label>
                <button class="button small outline" aria-label="Add {{ $meal->name }} to cart">Add to cart <span data-meal-total hidden></span></button>
            </div>
        </form>
    @elseif($variations->isNotEmpty() || $addons->isNotEmpty())
        <details class="meal-extras"><summary>Sizes, variations and extras</summary>
            <ul>
                @foreach($variations as $variation)<li>{{ $variation->label() }} · {{ \App\Models\Setting::money($variation->price) }}</li>@endforeach
                @foreach($addons as $addon)<li>{{ $addon->name }} +{{ \App\Models\Setting::money($addon->price) }}</li>@endforeach
            </ul>
        </details>
    @endif
</div>
