@props(['meal', 'eager' => false])
@php($gallery = $meal->gallery())
@if($gallery->isEmpty())
    <div class="photo photo-placeholder menu-photo-placeholder"><span>Photograph coming soon</span></div>
@else
    <div class="menu-gallery" data-gallery aria-label="Photographs of {{ $meal->name }}">
        <div class="menu-gallery-stage" data-gallery-stage>
            <button class="menu-gallery-open" type="button" data-gallery-open aria-label="Open larger photographs of {{ $meal->name }}">
                <x-photo :photo="$gallery->first()" :eager="$eager"/>
            </button>
            @if($gallery->count() > 1)
                <button type="button" class="menu-gallery-arrow previous" data-gallery-step="-1" aria-label="Previous photograph">‹</button>
                <button type="button" class="menu-gallery-arrow next" data-gallery-step="1" aria-label="Next photograph">›</button>
            @endif
        </div>
        @if($gallery->count() > 1)
            <div class="menu-thumbnails" aria-label="Choose photograph">
                @foreach($gallery as $photo)
                    <button type="button" data-gallery-thumb aria-label="Show photograph {{ $loop->iteration }}" aria-pressed="{{ $loop->first ? 'true' : 'false' }}">
                        <img src="{{ $photo->url(480) }}" alt="" loading="lazy" decoding="async" width="48" height="44">
                    </button>
                @endforeach
            </div>
        @endif
        <template data-gallery-photos>
            @foreach($gallery as $photo)
                <span data-src="{{ $photo->url(960) }}" data-full="{{ $photo->url() }}" data-srcset="{{ $photo->sourceSet() }}" data-description="{{ $photo->alt }}"></span>
            @endforeach
        </template>
        <span class="sr-only" data-gallery-status aria-live="polite"></span>
    </div>
@endif




