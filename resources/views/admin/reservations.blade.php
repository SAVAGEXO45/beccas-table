<x-layout title="Reservations" :admin="true">
    <header class="page-header"><h1>Reservations</h1></header>
    <form class="filter-form" method="GET">
        <label>Name, code or email<input type="search" name="q" value="{{ $search }}" maxlength="100"></label>
        <label>Date<input type="date" name="date" value="{{ request('date') }}"></label>
        <label>Status<select name="status"><option value="">All statuses</option>@foreach(\App\Models\Reservation::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ ucfirst($status) }}</option>@endforeach</select></label>
        <button class="button">Filter</button>
    </form>
    <div class="form-stack">
        @forelse($reservations as $reservation)
            <article class="panel reservation-row">
                <div><p class="eyebrow">{{ $reservation->code }}</p><h2>{{ $reservation->name }}</h2>
                    <p>{{ \Carbon\CarbonImmutable::parse($reservation->date)->format('j F Y') }} · {{ substr($reservation->time, 0, 5) }} Lagos time · {{ $reservation->guests }} guests</p>
                    <p class="break-text">{{ $reservation->email }} · {{ $reservation->phone }}</p>
                    @if($reservation->requests)<p>{{ $reservation->requests }}</p>@endif
                </div>
                <div><p class="status-tag">{{ ucfirst($reservation->status) }}</p>
                    @if(in_array($reservation->status, ['pending', 'approved'], true))
                    <form class="form-stack" method="POST" action="{{ route('admin.reservations.update', $reservation) }}">
                        @csrf @method('PATCH')
                        <label>Next status<select name="status" required><option value="">Choose</option>@if($reservation->status === 'pending')<option value="approved">Approve</option><option value="rejected">Reject</option>@endif<option value="cancelled">Cancel</option></select></label>
                        <button class="button small">Save status</button>
                    </form>
                    @endif
                </div>
            </article>
        @empty<p class="notice">No reservations match these filters.</p>@endforelse
    </div>
    {{ $reservations->links() }}
</x-layout>
