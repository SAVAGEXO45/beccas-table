<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Category, Meal, Photo, Setting, Testimonial};
use App\Services\PhotoStorage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\ForeignKeyConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;

class CatalogController extends Controller
{
    private const MODELS = [
        'meals' => Meal::class,
        'categories' => Category::class,
        'photos' => Photo::class,
        'testimonials' => Testimonial::class,
    ];

    private function model(string $section, ?int $id = null): Model
    {
        abort_unless(isset(self::MODELS[$section]), 404);
        $class = self::MODELS[$section];

        return $id === null ? new $class : $class::findOrFail($id);
    }

    public function index(Request $request, string $section)
    {
        if ($section === 'meals') {
            return $this->meals($request);
        }

        $query = $this->model($section)->newQuery();
        if ($section === 'categories') {
            $query->withCount('meals')->orderBy('position')->orderBy('name');
        } else {
            $query->latest();
        }

        return view('admin.catalog.index', [
            'section' => $section,
            'items' => $query->paginate(20)->withQueryString(),
        ]);
    }

    private function meals(Request $request)
    {
        $filters = $request->validate([
            'q' => 'nullable|string|max:100',
            'category_id' => 'nullable|integer|exists:categories,id',
            'availability' => 'nullable|in:available,sold-out',
        ]);
        $search = trim($filters['q'] ?? '');
        $categoryId = (int) ($filters['category_id'] ?? 0);
        $availability = $filters['availability'] ?? null;
        $filtered = $search !== '' || $categoryId || $availability;
        $categories = Category::withCount('meals')->orderBy('position')->orderBy('name')->get();

        $items = $filtered
            ? $this->listedMeals()
                ->when($search !== '', fn ($query) => $query->whereLike('name', '%'.$search.'%'))
                ->when($categoryId, fn ($query) => $query->where('category_id', $categoryId))
                ->when($availability, fn ($query) => $query->where('available', $availability === 'available'))
                ->paginate(12)->withQueryString()
            : null;

        return view('admin.catalog.meals', compact('categories', 'items', 'search', 'categoryId', 'availability'));
    }

    public function categoryMeals(Category $category)
    {
        $items = $this->listedMeals()->whereBelongsTo($category)->paginate(12);
        $items->withPath(route('admin.catalog.index', 'meals'))->appends(['category_id' => $category->id]);

        return view('admin.catalog.meal-rows', ['meals' => $items, 'pagination' => $items]);
    }

    private function listedMeals(): Builder
    {
        return Meal::with(['category', 'photo', 'photos'])->orderBy('position')->orderBy('name')->orderBy('id');
    }

    public function edit(string $section, ?int $id = null)
    {
        $item = $this->model($section, $id);

        return view('admin.catalog.form', [
            'section' => $section, 'item' => $item,
            'categoryHasMeals' => $section === 'categories' && $item->exists && $item->meals()->exists(),
            'categories' => $section === 'meals' ? Category::orderBy('position')->orderBy('name')->get() : collect(),
            'photos' => $section === 'meals' ? Photo::orderBy('alt')->get() : collect(),
            'selected' => $section === 'meals' && $item->exists ? $item->photos()->pluck('photos.id')->all() : [],
        ]);
    }

    public function save(Request $request, PhotoStorage $storage, string $section, ?int $id = null)
    {
        $item = $this->model($section, $id);
        $rules = match ($section) {
            'meals' => [
                'name' => 'required|string|max:150',
                'slug' => ['nullable', 'alpha_dash:ascii', 'max:150', Rule::unique('meals')->ignore($item)],
                'category_id' => 'required|integer|exists:categories,id',
                'photo_id' => 'nullable|integer|exists:photos,id',
                'photos' => 'nullable|array|max:12',
                'photos.*' => 'integer|distinct|exists:photos,id',
                'description' => 'required|string|max:2000',
                'preparation' => 'nullable|string|max:500',
                'price' => ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D', 'numeric', 'min:1'],
                'available' => 'nullable|boolean', 'featured' => 'nullable|boolean', 'popular' => 'sometimes|boolean', 'price_confirmed' => 'sometimes|boolean', 'position' => 'sometimes|integer|min:0|max:65535',
            ],
            'categories' => [
                'name' => 'required|string|max:150',
                'slug' => ['nullable', 'alpha_dash:ascii', 'max:150', Rule::unique('categories')->ignore($item)],
                'description' => 'nullable|string|max:500', 'active' => 'sometimes|boolean', 'position' => 'sometimes|integer|min:0|max:65535',
            ],
            'photos' => [
                'image' => [$id === null ? 'required' : 'nullable', 'file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:6000', 'dimensions:max_width=5000,max_height=5000'],
                'alt' => 'required|string|max:200',
                'show_in_gallery' => 'nullable|boolean', 'illustrative' => 'sometimes|boolean',
            ],
            'testimonials' => ['name' => 'required|string|max:100', 'quote' => 'required|string|max:700', 'published' => 'nullable|boolean'],
        };
        $data = $request->validate($rules);
        if (in_array($section, ['meals', 'categories'], true)) {
            $data['slug'] = Str::lower($data['slug'] ?? Str::slug($data['name']));
            if ($data['slug'] === '' || $item->newQuery()->where('slug', $data['slug'])->when($item->exists, fn ($q) => $q->whereKeyNot($item->id))->exists()) {
                throw ValidationException::withMessages(['slug' => 'Enter a unique URL slug.']);
            }
        }
        foreach (['available', 'featured', 'show_in_gallery', 'published', 'popular', 'price_confirmed', 'active', 'illustrative'] as $checkbox) {
            if (isset($rules[$checkbox]) && (!str_contains($rules[$checkbox], 'sometimes') || $request->has($checkbox))) {
                $data[$checkbox] = $request->boolean($checkbox);
            }
        }
        $photoIds = $request->has('photos') || $request->has('photos_present') ? ($data['photos'] ?? []) : null;
        $photo = $section === 'photos' && $request->hasFile('image') ? $storage->store($request->file('image')) : [];
        $newPath = $photo['path'] ?? null;
        $data = array_replace($data, $photo);
        if ($section === 'meals') {
            $data['price'] = Setting::minor($data['price']);
            if (!$item->exists) {
                $data['price_confirmed'] ??= false;
            }
            if (collect([$data['photo_id'] ?? null, ...($photoIds ?? [])])->filter()->unique()->count() > 12) {
                throw ValidationException::withMessages(['photos' => 'A meal can have up to 12 photographs including its primary image.']);
            }
        }
        unset($data['image'], $data['photos']);

        try {
            $oldPath = DB::transaction(function () use ($item, $id, $data, $section, $photoIds) {
                $record = $id === null ? $item->newInstance() : $item->newQuery()->lockForUpdate()->findOrFail($id);
                $oldPath = $section === 'photos' ? $record->path : null;
                $record->fill($data)->save();
                if ($section === 'meals') {
                    if ($photoIds !== null) {
                        $existing = $record->photos()->get()->keyBy('id');
                        $position = (int) $existing->max('pivot.position');
                        $attachments = [];
                        foreach ($photoIds as $photoId) {
                            $attachments[$photoId] = ['position' => $existing->get($photoId)?->pivot->position ?? ++$position];
                        }
                        $record->photos()->sync($attachments);
                    }
                }

                return $oldPath;
            }, 3);
        } catch (Throwable $exception) {
            if ($newPath) {
                $storage->delete($newPath);
            }
            throw $exception;
        }
        if ($newPath && $oldPath) {
            $storage->delete($oldPath);
        }

        return redirect()->route('admin.catalog.index', $section)->with('status', 'Changes saved.');
    }

    public function delete(PhotoStorage $storage, string $section, int $id)
    {
        $item = $this->model($section, $id);
        try {
            $path = DB::transaction(function () use ($section, $id, $item) {
                if ($section === 'photos') {
                    $setting = Setting::lockForUpdate()->findOrFail(1);
                    $content = $setting->values();
                    foreach (['hero_photo_id', 'about_photo_id', 'logo_photo_id'] as $field) {
                        if ((int) $content[$field] === $id) {
                            $content[$field] = null;
                        }
                    }
                    $setting->update(['content' => $content]);
                }
                $record = $item->newQuery()->lockForUpdate()->findOrFail($item->id);
                if ($section === 'categories' && $record->meals()->exists()) {
                    throw ValidationException::withMessages(['delete' => 'Move or remove meals in this category before deleting it.']);
                }
                $record->delete();

                return $section === 'photos' ? $record->path : null;
            }, 3);
        } catch (ForeignKeyConstraintViolationException $exception) {
            return back()->withErrors(['delete' => 'Move or remove meals in this category before deleting it.']);
        }
        if ($path) {
            $storage->delete($path);
        }

        return back()->with('status', 'Item deleted.');
    }
}







