@php($gallery = $meal->gallery())
<x-layout :title="'Edit photos for '.$meal->name" :admin="true">
    <header class="page-header">
        <a class="text-link" href="{{ route('admin.catalog.edit', ['meals', $meal->id]) }}">Back to meal</a>
        <h1>Edit meal photos</h1>
        <p>{{ $meal->name }} · {{ $gallery->count() }} of 12 photos</p>
    </header>

    @if($gallery->isNotEmpty())
        <section class="form-stack" aria-labelledby="meal-photo-heading">
            <h2 id="meal-photo-heading">Current photos</h2>
            <p>Choose a replacement file and save it on the card for the photo you want to change. Use JPEG, PNG or WebP up to 6 MB and 5,000 px per side.</p>
            <div class="meal-grid">
                @foreach($gallery as $photo)
                    <article class="panel form-stack">
                        <h3>Photo {{ $loop->iteration }} @if($loop->first) · Primary @endif</h3>
                        <x-photo :photo="$photo"/>
                        <form class="form-stack" method="POST" enctype="multipart/form-data" action="{{ route('admin.menu.meals.replace-photo', [$meal, $photo]) }}">
                            @csrf @method('PATCH')
                            <label>Replace photo {{ $loop->iteration }}<input name="image" type="file" accept="image/jpeg,image/png,image/webp" data-menu-upload></label>
                            <div class="menu-upload-previews" data-menu-previews></div>
                            <label>Image description for screen readers<input name="alt" value="{{ $photo->alt }}" maxlength="200" required></label>
                            <label class="check"><input type="checkbox" name="illustrative" value="1" @checked($photo->illustrative)> Illustrative stock photograph</label>
                            <button class="button" type="submit">Save photo {{ $loop->iteration }}</button>
                        </form>
                        <form method="POST" data-confirm="Remove this photograph from this meal?" action="{{ route('admin.menu.meals.detach-photo', [$meal, $photo]) }}">
                            @csrf @method('DELETE')
                            <button class="button small outline" type="submit">Remove photo {{ $loop->iteration }}</button>
                        </form>
                    </article>
                @endforeach
            </div>
        </section>

        <form class="panel form-stack detail" method="POST" id="gallery-order" action="{{ route('admin.menu.meals.photo-order', $meal) }}">
            @csrf @method('PUT')
            <h2>Display order</h2>
            <p>Choose a primary image and set the order for the remaining photos. Lower numbers appear first.</p>
            <div class="meal-grid">
                @foreach($gallery as $photo)
                    <fieldset>
                        <legend>Photo {{ $loop->iteration }}</legend>
                        <label class="check"><input type="radio" name="primary" value="{{ $photo->id }}" @checked((int) old('primary', $gallery->first()->id) === $photo->id)> Primary image</label>
                        <label>Position<input type="number" min="0" max="65535" name="positions[{{ $photo->id }}]" value="{{ old('positions.'.$photo->id, $loop->iteration) }}" required></label>
                    </fieldset>
                @endforeach
            </div>
            <button class="button">Save photo order</button>
        </form>
    @endif

    <form class="panel form-stack detail" method="POST" enctype="multipart/form-data" action="{{ route('admin.menu.meals.upload', $meal) }}">
        @csrf
        <h2>Add photos</h2>
        <label>Upload photographs <input type="file" name="images[]" accept="image/jpeg,image/png,image/webp" multiple required data-menu-upload></label>
        <p>Choose up to eight JPEG, PNG or WebP files at a time. Each file can be up to 6 MB and 5,000 px per side.</p>
        <div class="menu-upload-previews" data-menu-previews></div>
        <label class="check"><input type="checkbox" name="illustrative" value="1"> These are illustrative stock photographs</label>
        <button class="button">Upload photographs</button>
    </form>
</x-layout>
