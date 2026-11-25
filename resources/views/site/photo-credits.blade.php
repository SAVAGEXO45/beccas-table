<x-layout title="Image licenses">
    <section class="container section narrow">
        <header class="page-header">
            <h1>Image licenses</h1>
            <p>Third-party photographs are used under the licenses listed here. Preview copies have been resized and converted to WebP.</p>
        </header>
        <ul class="form-stack">
            @foreach($credits as $photo)
                <li class="panel">
                    <a class="text-link" href="{{ $photo['url'] }}">{{ $photo['title'] }}</a>
                    <span> by {{ $photo['author'] }} · </span>
                    <a class="text-link" href="{{ $photo['licenseUrl'] }}">{{ $photo['license'] }}</a>
                </li>
            @endforeach
        </ul>
        <p><a class="text-link" href="{{ route('menu') }}">Return to the menu</a></p>
    </section>
</x-layout>
