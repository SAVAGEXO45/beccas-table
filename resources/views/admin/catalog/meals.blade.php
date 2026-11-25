<x-layout title="Meal catalog" :admin="true">
    <header class="page-header section-header">
        <h1>Meal catalog</h1>
        <div class="catalog-actions">
            <a class="button small outline" href="{{ route('admin.catalog.index', 'categories') }}">Manage categories</a>
            <a class="button small" href="{{ route('admin.catalog.create', 'meals') }}">Add meal</a>
        </div>
    </header>

    <x-menu-price-notice/>

    <form class="filter-form catalog-filters" method="GET" action="{{ route('admin.catalog.index', 'meals') }}">
        <label>Search meals<input name="q" value="{{ $search }}" type="search" maxlength="100"></label>
        <label>Category
            <select name="category_id">
                <option value="">All categories</option>
                @foreach($categories as $category)
                    <option value="{{ $category->id }}" @selected($categoryId === $category->id)>{{ $category->name }}</option>
                @endforeach
            </select>
        </label>
        <label>Availability
            <select name="availability">
                <option value="">All statuses</option>
                <option value="available" @selected($availability === 'available')>Available</option>
                <option value="sold-out" @selected($availability === 'sold-out')>Sold out</option>
            </select>
        </label>
        <button class="button small">Apply filters</button>
        @if($items)
            <a class="text-link" href="{{ route('admin.catalog.index', 'meals') }}">Clear filters</a>
        @endif
    </form>

    @if($items)
        <p class="catalog-count">{{ $items->total() }} {{ Str::plural('meal', $items->total()) }} found</p>
        @php($groups = $items->getCollection()->groupBy('category_id'))
        <div class="catalog-groups">
            @forelse($categories->whereIn('id', $groups->keys()) as $category)
                @php($group = $groups->get($category->id))
                <details class="catalog-group" open>
                    <summary><span>{{ $category->name }}</span><span class="catalog-count">{{ $group->count() }} {{ Str::plural('meal', $group->count()) }} on this page</span></summary>
                    @include('admin.catalog.meal-rows', ['meals' => $group])
                </details>
            @empty
                <p class="notice">No meals match these filters.</p>
            @endforelse
        </div>
        {{ $items->links() }}
    @else
        <div class="catalog-groups">
            @forelse($categories as $category)
                <details class="catalog-group" name="meal-categories" @if($category->meals_count) data-catalog-category="{{ route('admin.catalog.category-meals', $category) }}" @endif>
                    <summary>
                        <span>{{ $category->name }}</span>
                        <span class="catalog-count">{{ $category->meals_count }} {{ Str::plural('meal', $category->meals_count) }}</span>
                    </summary>
                    @if($category->meals_count)
                        <div class="catalog-group-content" data-catalog-content>
                            <div data-catalog-rows>
                                <a class="text-link" href="{{ route('admin.catalog.index', ['section' => 'meals', 'category_id' => $category->id]) }}">View meals in {{ $category->name }}</a>
                            </div>
                            <p class="catalog-count" data-catalog-status role="status" hidden></p>
                        </div>
                    @else
                        <p class="catalog-group-content">This category has no meals yet.</p>
                    @endif
                </details>
            @empty
                <p class="notice">Create a category before adding meals.</p>
            @endforelse
        </div>
    @endif
</x-layout>
