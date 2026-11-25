<x-layout title="Gallery">
    <section class="container section">
        <header class="page-header">
            <h1>Gallery</h1>
            <p>Explore our food gallery, then browse the menu to see each dish’s full photo set.</p>
            <a class="text-link" href="{{ route('menu') }}">View the menu</a>
        </header>
        @if($gallery->isNotEmpty())
            <div class="meal-grid">@foreach($gallery as $photo)<x-photo :photo="$photo"/>@endforeach</div>
            {{ $gallery->links() }}
        @else
            <p class="notice">Restaurant and food photographs will appear here when available.</p>
        @endif
    </section>
</x-layout>
