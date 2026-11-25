@php($title = ucfirst($section))
<x-layout :title="$title" :admin="true">
    <header class="page-header section-header"><h1>{{ $title }}</h1><a class="button" href="{{ route('admin.catalog.create', $section) }}">{{ ['meals' => 'Add meal', 'categories' => 'Add category', 'photos' => 'Upload photo', 'testimonials' => 'Add testimonial'][$section] }}</a></header>
    @if($section === 'photos')<p>Upload approved originals or licensed photography. Assign images to meals or select them in website settings.</p>@endif
    <div class="{{ $section === 'photos' ? 'meal-grid' : 'form-stack' }}">
        @forelse($items as $item)
            <article class="panel {{ $section === 'photos' ? 'form-stack' : 'reservation-row' }}">
                <div>
                    @if($section === 'photos')<x-photo :photo="$item"/><h2>{{ $item->alt }}</h2><p>{{ $item->show_in_gallery ? 'Visible in gallery' : 'Photo library' }} @if($item->illustrative) · Illustrative stock image @endif</p>
                    @else
                        <h2>{{ $item->name }}</h2>
                        @if($section === 'categories')<p>{{ $item->active ? 'Active' : 'Inactive' }} · {{ $item->meals_count }} meals · Position {{ $item->position }}</p>@endif @if($section === 'categories' && $item->description)<p>{{ $item->description }}</p>@endif
                        @if($section === 'testimonials')<p>{{ \Illuminate\Support\Str::limit($item->quote, 90) }} · {{ $item->published ? 'Published' : 'Private' }}</p>@endif
                    @endif
                </div>
                <div class="actions">
                    <a class="button small outline" href="{{ route('admin.catalog.edit', [$section, $item->id]) }}">Edit</a>
                    @if($section === 'categories' && $item->meals_count)
                        <a class="button small outline" href="{{ route('admin.catalog.index', ['section' => 'meals', 'category_id' => $item->id]) }}">View meals</a>
                    @endif
                </div>
            </article>
        @empty<p class="notice">No entries yet.</p>@endforelse
    </div>
    {{ $items->links() }}
</x-layout>


