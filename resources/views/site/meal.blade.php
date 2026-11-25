<x-layout :title="$meal->name" :description="$meal->description" :image="$meal->gallery()->first()?->url()">
    <section class="container section">
        <a class="text-link" href="{{ route('menu.category', $meal->category->slug) }}">{{ $meal->category->name }}</a>
        <div class="split detail">
            <x-meal-gallery :meal="$meal" :eager="true"/>
            <div class="detail-meal-copy">
                <h1>{{ $meal->name }}</h1>
                <p>{{ $meal->description }}</p>
                @if($meal->preparation)<p>{{ $meal->preparation }}</p>@endif
                <x-meal-order :meal="$meal"/>
            </div>
        </div>
    </section>
</x-layout>
