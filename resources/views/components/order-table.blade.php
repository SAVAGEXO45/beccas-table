@props(['orders', 'staff' => false])
<div class="table-scroll">
<table>
    <thead><tr><th>Order</th>@if($staff)<th>Customer</th><th>Payment</th>@endif<th>Placed</th><th>Status</th><th>Total</th></tr></thead>
    <tbody>
        @forelse($orders as $order)
            <tr><th><a class="text-link" href="{{ route($staff ? 'admin.orders.show' : 'orders.show', $order) }}">{{ $order->number }}</a></th>
            @if($staff)<td>{{ $order->name }}</td><td>{{ $order->payment?->methodLabel() }} · {{ $order->payment?->statusLabel() }}</td>@endif
            <td>{{ $order->created_at->timezone('Africa/Lagos')->format('j M Y') }}</td>
            <td>{{ $order->progressLabel($order->status) }}</td><td>{{ \App\Models\Setting::money($order->total) }}</td></tr>
        @empty
            <tr><td colspan="{{ $staff ? 6 : 4 }}">No orders to display.</td></tr>
        @endforelse
    </tbody>
</table>
</div>
