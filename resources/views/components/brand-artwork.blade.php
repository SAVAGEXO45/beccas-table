@props(['photo' => null, 'name', 'sizes' => '(max-width: 980px) calc(100vw - 2rem), 45vw'])
<img class="hero-brand-image" src="{{ $photo?->url(960) ?? asset('media/beccas-table-hero-768.webp') }}"
     srcset="{{ $photo?->sourceSet() ?? (asset('media/beccas-table-hero-768.webp').' 768w, '.asset('media/beccas-table-hero-1536.webp').' 1536w') }}"
     sizes="{{ $sizes }}" alt="{{ $name }} logo" width="{{ $photo?->width ?? 1536 }}" height="{{ $photo?->height ?? 1024 }}" fetchpriority="high">
