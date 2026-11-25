<x-layout :title="$customer->name" :admin="true">
    <header class="page-header"><p class="eyebrow">Customer</p><h1>{{ $customer->name }}</h1></header>
    <div class="panel form-stack">
        <p class="break-text">{{ $customer->email }}<br>{{ $customer->phone ?: 'No phone on file' }}</p>
        <p>Account {{ $customer->is_disabled ? 'disabled' : 'active' }}</p>
        <form method="POST" action="{{ route('admin.customers.update', $customer) }}">
            @csrf @method('PATCH')
            <input type="hidden" name="disabled" value="{{ $customer->is_disabled ? '0' : '1' }}">
            <button class="button outline">{{ $customer->is_disabled ? 'Enable account' : 'Disable account' }}</button>
        </form>
    </div>
    <section class="staff-section"><h2>Order history</h2><x-order-table :orders="$orders" :staff="true"/>{{ $orders->links() }}</section>
</x-layout>
