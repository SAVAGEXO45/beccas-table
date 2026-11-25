<x-layout title="Customers" :admin="true">
    <header class="page-header"><h1>Customers</h1></header>
    <form method="GET" class="filter-form"><label>Name or email<input type="search" name="q" value="{{ $search }}" maxlength="100"></label><button class="button">Search</button></form>
    <div class="table-scroll"><table>
        <thead><tr><th>Customer</th><th>Email</th><th>Orders</th><th>Status</th></tr></thead>
        <tbody>
            @forelse($customers as $customer)
                <tr><th><a class="text-link" href="{{ route('admin.customers.show', $customer) }}">{{ $customer->name }}</a></th><td>{{ $customer->email }}</td><td>{{ $customer->orders_count }}</td><td>{{ $customer->is_disabled ? 'Disabled' : 'Active' }}</td></tr>
            @empty<tr><td colspan="4">No customers to display.</td></tr>@endforelse
        </tbody>
    </table></div>{{ $customers->links() }}
</x-layout>
