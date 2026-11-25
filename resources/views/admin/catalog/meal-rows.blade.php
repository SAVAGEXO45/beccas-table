@if($meals->isNotEmpty())
    <ul class="catalog-rows">
        @foreach($meals as $meal)
            @php($photo = $meal->photo ?? $meal->photos->first())
            <li class="catalog-row">
                <div class="catalog-meal">
                    @if($photo)
                        <img class="catalog-thumbnail" src="{{ $photo->url(480) }}" loading="lazy" decoding="async" width="56" height="56" alt="">
                    @else
                        <span class="catalog-thumbnail catalog-no-photo">No photo</span>
                    @endif
                    <div>
                        <h2>{{ $meal->name }}</h2>
                        <span class="catalog-count">{{ $meal->category->name }}</span>
                    </div>
                </div>

                <div class="catalog-price">
                    <strong>{{ \App\Models\Setting::money($meal->price) }}</strong>
                    @unless($meal->price_confirmed)
                        <small>Suggested price</small>
                    @endunless
                </div>
                <span @class(['catalog-availability', 'catalog-sold-out' => !$meal->available])>{{ $meal->available ? 'Available' : 'Sold out' }}</span>

                <div class="catalog-actions">
                    <a class="button small outline" href="{{ route('admin.catalog.edit', ['meals', $meal->id]) }}" aria-label="Edit {{ $meal->name }}">Edit</a>
                    <a class="button small outline" href="{{ route('admin.menu.meals.photos', $meal) }}" aria-label="Edit photos for {{ $meal->name }}">Edit photos</a>
                    <form method="POST" action="{{ route('admin.catalog.delete', ['meals', $meal->id]) }}" data-confirm="Delete {{ $meal->name }} from the meal catalog?">
                        @csrf
                        @method('DELETE')
                        <button class="button small outline catalog-delete" type="submit" aria-label="Delete {{ $meal->name }}">Delete</button>
                    </form>
                </div>
            </li>
        @endforeach
    </ul>
@else
    <p class="catalog-group-content">No meals on this page.</p>
@endif
@isset($pagination)
    @if($pagination->hasPages())
        <div class="catalog-group-content" data-catalog-pagination>{{ $pagination->links() }}</div>
    @endif
@endisset
