@php
    $noun = ['meals' => 'meal', 'categories' => 'category', 'photos' => 'photo', 'testimonials' => 'testimonial'][$section];
    $title = ($item->exists ? 'Edit ' : 'Add ').$noun;
@endphp
<x-layout :title="$title" :admin="true">
    <header class="page-header"><a class="text-link" href="{{ route('admin.catalog.index', $section) }}">All {{ $section }}</a><h1>{{ $title }}</h1></header>
    @if($section === 'meals' && $item->exists)
        @unless($item->price_confirmed)<x-menu-price-notice/>@endunless
        <nav class="filters" aria-label="Meal management">
            <a href="{{ route('admin.menu.meals.options', $item) }}">Variations and extras</a>
            <a href="{{ route('admin.menu.meals.photos', $item) }}">Edit photos</a>
            <a href="{{ route('admin.menu.meals.preview', $item) }}">Preview customer card</a>
        </nav>
    @endif
    <form class="panel form-stack" method="POST" enctype="multipart/form-data" action="{{ $item->exists ? route('admin.catalog.update', [$section, $item->id]) : route('admin.catalog.store', $section) }}">
        @csrf @if($item->exists) @method('PATCH') @endif
        @if($section === 'photos')
            @if($item->exists)<x-photo :photo="$item"/>@endif
            <label>{{ $item->exists ? 'Replace photograph (optional)' : 'Photograph' }}<input type="file" name="image" accept="image/jpeg,image/png,image/webp" data-photo-input @required(!$item->exists)></label>
            <img data-photo-preview class="photo-preview" alt="Selected photo preview" hidden>
            <small>JPEG, PNG or WebP. Up to 6,000 KB and 5,000 pixels per side.</small>
            <x-field name="alt" label="Alt text" :value="$item->alt" required maxlength="200"/>
            <input type="hidden" name="illustrative" value="0">
            <label class="check"><input type="checkbox" name="illustrative" value="1" @checked(old('illustrative', $item->illustrative))> Illustrative stock photograph</label>
            <input type="hidden" name="show_in_gallery" value="0">
            <label class="check"><input type="checkbox" name="show_in_gallery" value="1" @checked(old('show_in_gallery', $item->show_in_gallery))> Display in gallery</label>
        @else
            <x-field name="name" :label="$section === 'testimonials' ? 'Guest name' : 'Name'" :value="$item->name" required :maxlength="$section === 'testimonials' ? 100 : 150"/>
            @if($section === 'testimonials')
                <x-field name="quote" label="Guest quote" :value="$item->quote" rows="5" required maxlength="700"/>
                <input type="hidden" name="published" value="0">
                <label class="check"><input type="checkbox" name="published" value="1" @checked(old('published', $item->published))> Publish testimonial</label>
            @else
                <x-field name="slug" label="Custom URL slug (optional)" :value="$item->slug" maxlength="150"/>
                <x-field name="description" label="Description" :value="$item->description" rows="5" :required="$section === 'meals'" :maxlength="$section === 'meals' ? 2000 : 500"/>
            @endif
        @endif
        @if(in_array($section, ['meals', 'categories'], true))
            <x-field name="position" label="Display position" type="number" :value="$item->position ?? 0" min="0" max="65535" required/>
        @endif
        @if($section === 'categories')
            <input type="hidden" name="active" value="0">
            <label class="check"><input type="checkbox" name="active" value="1" @checked(old('active', $item->active))> Show category on the menu</label>
        @endif
        @if($section === 'meals')
            <div class="form-row">
                <label>Category<select name="category_id" required><option value="">Select category</option>@foreach($categories as $category)<option value="{{ $category->id }}" @selected(old('category_id', $item->category_id) == $category->id)>{{ $category->name }}</option>@endforeach</select></label>
                <x-field name="price" label="Price (NGN)" type="number" :value="$item->exists ? number_format($item->price / 100, 2, '.', '') : null" min="1" max="9999999.99" step="0.01" required/>
            </div>
            @if($categories->isEmpty())<p><a class="text-link" href="{{ route('admin.catalog.create', 'categories') }}">Add a category</a> before saving this meal.</p>@endif
            <x-field name="preparation" label="Preparation information (optional)" :value="$item->preparation" rows="3" maxlength="500"/>
            <label>Main photograph<select name="photo_id"><option value="">No photograph</option>@foreach($photos as $photo)<option value="{{ $photo->id }}" @selected(old('photo_id', $item->photo_id) == $photo->id)>{{ $photo->alt }}</option>@endforeach</select></label>
            @if($photos->isNotEmpty())
                <fieldset><legend>Additional photographs</legend>
                    <input type="hidden" name="photos_present" value="1">
                    @foreach($photos as $photo)<label class="check"><input type="checkbox" name="photos[]" value="{{ $photo->id }}" @checked(in_array($photo->id, old('photos', old('photos_present') ? [] : $selected)))> {{ $photo->alt }}</label>@endforeach
                </fieldset>
            @endif
            <a class="text-link" href="{{ route('admin.catalog.index', 'photos') }}">Open photo library</a>
            @foreach(['available' => 'Available to order', 'featured' => 'Feature on the menu and homepage', 'popular' => 'Mark as popular', 'price_confirmed' => 'Prices confirmed for public ordering'] as $field => $label)
                <input type="hidden" name="{{ $field }}" value="0">
                <label class="check"><input type="checkbox" name="{{ $field }}" value="1" @checked(old($field, $field === 'price_confirmed' ? ($item->exists && $item->price_confirmed) : $item->$field))> {{ $label }}</label>
            @endforeach
        @endif
        <button class="button">Save {{ $noun }}</button>
    </form>
    @if($categoryHasMeals)
        <p class="notice">Move this category’s meals to another category before deleting it.</p>
    @elseif($item->exists)
        <form class="actions" method="POST" action="{{ route('admin.catalog.delete', [$section, $item->id]) }}" data-confirm="Delete this {{ $noun }}?">
            @csrf @method('DELETE')<button class="button outline">Delete {{ $noun }}</button>
        </form>
    @endif
</x-layout>


