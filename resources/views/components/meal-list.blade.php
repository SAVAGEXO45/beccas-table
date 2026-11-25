@props(['meals', 'preview' => false])
<div class="meal-grid">
    @foreach($meals as $meal)
        <article class="meal-card">
            <x-meal-gallery :meal="$meal"/>
            <div class="meal-copy">
                <span class="eyebrow">{{ $meal->category->name }}</span>
                <h3>
                    @if($preview){{ $meal->name }}
                    @else<a href="{{ route('menu.meal', $meal->slug) }}">{{ $meal->name }}</a>@endif
                </h3>
                @if($meal->featured || $meal->popular)
                    <p class="meal-badges">
                        @if($meal->featured)<span class="status-tag">Featured</span>@endif
                        @if($meal->popular)<span class="status-tag">Popular</span>@endif
                    </p>
                @endif
                <p>{{ $meal->description }}</p>
                <x-meal-order :meal="$meal" :preview="$preview"/>
            </div>
        </article>
    @endforeach
</div>
