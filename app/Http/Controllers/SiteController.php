<?php

namespace App\Http\Controllers;

use App\Models\{Category, Meal, Photo, Setting, Testimonial};
use Illuminate\Http\Request;

class SiteController extends Controller
{
    public function home()
    {
        return view('site.home', [
            'featured' => Meal::with(Meal::MENU_RELATIONS)->whereHas('category', fn ($q) => $q->where('active', true))->where('available', true)->where('price_confirmed', true)->where('featured', true)->orderBy('position')->latest()->limit(8)->get(),
            'categories' => Category::where('active', true)->whereHas('meals', fn ($query) => $query->where('available', true))->orderBy('position')->orderBy('name')->get(),
            'gallery' => Photo::where('show_in_gallery', true)->latest()->limit(3)->get(),
            'testimonials' => Testimonial::where('published', true)->latest()->limit(4)->get(),
        ]);
    }

    public function menu(Request $request, ?Category $category = null)
    {
        $filter = $request->validate(['q' => 'nullable|string|max:100', 'view' => 'nullable|in:featured,popular,available,sold-out']);
        $search = trim($filter['q'] ?? '');
        abort_if($category?->exists && !$category->active, 404);
        $view = $filter['view'] ?? null;
        $meals = Meal::with(Meal::MENU_RELATIONS)->whereHas('category', fn ($q) => $q->where('active', true))
            ->when($view === 'featured' || $view === 'popular', fn ($q) => $q->where($view, true))
            ->when($view === 'available', fn ($q) => $q->where('available', true)->where('price_confirmed', true)
                ->where(fn ($q) => $q->doesntHave('variations')->orWhereHas('variations', fn ($q) => $q->where('available', true))))
            ->when($view === 'sold-out', fn ($q) => $q->where(fn ($q) => $q->where('available', false)
                ->orWhere(fn ($q) => $q->has('variations')->whereDoesntHave('variations', fn ($q) => $q->where('available', true)))))
            ->when($category?->exists, fn ($query) => $query->where('category_id', $category->id))
            ->when($search !== '', fn ($query) => $query->where(fn ($match) => $match->whereLike('name', '%'.$search.'%')->orWhereLike('description', '%'.$search.'%')))
            ->orderBy('position')->orderBy('name')->paginate(12)->withQueryString();
        $categories = Category::where('active', true)->orderBy('position')->orderBy('name')->get();

        $heroMeal = $category?->exists
            ? $meals->getCollection()->first(fn (Meal $meal) => $meal->photo || $meal->photos->isNotEmpty())
            : null;
        $hero = $heroMeal?->gallery()->first();
        $brandPhotoId = $category?->exists ? null : Setting::current()['logo_photo_id'];
        $brandPhoto = $brandPhotoId ? Photo::find($brandPhotoId) : null;

        return view('site.menu', compact('meals', 'categories', 'category', 'search', 'view', 'heroMeal', 'hero', 'brandPhoto'));
    }

    public function meal(Meal $meal)
    {
        $meal->load(Meal::MENU_RELATIONS);
        abort_unless($meal->category->active, 404);

        return view('site.meal', ['meal' => $meal]);
    }

    public function photoCredits()
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('local');
        $credits = $disk->exists('menu-stock-credits.json') ? $disk->json('menu-stock-credits.json') : [];

        return view('site.photo-credits', ['credits' => $credits ?: []]);
    }

    public function gallery()
    {
        $menuPhotos = Meal::query()->select('photo_id')->whereNotNull('photo_id')
            ->whereHas('category', fn ($query) => $query->where('active', true));

        $gallery = Photo::query()
            ->where('show_in_gallery', true)
            ->orWhereIn('id', $menuPhotos)
            ->orderByDesc('show_in_gallery')
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        return view('site.gallery', compact('gallery'));
    }

    public function photograph(string $file)
    {
        $disk = \Illuminate\Support\Facades\Storage::disk('public');
        $path = 'photos/'.$file;
        abort_unless($disk->exists($path), 404);

        return $disk->response($path, $file, [
            'Content-Type' => 'image/webp',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }

    public function sitemap()
    {
        $urls = array_map(fn ($route) => route($route), ['home', 'about', 'menu', 'gallery', 'contact', 'faq', 'privacy', 'terms', 'reservations.create']);
        Category::where('active', true)->whereHas('meals', fn ($query) => $query->where('available', true))->orderBy('id')->chunkById(100, function ($categories) use (&$urls) {
            foreach ($categories as $category) {
                $urls[] = route('menu.category', $category->slug);
            }
        });
        Meal::whereHas('category', fn ($q) => $q->where('active', true))->orderBy('id')->chunkById(100, function ($meals) use (&$urls) {
            foreach ($meals as $meal) {
                $urls[] = route('menu.meal', $meal->slug);
            }
        });

        return response()->view('site.sitemap', compact('urls'))->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    public function robots()
    {
        return response("User-agent: *\nDisallow: /admin/\nDisallow: /account\nDisallow: /cart\nDisallow: /checkout\nDisallow: /payments/\nSitemap: ".route('sitemap')."\n")
            ->header('Content-Type', 'text/plain; charset=UTF-8');
    }

    public function account(Request $request)
    {
        $orders = $request->user()->orders()
            ->with('payment')
            ->withSum('items as meal_quantity', 'quantity')
            ->latest()
            ->orderByDesc('id')
            ->paginate(12);

        return view('account.index', compact('orders'));
    }
}





