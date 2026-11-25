@props(['payments'])
<div class="table-scroll"><table>
    <thead><tr><th>Payment reference</th><th>Order</th><th>Method</th><th>Status</th><th>Amount</th><th>Transaction reference</th><th>Reported</th><th>Verified</th><th>Created</th></tr></thead>
    <tbody>
        @forelse($payments as $payment)
            <tr>
                <th class="break-text">{{ $payment->reference }}</th>
                <td><a class="text-link" href="{{ route('admin.orders.show', $payment->order) }}">{{ $payment->order->number }}</a></td>
                <td>{{ $payment->methodLabel() }}</td>
                <td>{{ $payment->statusLabel() }}@if($payment->status === 'pending' && $payment->transfer_notified_at)<br><a href="{{ route('admin.orders.show', $payment->order) }}" class="text-link">Review transfer</a>@endif</td>
                <td>{{ \App\Models\Setting::money($payment->order->total) }}</td>
                <td class="break-text">{{ $payment->provider_id ?? $payment->bank_transaction_reference ?? '—' }}</td>
                <td>{{ $payment->transfer_notified_at?->timezone('Africa/Lagos')->format('j M Y, g:i A') ?? '—' }}</td>
                <td>{{ $payment->verified_at?->timezone('Africa/Lagos')->format('j M Y, g:i A') ?? '—' }}</td>
                <td>{{ $payment->created_at?->timezone('Africa/Lagos')->format('j M Y, g:i A') ?? '—' }}</td>
            </tr>
        @empty
            <tr><td colspan="9">No transactions to display.</td></tr>
        @endforelse
    </tbody>
</table></div>
