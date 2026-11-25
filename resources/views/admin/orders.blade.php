<x-layout title="Orders" :admin="true">
    <header class="page-header"><h1>Orders</h1></header>
    <form class="filter-form" method="GET">
        <label>Order or customer<input type="search" name="q" value="{{ $search }}" maxlength="100"></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Order::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ \App\Models\Order::label($status) }}</option>@endforeach</select></label>
        <x-admin.payment-filters/>
        <button class="button">Filter orders</button>
    </form>
    <x-order-table :orders="$orders" :staff="true"/>{{ $orders->links() }}
</x-layout>
