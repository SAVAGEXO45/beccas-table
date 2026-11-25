@php
    $business = \App\Models\Setting::current();
    $menuRoute = $category?->exists ? 'menu.category' : 'menu';
    $categoryParam = $category?->exists ? ['category' => $category->slug] : [];
    $filters = ['featured' => 'Featured', 'popular' => 'Popular', 'available' => 'Available to order', 'sold-out' => 'Sold out'];
@endphp
<x-layout :title="$category?->name ?: 'Food menu'" :description="$category?->description">
    <section class="menu-hero">
        <div @class(['container menu-hero-inner', 'menu-hero-with-photo' => !$category?->exists || $hero])>
            <div>
                <p class="eyebrow">{{ $business['name'] }}</p>
                <h1>{{ $category?->name ?: 'Explore the menu' }}</h1>
                <p class="menu-tagline">{{ $business['tagline'] }}</p>
                <p>{{ $category?->description ?: 'From smoky Nigerian rice and soups to Continental mains, fresh drinks and something sweet.' }}</p>
            </div>
            @if(!$category?->exists)
                <x-brand-artwork :photo="$brandPhoto" :name="$business['name']" sizes="(max-width: 700px) calc(100vw - 2rem), 45vw"/>
            @elseif($hero)
                <a class="menu-hero-link" href="{{ route('menu.meal', $heroMeal->slug) }}" aria-label="View {{ $heroMeal->name }} and its photos">
                    <x-photo :photo="$hero" :eager="true" :decorative="true" sizes="(max-width: 700px) 100vw, (max-width: 1260px) 45vw, 510px"/>
                </a>
            @endif
        </div>
    </section>
    <section class="container section" aria-label="Browse meals">
        <nav class="filters" aria-label="Food categories">
            <a href="{{ route('menu', ['q' => $search, 'view' => $view]) }}" @if(!$category?->exists) aria-current="page" @endif>All meals</a>
            @foreach($categories as $item)
                <a href="{{ route('menu.category', ['category' => $item->slug, 'q' => $search, 'view' => $view]) }}" @if($category?->id === $item->id) aria-current="page" @endif>{{ $item->name }}</a>
            @endforeach
        </nav>
        <form method="GET" class="filter-form menu-search" action="{{ route($menuRoute, $categoryParam) }}">
            <label>Search meals<input type="search" name="q" maxlength="100" value="{{ $search }}" placeholder="Find a dish"></label>
            <label>Show<select name="view"><option value="">All meals</option>@foreach($filters as $key => $label)<option value="{{ $key }}" @selected($view === $key)>{{ $label }}</option>@endforeach</select></label>
            <button class="button">Apply filters</button>
            @if($search || $view)<a class="text-link" href="{{ route($menuRoute, $categoryParam) }}">Clear filters</a>@endif
        </form>
        @if($meals->isNotEmpty())
            <x-meal-list :meals="$meals"/>
            {{ $meals->links() }}
        @else
            <p class="notice">{{ $search || $view ? 'No meals matched your search.' : 'There are no meals available online right now.' }}</p>
        @endif
    </section>
</x-layout>

