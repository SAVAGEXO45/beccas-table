<x-account-layout title="My orders">
    <header class="page-header">
        <p class="eyebrow">Welcome, {{ auth()->user()->name }}</p>
        <h1 class="icon-label"><x-icon name="history"/> Order history</h1>
    </header>
    <ul class="order-history">
        @forelse($orders as $order)
            @php($payment = $order->payment)
            <li class="panel order-history-card">
                <div class="order-history-heading">
                    <div>
                        <h2 class="icon-label break-text"><x-icon name="orders"/> {{ $order->number }}</h2>
                        <p class="icon-label order-date"><x-icon name="reservations"/> <time datetime="{{ $order->created_at->toAtomString() }}">{{ $order->created_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}</time></p>
                    </div>
                    <a class="button small outline icon-label" href="{{ route('orders.show', $order) }}" aria-label="View order details for {{ $order->number }}">
                        View order details <x-icon name="arrow-right"/>
                    </a>
                </div>
                <dl class="order-history-facts">
                    <div><dt class="icon-label"><x-icon name="meals"/> Meals</dt><dd>{{ (int) $order->meal_quantity }}</dd></div>
                    <div><dt class="icon-label"><x-icon name="wallet"/> Order total</dt><dd>{{ \App\Models\Setting::money($order->total) }}</dd></div>
                    <div><dt class="icon-label"><x-icon :name="$payment?->method === 'bank_transfer' ? 'bank' : 'payments'"/> Payment method</dt><dd>{{ $payment?->methodLabel() ?? 'Unavailable' }}</dd></div>
                    <div><dt>Payment status</dt><dd><x-status-indicator :status="$payment?->status" :label="$payment?->statusLabel() ?? 'Unavailable'" type="payment"/></dd></div>
                    <div><dt>Order status</dt><dd><x-status-indicator :status="$order->status" :label="$order->progressLabel($order->status)"/></dd></div>
                </dl>
            </li>
        @empty
            <li class="panel">
                <p>You haven’t placed any orders yet.</p>
                <a class="text-link icon-label" href="{{ route('menu') }}">Explore the menu <x-icon name="arrow-right"/></a>
            </li>
        @endforelse
    </ul>
    {{ $orders->links() }}
</x-account-layout>