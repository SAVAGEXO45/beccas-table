@props(['photo' => null, 'eager' => false, 'decorative' => false, 'sizes' => '(max-width: 700px) 100vw, (max-width: 980px) 50vw, 400px', 'placeholder' => 'Photograph coming soon'])
@if ($photo)
    <figure {{ $attributes->class(['photo']) }}>
        <img src="{{ $photo->url(960) }}" srcset="{{ $photo->sourceSet() }}"
             sizes="{{ $sizes }}" alt="{{ $decorative ? '' : $photo->alt }}" loading="{{ $eager ? 'eager' : 'lazy' }}" decoding="async" @if($eager) fetchpriority="high" @endif>
    </figure>
@elseif ($placeholder)
    <div {{ $attributes->class(['photo', 'photo-placeholder']) }}><span>{{ $placeholder }}</span></div>
@endif


