<x-layout title="Dashboard" :admin="true">
    <x-menu-price-notice/>
    <header class="page-header"><p class="eyebrow">Restaurant management</p><h1>Overview</h1></header>
    <dl class="stat-grid">
        @foreach($stats as $label => $value)<div class="panel"><dt>{{ $label }}</dt><dd>{{ $value }}</dd></div>@endforeach
    </dl>
    <section class="staff-section"><div class="section-header"><h2>Recent orders</h2><a class="text-link" href="{{ route('admin.orders.index') }}">All orders</a></div><x-order-table :orders="$orders" :staff="true"/></section>
    <section class="staff-section"><div class="section-header"><h2>Recent transactions</h2><a class="text-link" href="{{ route('admin.payments.index') }}">All transactions</a></div><x-payment-table :payments="$payments"/></section>
</x-layout>

