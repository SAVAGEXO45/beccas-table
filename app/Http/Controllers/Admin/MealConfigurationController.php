<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\{Meal, Photo, Setting};
use App\Services\PhotoStorage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Throwable;

class MealConfigurationController extends Controller
{
    private const IMAGE_RULES = ['file', 'mimetypes:image/jpeg,image/png,image/webp', 'max:6000', 'dimensions:max_width=5000,max_height=5000'];

    public function preview(Meal $meal)
    {
        return view('admin.catalog.preview', ['meal' => $meal->load(Meal::MENU_RELATIONS)]);
    }

    public function options(Meal $meal)
    {
        return view('admin.catalog.options', ['meal' => $meal->load(['variations', 'addons'])]);
    }

    public function saveOptions(Request $request, Meal $meal)
    {
        $rules = [];
        foreach (['variations', 'addons'] as $type) {
            $rules[$type] = 'sometimes|array|max:20';
            $rules["$type.*.id"] = 'nullable|integer|min:1|distinct';
            $rules["$type.*.name"] = 'required|string|max:100';
            $rules["$type.*.price"] = ['required', 'regex:/^\d{1,7}(\.\d{1,2})?$/D', 'numeric', 'min:1', 'max:999999.99'];
            $rules["$type.*.position"] = 'required|integer|min:0|max:65535';
            $rules["$type.*.available"] = 'required|boolean';
            $rules["$type.*.remove"] = 'nullable|boolean';
        }
        $rules['variations.*.size'] = 'nullable|string|max:50';
        $rules['variations.*.protein'] = 'nullable|string|max:50';
        $payload = $request->validate($rules);

        DB::transaction(function () use ($meal, $payload) {
            $meal = Meal::lockForUpdate()->findOrFail($meal->id);
            $pricingChanged = false;
            foreach ($payload as $type => $rows) {
                $relation = $meal->{$type}();
                $existing = $relation->get()->keyBy('id');
                foreach ($rows as $row) {
                    $id = $row['id'] ?? null;
                    $record = $id ? $existing->get($id) : $relation->make();
                    if (!$record) {
                        throw ValidationException::withMessages([$type => 'One of these options no longer belongs to this meal. Reload the page.']);
                    }
                    if ($row['remove'] ?? false) {
                        if ($record->exists) {
                            $record->delete();
                            $pricingChanged = true;
                        }
                        continue;
                    }
                    $row['price'] = Setting::minor($row['price']);
                    unset($row['id'], $row['remove']);
                    $record->fill($row);
                    $pricingChanged = $pricingChanged || !$record->exists || $record->isDirty('price');
                    $record->save();
                }
            }
            if ($pricingChanged) {
                $meal->update(['price_confirmed' => false]);
            }
        }, 3);

        return back()->with('status', 'Meal options saved.');
    }

    public function photos(Meal $meal)
    {
        return view('admin.catalog.meal-photos', ['meal' => $meal->load(['photo', 'photos'])]);
    }

    public function upload(Request $request, Meal $meal, PhotoStorage $storage)
    {
        $data = $request->validate([
            'images' => 'required|array|min:1|max:8',
            'images.*' => ['required', ...self::IMAGE_RULES],
            'illustrative' => 'nullable|boolean',
        ]);
        $files = [];
        try {
            foreach ($data['images'] as $image) {
                $files[] = $storage->store($image);
            }
            DB::transaction(function () use ($meal, $files, $data) {
                $meal = Meal::lockForUpdate()->with(['photo', 'photos'])->findOrFail($meal->id);
                $gallery = $meal->gallery();
                if ($gallery->count() + count($files) > 12) {
                    throw ValidationException::withMessages(['images' => 'A meal can have up to 12 photographs.']);
                }
                $position = $meal->photos->max('pivot.position') ?? 0;
                foreach ($files as $index => $file) {
                    $photo = Photo::create($file + [
                        'alt' => mb_substr($meal->name, 0, 150).' – photograph '.($gallery->count() + $index + 1),
                        'illustrative' => (bool) ($data['illustrative'] ?? false),
                    ]);
                    $meal->photos()->attach($photo->id, ['position' => ++$position]);
                    if (!$meal->photo_id && $gallery->isEmpty()) {
                        $meal->update(['photo_id' => $photo->id]);
                    }
                }
            }, 3);
        } catch (Throwable $error) {
            foreach ($files as $file) {
                $storage->delete($file['path']);
            }
            throw $error;
        }

        return back()->with('status', 'Photographs uploaded.');
    }

    public function order(Request $request, Meal $meal)
    {
        $data = $request->validate([
            'primary' => 'required|integer|min:1',
            'positions' => 'required|array|min:1|max:13',
            'positions.*' => 'required|integer|min:0|max:65535',
        ]);

        DB::transaction(function () use ($meal, $data) {
            $meal = Meal::lockForUpdate()->with(['photo', 'photos'])->findOrFail($meal->id);
            $ids = $meal->gallery()->pluck('id')->sort()->values()->all();
            $submitted = collect(array_keys($data['positions']))->sort()->values()->all();
            if ($ids !== $submitted || !in_array((int) $data['primary'], $ids, true)) {
                throw ValidationException::withMessages(['positions' => 'The photograph list changed. Reload the page before saving.']);
            }
            $order = collect($data['positions'])->sort()->keys()->values();
            $meal->photos()->sync($order->mapWithKeys(fn ($id, $index) => [$id => ['position' => $index + 1]])->all());
            $meal->update(['photo_id' => $data['primary']]);
        }, 3);

        return back()->with('status', 'Photo order and primary image saved.');
    }

    public function replace(Request $request, Meal $meal, Photo $photo, PhotoStorage $storage)
    {
        $data = $request->validate([
            'image' => ['nullable', ...self::IMAGE_RULES],
            'alt' => 'required|string|max:200',
            'illustrative' => 'nullable|boolean',
        ]);
        abort_unless($meal->load(['photo', 'photos'])->gallery()->contains('id', $photo->id), 404);
        $file = $request->hasFile('image') ? $storage->store($request->file('image')) : null;

        try {
            DB::transaction(function () use ($meal, $photo, $data, $file) {
                $meal = Meal::lockForUpdate()->with(['photo', 'photos'])->findOrFail($meal->id);
                abort_unless($meal->gallery()->contains('id', $photo->id), 404);
                if (!$file) {
                    $photo->fill(['alt' => $data['alt'], 'illustrative' => (bool) ($data['illustrative'] ?? false)]);
                    $shared = $photo->show_in_gallery
                        || in_array($photo->id, array_intersect_key(Setting::current(), array_flip(['hero_photo_id', 'logo_photo_id', 'about_photo_id'])))
                        || Meal::whereKeyNot($meal->id)->where(fn ($q) => $q->where('photo_id', $photo->id)
                            ->orWhereHas('photos', fn ($q) => $q->whereKey($photo->id)))->exists();
                    if ($photo->isDirty() && $shared) {
                        throw ValidationException::withMessages(['image' => 'Upload a replacement to change this photograph for this meal alone.']);
                    }
                    $photo->save();
                    return;
                }
                $replacement = Photo::create($file + [
                    'alt' => $data['alt'], 'illustrative' => (bool) ($data['illustrative'] ?? false),
                ]);
                $position = $meal->photos->firstWhere('id', $photo->id)?->pivot->position ?? 0;
                $meal->photos()->detach($photo->id);
                $meal->photos()->attach($replacement->id, ['position' => $position]);
                if ((int) $meal->photo_id === $photo->id) {
                    $meal->update(['photo_id' => $replacement->id]);
                }
            }, 3);
        } catch (Throwable $error) {
            if ($file) {
                $storage->delete($file['path']);
            }
            throw $error;
        }

        return back()->with('status', $file ? 'Photograph replaced for this meal.' : 'Photo details saved.');
    }

    public function detach(Meal $meal, Photo $photo)
    {
        DB::transaction(function () use ($meal, $photo) {
            $meal = Meal::lockForUpdate()->with(['photo', 'photos'])->findOrFail($meal->id);
            $gallery = $meal->gallery();
            abort_unless($gallery->contains('id', $photo->id), 404);
            $meal->photos()->detach($photo->id);
            if ((int) $meal->photo_id === $photo->id) {
                $meal->update(['photo_id' => $gallery->first(fn ($item) => $item->id !== $photo->id)?->id]);
            }
        }, 3);

        return back()->with('status', 'Photograph removed from this meal.');
    }
}


