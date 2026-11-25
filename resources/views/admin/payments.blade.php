<x-layout title="Payments" :admin="true">
    <header class="page-header section-header">
        <h1>Payments</h1>
        <a class="button small outline" href="{{ route('admin.settings.bank') }}">Bank transfer settings</a>
    </header>
    <form method="GET" class="filter-form">
        <label>Payment or order reference<input type="search" name="q" value="{{ $search }}" maxlength="100"></label>
        <x-admin.payment-filters/>
        <label class="check"><input type="checkbox" name="needs_review" value="1" @checked(request()->boolean('needs_review'))> Transfers awaiting review</label>
        <button class="button">Filter payments</button>
    </form>
    <x-payment-table :payments="$payments"/>{{ $payments->links() }}
</x-layout>
