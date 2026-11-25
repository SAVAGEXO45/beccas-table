@if($payment->status === 'pending' && !$staff && $bank)
    <div class="bank-instructions">
        <h3 class="icon-label"><x-icon name="bank"/> Becca’s Table bank details</h3>
        <x-bank-details :bank="$bank"/>
        <dl class="hours">
            <div><dt class="icon-label"><x-icon name="wallet"/> Exact amount to transfer</dt><dd><strong>{{ $money($order->total) }}</strong></dd></div>
            <div><dt class="icon-label"><x-icon name="orders"/> Transfer narration</dt><dd><strong>{{ $order->number }}</strong></dd></div>
        </dl>
        <p>Use your order reference as the transfer narration where supported.</p>
        @if($bank['instructions'] !== \App\Models\Setting::defaults()['bank_transfer']['instructions'])<p>{{ $bank['instructions'] }}</p>@endif
        @if($payment->transfer_notified_at)
            <p class="notice icon-label"><x-icon name="clock"/> <span>Transfer reported. The restaurant is checking the bank account before processing your order.</span></p>
        @else
            <p>Transfer the exact amount once, then report it here. The restaurant must confirm receipt before it prepares your order.</p>
            <form method="POST" action="{{ route('orders.transfer.notify', $order) }}" class="print-hide">
                @csrf
                <button class="button" type="submit">I Have Made Payment</button>
            </form>
        @endif
    </div>
@endif

@if($payment->status === 'rejected')
    <p class="notice error icon-label"><x-icon name="warning"/> <span>The transfer could not be verified. Your order is unpaid. {{ $payment->rejection_reason }}</span></p>
@endif

@if($staff)
    @if($payment->transfer_notified_at)
        <p>Customer notified at {{ $payment->transfer_notified_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}.</p>
    @else
        <p>The customer has yet to report a transfer.</p>
    @endif
    @if($payment->reviewed_at)
        <p>Reviewed by {{ $payment->reviewer?->name ?? 'Staff' }} on {{ $payment->reviewed_at->timezone('Africa/Lagos')->format('j M Y, g:i A') }}.</p>
        @if($payment->bank_transaction_reference)<p class="break-text">Bank statement reference {{ $payment->bank_transaction_reference }}</p>@endif
    @endif
    @if($payment->status === 'pending' && $payment->transfer_notified_at && $bank && $order->status === 'pending_payment')
        <p>Verify that {{ $money($order->total) }} arrived in the account ending in {{ substr($bank['account_number'], -4) }} before confirming.</p>
        <div class="form-stack print-hide">
            <form method="POST" action="{{ route('admin.orders.transfer.review', $order) }}" class="form-stack" data-confirm="Confirm receipt of this transfer?">
                @csrf @method('PATCH')
                <input type="hidden" name="decision" value="confirm">
                <label>Bank statement transaction reference<input name="bank_transaction_reference" value="{{ old('bank_transaction_reference') }}" maxlength="100" required></label>
                <label class="check"><input type="checkbox" name="receipt_checked" value="1" required> I matched the incoming transfer on the bank statement.</label>
                <button class="button" type="submit">Confirm Bank Transfer</button>
            </form>
            <form method="POST" action="{{ route('admin.orders.transfer.review', $order) }}" class="form-stack" data-confirm="Reject this transfer report?">
                @csrf @method('PATCH')
                <input type="hidden" name="decision" value="reject">
                <label>Reason for rejection<textarea name="rejection_reason" required maxlength="600" rows="2">{{ old('rejection_reason') }}</textarea></label>
                <button class="button outline" type="submit">Reject Bank Transfer</button>
            </form>
        </div>
    @endif
@endif
