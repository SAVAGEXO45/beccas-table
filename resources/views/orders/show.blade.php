@php
    $business = \App\Models\Setting::current();
    $money = fn ($amount) => \App\Models\Setting::money((int) $amount);
    $label = fn ($status) => $order->progressLabel($status);
    $payment = $order->payment;
    $whatsapp = $business['whatsapp'].(str_contains($business['whatsapp'], '?') ? '&' : '?').'text='.rawurlencode("Hello, I have a question about my order {$order->number}.");
@endphp
<x-dynamic-component :component="$staff ? 'layout' : 'account-layout'" :title="'Order '.$order->number" :admin="$staff">
    <section class="order-detail {{ $staff ? 'staff-section' : '' }}">
        <header class="page-header">
            <p class="eyebrow icon-label"><x-icon name="orders"/> {{ $order->paid_at ? 'Payment receipt' : 'Order details' }}</p>
            <h1 class="break-text">{{ $order->number }}</h1>
            <div class="order-detail-meta">
                <x-status-indicator :status="$order->status" :label="$label($order->status)"/>
                <span class="icon-label"><x-icon name="reservations"/> <time datetime="{{ $order->created_at->toAtomString() }}">{{ $order->created_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}</time></span>
            </div>
        </header>
        <div class="checkout-grid">
            <div class="form-stack">
                <section class="panel">
                    <h2 class="icon-label"><x-icon name="orders"/> Order summary</h2>
                    <dl class="hours">
                        @foreach($order->items as $item)
                            <div>
                                <dt>
                                    <strong class="icon-label"><x-icon name="meals"/> {{ $item->name }}</strong>
                                    @if($item->options)<small>{{ implode(' · ', $item->options) }}</small>@endif
                                    <div class="order-line-meta">
                                        <span class="icon-label"><x-icon name="quantity"/> Qty {{ $item->quantity }}</span>
                                        <span>{{ $money($item->unit_price) }} each</span>
                                    </div>
                                </dt>
                                <dd>{{ $money($item->unit_price * $item->quantity) }}</dd>
                            </div>
                        @endforeach
                        <div><dt class="icon-label"><x-icon name="orders"/> Subtotal</dt><dd>{{ $money($order->subtotal) }}</dd></div>
                        <div><dt class="icon-label"><x-icon name="truck"/> Delivery fee</dt><dd>{{ $money($order->delivery_fee) }}</dd></div>
                        <div class="summary-total"><dt class="icon-label"><x-icon name="wallet"/> Total ({{ $order->currency }})</dt><dd>{{ $money($order->total) }}</dd></div>
                    </dl>
                    <h3>{{ ucfirst($order->fulfilment) }}</h3>
                    <p>{{ $order->name }} · {{ $order->phone }}<br>{{ $order->email }}</p>
                    @if($order->fulfilment === 'delivery')
                        <p class="icon-label"><x-icon name="pin"/> Delivery address</p><address>{{ $order->address }}<br>{{ $order->delivery_area }}</address>
                        @if($order->delivery_instructions)<p>Delivery instructions: {{ $order->delivery_instructions }}</p>@endif
                    @else<p class="icon-label"><x-icon name="pin"/> <span>{{ $business['pickup'] }}<br>{{ $business['address'] }}</span></p>@endif
                </section>
                @if($payment)
                    <section class="panel">
                        <h2 class="icon-label"><x-icon name="wallet"/> Payment</h2>
                        <div class="order-detail-meta">
                            <strong class="icon-label"><x-icon :name="$payment->method === 'bank_transfer' ? 'bank' : 'payments'"/> {{ $payment->methodLabel() }}</strong>
                            <x-status-indicator :status="$payment->status" :label="$payment->statusLabel()" type="payment"/>
                        </div>
                        <p class="icon-label break-text"><x-icon name="orders"/> <span>Payment reference {{ $payment->reference }}</span></p>
                        @if($staff && $payment->provider_id)<p>Paystack transaction {{ $payment->provider_id }}</p>@endif
                        @if($payment->channel)<p>Channel {{ ucfirst($payment->channel) }}</p>@endif
                        @if($order->paid_at)<p class="icon-label"><x-icon name="shield-check"/> Paid {{ $order->paid_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}</p>@endif
                        @if($payment->method === 'bank_transfer')
                            @include('orders.bank-transfer', ['bank' => $payment->bank_details])
                        @elseif(!$order->paid_at)
                            @if(in_array($payment->status, ['failed', 'abandoned', 'reversed', 'mismatch'], true))
                                <p>We could not confirm payment. Your order is still unpaid.</p>
                            @else
                                <p>Payment is awaiting verification.</p>
                            @endif
                        @endif
                        <div class="actions print-hide">
                        @if(!$order->paid_at && $payment->method === 'paystack')
                            @if(!$staff && in_array($payment->status, ['created', 'pending'], true))
                                <form method="POST" action="{{ route('orders.pay', $order) }}">@csrf<button class="button">Continue to payment</button></form>
                            @endif
                            <form method="POST" action="{{ route($staff ? 'admin.orders.refresh' : 'orders.refresh', $order) }}">@csrf<button class="button outline">Check payment</button></form>
                            @if(!$staff && in_array($payment->status, ['failed', 'abandoned', 'reversed'], true))<a href="{{ route('menu') }}" class="text-link">Start a new order</a>@endif
                        @elseif($order->paid_at)
                            <button class="button outline" type="button" data-print><span class="icon-label"><x-icon name="orders"/> Print receipt</span></button>
                        @endif
                        </div>
                    </section>
                @endif
            </div>
            <aside class="panel summary">
                <h2 class="icon-label"><x-icon name="history"/> Order progress</h2>
                <ol class="timeline">
                @foreach($order->updates->sortBy('id') as $update)<li><x-status-indicator :status="$update->status" :label="$label($update->status)"/><br><small>{{ $update->created_at->timezone('Africa/Lagos')->format('j M, g:i A') }}</small></li>@endforeach
                </ol>
                @if($staff && $order->transitions())
                    <form method="POST" action="{{ route('admin.orders.update', $order) }}" class="form-stack print-hide">
                        @csrf @method('PATCH')
                        <label>Next status<select name="status"><option value="">Choose a status</option>@foreach($order->transitions() as $next)<option value="{{ $next }}">{{ $label($next) }}</option>@endforeach</select></label>
                        @if(in_array('cancelled', $order->transitions(), true))<small>Any refund must be handled separately through the original payment method.</small>@endif
                        <button class="button">Update order</button>
                    </form>
                @elseif(!$staff)
                    <a class="text-link print-hide" href="{{ $whatsapp }}">Ask about this order on WhatsApp</a>
                @endif
            </aside>
        </div>
    </section>
</x-dynamic-component>

