<label>Payment method
    <select name="method">
        <option value="">All methods</option>
        @foreach(\App\Models\Payment::METHODS as $method => $label)
            <option value="{{ $method }}" @selected(request('method') === $method)>{{ $label }}</option>
        @endforeach
    </select>
</label>
<label>Payment status
    <select name="payment_status">
        <option value="">All payment statuses</option>
        @foreach(\App\Models\Payment::STATUSES as $paymentStatus)
            <option value="{{ $paymentStatus }}" @selected(request('payment_status') === $paymentStatus)>{{ \App\Models\Order::label($paymentStatus) }}</option>
        @endforeach
    </select>
</label>
