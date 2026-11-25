<?php

namespace Tests\Feature;

use App\Models\{Category, Meal, Photo, Setting, User};
use Database\Seeders\{RestaurantMenuPhotographySeeder, RestaurantMenuSeeder};
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\{Http, Storage};
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('m', 32))]);
    }

    public function test_catalog_seed_is_complete_and_preserves_owner_changes(): void
    {
        Storage::fake('public');
        $this->seed(RestaurantMenuSeeder::class);
        $this->assertDatabaseCount('categories', 8);
        $this->assertDatabaseCount('meals', 60);
        $this->assertSame(60, Meal::where('price', '>', 0)->where('description', '<>', '')->where('price_confirmed', false)->count());
        $this->assertSame([7, 8, 8, 8, 8, 7, 8, 6], $this->categoryCounts());
        $jollof = Meal::where('slug', 'party-jollof-rice')->firstOrFail();
        $this->assertSame(5, $jollof->variations()->count());
        $this->assertSame(2, $jollof->addons()->count());
        $this->seed(RestaurantMenuPhotographySeeder::class);
        $this->assertDatabaseCount('photos', 8);
        $this->assertSame(5, Meal::whereNotNull('photo_id')->count());
        $this->assertSame(8, Photo::where('illustrative', true)->count());
        $this->get('/menu')->assertOk()->assertSee('Where Every Meal Feels Special.')
            ->assertDontSee('Illustrative food photograph')->assertSee('Suggested pricing');
        $this->post(route('cart.update', $jollof), ['quantity' => 1, 'variation' => $jollof->variations()->first()->id])
            ->assertSessionHasErrors('cart');

        $jollof->update(['price' => 970000, 'price_confirmed' => true]);
        $this->seed(RestaurantMenuSeeder::class);
        $this->seed(RestaurantMenuPhotographySeeder::class);
        $this->assertDatabaseCount('meals', 60);
        $this->assertDatabaseCount('photos', 8);
        $this->assertSame(970000, $jollof->fresh()->price);
        $this->assertTrue($jollof->fresh()->price_confirmed);
    }

    public function test_menu_filters_search_and_handles_hidden_categories_and_sold_out_meals(): void
    {
        $this->seed(RestaurantMenuSeeder::class);
        $burger = Meal::where('slug', 'classic-beef-burger')->firstOrFail();
        $burger->update(['popular' => true, 'featured' => true, 'price_confirmed' => true]);
        $this->get('/menu?q=Classic+Beef')->assertOk()->assertSee('Classic Beef Burger')->assertDontSee('Party Jollof Rice');
        $categoryUrl = route('menu.category', $burger->category->slug);
        $this->get($categoryUrl)->assertOk()->assertSee('Classic Beef Burger')->assertDontSee('Fresh Watermelon Juice');
        foreach (['featured', 'popular', 'available'] as $filter) {
            $this->get('/menu?view='.$filter)->assertOk()->assertSee('Classic Beef Burger');
        }
        $burger->update(['available' => false]);
        $this->get('/menu?view=sold-out')->assertOk()->assertSee('Classic Beef Burger')->assertSee('Sold out');
        $this->post(route('cart.update', $burger), ['quantity' => 2])->assertSessionHasErrors('cart');
        $burger->category->update(['active' => false]);
        $this->get($categoryUrl)->assertNotFound();
        $this->get(route('menu.meal', $burger->slug))->assertNotFound();
    }

    public function test_options_have_independent_cart_lines_and_are_repriced_and_snapshotted_at_checkout(): void
    {
        $meal = $this->meal('Grilled Chicken');
        $small = $meal->variations()->create(['name' => 'Grilled', 'size' => 'Small', 'protein' => 'Chicken', 'price' => 900000]);
        $large = $meal->variations()->create(['name' => 'Roasted', 'size' => 'Large', 'price' => 1250000]);
        $extra = $meal->addons()->create(['name' => 'Plantain', 'price' => 200000]);
        $customer = User::factory()->create();
        $this->actingAs($customer)->post(route('cart.update', $meal), ['quantity' => 1])->assertSessionHasErrors('cart');
        $this->post(route('cart.update', $meal), ['quantity' => 2, 'variation' => $small->id, 'addons' => [$extra->id], 'price' => 1])
            ->assertRedirect('/cart');
        $line = array_key_first(session('cart'));
        $this->post(route('cart.update', $meal), ['quantity' => 1, 'variation' => $large->id])->assertRedirect('/cart');
        $this->get('/cart')->assertOk()->assertSee('Small')->assertSee('Large')->assertSee('Plantain')->assertSee('₦34,500.00');
        $this->post(route('cart.update', $meal), ['line' => $line, 'quantity' => 3])->assertRedirect('/cart');
        $this->get('/cart')->assertSee('₦45,500.00');
        $this->post(route('cart.update', $meal), ['quantity' => 1, 'variation' => $large->id, 'addons' => [98999]])
            ->assertSessionHasErrors('cart');
        $largeKey = array_key_last(session('cart'));
        $large->update(['available' => false]);
        $this->get('/cart')->assertSee('Remove this');
        $this->post(route('cart.update', $meal), ['line' => $largeKey, 'quantity' => 0])->assertRedirect('/cart');
        $this->get('/cart')->assertOk()->assertSee('₦33,000.00');

        config(['restaurant.paystack.secret_key' => 'local-fixture']);
        $this->get('/checkout')->assertOk()->assertSee('Plantain');
        $reference = session('checkout_reference');
        Http::fake(['https://api.paystack.co/transaction/initialize' => Http::response([
            'status' => true, 'data' => ['authorization_url' => 'https://checkout.paystack.com/fixture', 'reference' => $reference],
        ])]);
        $this->post('/checkout', [
            'checkout_reference' => $reference, 'name' => $customer->name, 'phone' => '+234 801 234 5678', 'fulfilment' => 'pickup',
        ])->assertRedirect('https://checkout.paystack.com/fixture');
        $order = $customer->orders()->sole();
        $item = $order->items()->sole();
        $this->assertEquals(3300000, $order->total);
        $this->assertEquals(1100000, $item->unit_price);
        $this->assertSame(['Grilled · Small · Chicken', 'Plantain'], $item->options);
        $extra->delete();
        $this->get(route('orders.show', $order))->assertOk()->assertSee('Plantain');
    }

    public function test_staff_can_save_searchable_options_and_confirm_prices_while_customers_cannot(): void
    {
        $meal = $this->meal('Weekly Chicken');
        $base = "/admin/menu/meals/{$meal->id}";
        $data = ['variations' => [['name' => 'Seared', 'size' => 'Large', 'protein' => 'Chicken', 'price' => '9000.50', 'available' => 1, 'position' => 2]]];
        $this->actingAs(User::factory()->create())->put($base.'/options', $data)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->get($base.'/options')->assertOk()->assertSee('Add variation');
        $this->put($base.'/options', $data)->assertSessionHasNoErrors();
        $this->assertFalse($meal->fresh()->price_confirmed);
        $this->assertSame(900050, $meal->variations()->sole()->price);
        $this->patch("/admin/catalog/meals/{$meal->id}", [
            'name' => $meal->name, 'slug' => $meal->slug, 'description' => $meal->description,
            'category_id' => $meal->category_id, 'price' => '8000', 'price_confirmed' => 1, 'available' => 1,
        ])->assertSessionHasNoErrors();
        $this->get('/admin/catalog/meals?q=Weekly&category_id='.$meal->category_id)->assertOk()->assertSee('Weekly Chicken');
        $this->get($base.'/preview')->assertOk()->assertSee('Weekly Chicken');
        $this->get('/menu?q=Weekly')->assertOk()->assertSee('₦9,000.50')->assertSee('Add to cart');
    }

    public function test_staff_can_batch_upload_order_replace_and_detach_meal_photographs_safely(): void
    {
        Storage::fake('public');
        $meal = $this->meal('Paneer');
        $other = $this->meal('Chicken Pasta');
        $base = "/admin/menu/meals/{$meal->id}/photos";
        $this->get($base)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create(['is_admin' => true]));
        $this->post($base, ['images' => [UploadedFile::fake()->create('test.svg', 1, 'image/svg+xml')]])->assertSessionHasErrors();
        $this->post($base, ['illustrative' => 1, 'images' => [
            UploadedFile::fake()->image('front.jpg', 900, 700), UploadedFile::fake()->image('side.jpg', 800, 650),
        ]])->assertSessionHasNoErrors();
        [$front, $side] = $meal->photos()->get()->all();
        $this->assertSame($front->id, $meal->fresh()->photo_id);
        $other->update(['photo_id' => $front->id]);
        $this->get('/admin/catalog/meals?q=Paneer')->assertOk()->assertSee('Edit photos for Paneer')->assertSee(url($base));
        $this->get("/admin/catalog/meals/{$meal->id}/edit")->assertOk()->assertSee(url($base));
        $this->get($base)->assertOk()->assertSee('Illustrative stock')
            ->assertSeeInOrder(['Photo 1', 'Replace photo 1', 'Save photo 1', 'Photo 2', 'Replace photo 2', 'Save photo 2'])
            ->assertSee(url("$base/{$front->id}"))->assertSee(url("$base/{$side->id}"));
        $this->put($base.'/order', ['primary' => $side->id, 'positions' => [$front->id => 2, $side->id => 1]])
            ->assertSessionHasNoErrors();
        $this->assertSame([$side->id, $front->id], $meal->fresh()->load(['photo', 'photos'])->gallery()->pluck('id')->all());
        $this->patch("$base/{$front->id}", ['alt' => 'Replacement', 'image' => UploadedFile::fake()->image('new.jpg', 600, 500)])
            ->assertSessionHasNoErrors();
        $this->assertSame($front->id, $other->fresh()->photo_id);
        Storage::disk('public')->assertExists($front->path);
        $this->patch("$base/{$side->id}", ['alt' => 'New primary', 'image' => UploadedFile::fake()->image('new-primary.png', 800, 650)])
            ->assertSessionHasNoErrors();
        $updated = $meal->fresh()->load(['photo', 'photos']);
        $this->assertSame(['New primary', 'Replacement'], $updated->gallery()->pluck('alt')->all());
        $this->assertSame('New primary', $updated->photo->alt);
        $this->get(route('menu.meal', $meal->slug))->assertOk()->assertSee('New primary')->assertSee('Replacement');
        $categoryPage = $this->get(route('menu.category', ['category' => $meal->category->slug, 'q' => $meal->name]))
            ->assertOk()->assertDontSee($side->path)->assertSee('New primary');
        $this->assertMatchesRegularExpression(
            '~class="menu-hero-link"[^>]*>\s*<figure[^>]*>\s*<img src="'.preg_quote($updated->photo->url(960), '~').'"~',
            $categoryPage->getContent()
        );
        $this->delete("$base/{$updated->photo_id}")->assertSessionHasNoErrors();
        $updated = $meal->fresh()->load(['photo', 'photos']);
        $this->assertSame('Replacement', $updated->gallery()->sole()->alt);
        $this->assertNotEquals($side->id, $updated->photo_id);
        $this->delete("$base/{$front->id}")->assertNotFound();
    }

    public function test_gallery_shows_menu_photos_to_guests_and_signed_in_customers(): void
    {
        $meal = $this->meal('Gallery Chicken');
        $hiddenMeal = $this->meal('Hidden Gallery Meal');
        $hiddenMeal->category()->associate(Category::create(['name' => 'Hidden', 'slug' => 'hidden', 'active' => false]));
        $photoDetails = ['width' => 800, 'height' => 600];
        $primary = Photo::create($photoDetails + ['path' => 'photos/gallery-chicken.webp', 'alt' => 'Menu chicken photo']);
        $hidden = Photo::create($photoDetails + ['path' => 'photos/hidden-meal.webp', 'alt' => 'Hidden meal photo']);
        $meal->update(['photo_id' => $primary->id]);
        $hiddenMeal->update(['photo_id' => $hidden->id]);

        $this->get('/gallery')->assertOk()->assertSee('Menu chicken photo')->assertDontSee('Hidden meal photo');

        Photo::create($photoDetails + [
            'path' => 'photos/curated.webp', 'alt' => 'Curated gallery photo', 'show_in_gallery' => true,
        ]);
        $this->actingAs(User::factory()->create())->get('/gallery')->assertOk()
            ->assertSeeInOrder(['Curated gallery photo', 'Menu chicken photo'])->assertDontSee('Hidden meal photo');
    }

    public function test_category_banner_uses_a_photo_from_its_filtered_menu_and_links_to_the_dish(): void
    {
        $grill = $this->meal('Grilled Chicken');
        $dessert = $this->meal('Chocolate Brownie');
        $dessert->category()->associate(Category::create(['name' => 'Dessert', 'slug' => 'dessert']));
        $grillPhoto = Photo::create(['path' => 'photos/chicken.webp', 'alt' => 'Grilled chicken photo', 'width' => 800, 'height' => 600]);
        $dessertPhoto = Photo::create(['path' => 'photos/brownie.webp', 'alt' => 'Chocolate brownie photo', 'width' => 800, 'height' => 600]);
        $grill->photos()->attach($grillPhoto->id);
        $dessert->update(['photo_id' => $dessertPhoto->id]);

        foreach ([[$grill, $grillPhoto, $dessertPhoto], [$dessert, $dessertPhoto, $grillPhoto]] as [$meal, $expected, $other]) {
            $response = $this->get(route('menu.category', $meal->category->slug))->assertOk()
                ->assertDontSee($other->path)->assertDontSee('jollof-reference');
            $this->assertMatchesRegularExpression(
                '~class="menu-hero-link" href="'.preg_quote(route('menu.meal', $meal->slug), '~').'".*?'.preg_quote($expected->url(960), '~').'~s',
                $response->getContent()
            );
        }

        $this->get(route('menu.category', ['category' => $grill->category->slug, 'q' => 'No matches']))->assertOk()
            ->assertDontSee('class="menu-hero-link"', false)->assertDontSee('jollof-reference');
    }

    public function test_all_meals_shows_brand_artwork_and_uses_the_admin_selected_logo(): void
    {
        $this->get('/menu')->assertOk()->assertSee('beccas-table-hero-768.webp')->assertDontSee('menu-hero-link');
        $logo = Photo::create(['path' => 'photos/updated-logo.webp', 'alt' => 'Updated logo', 'width' => 800, 'height' => 600]);
        $setting = Setting::findOrFail(1);
        $setting->update(['content' => array_replace($setting->values(), ['logo_photo_id' => $logo->id])]);

        $this->get('/menu')->assertOk()->assertSee($logo->url(960))->assertDontSee('beccas-table-hero-768.webp');
    }

    public function test_admin_catalog_loads_only_category_counts_until_opened_and_filters_across_categories(): void
    {
        $this->seed(RestaurantMenuSeeder::class);
        $drinks = Category::where('slug', 'drinks')->sole();
        $menuUrl = '/admin/catalog/meals';
        $fragment = route('admin.catalog.category-meals', $drinks);
        $this->get($fragment)->assertRedirect('/admin/login');
        $this->actingAs(User::factory()->create())->get($fragment)->assertForbidden();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $catalog = $this->get($menuUrl)->assertOk()->assertSee($fragment)->assertDontSee('Edit photos for Fruit Salad');
        $this->assertCount(8, $catalog->viewData('categories'));
        $this->assertSame(60, $catalog->viewData('categories')->sum('meals_count'));
        $this->assertNull($catalog->viewData('items'));

        $this->get($fragment)->assertOk()->assertSee('Fresh Watermelon Juice')
            ->assertSee('Edit photos for Fresh Watermelon Juice')->assertSee('Delete Fresh Watermelon Juice')
            ->assertDontSee('Chocolate Brownie');
        Meal::where('slug', 'fresh-watermelon-juice')->update(['available' => false]);
        $filtered = $this->get($menuUrl.'?q=Juice&availability=sold-out')->assertOk()
            ->assertSee('1 meal found')->assertSee('Drinks')->assertSee('Fresh Watermelon Juice')
            ->assertDontSee('Fresh Pineapple Juice')->assertSee('Clear filters');
        $this->assertSame(1, $filtered->viewData('items')->total());

        $this->get($menuUrl.'?q=Juice&category_id='.$drinks->id.'&availability=available')->assertOk()
            ->assertSee('2 meals found')->assertDontSee('Fresh Watermelon Juice');
        $this->get($menuUrl.'?category_id='.$drinks->id.'&q=Brownie')->assertOk()
            ->assertSee('No meals match these filters.');
    }

    public function test_large_admin_categories_have_their_own_pages_and_protect_assigned_meals(): void
    {
        for ($number = 1; $number <= 13; $number++) {
            $this->meal(sprintf('Grill Meal %02d', $number));
        }
        $category = Category::where('slug', 'grill')->sole();
        $this->actingAs(User::factory()->create(['is_admin' => true]));

        $this->get(route('admin.catalog.category-meals', $category))->assertOk()
            ->assertSee('Grill Meal 12')->assertDontSee('Grill Meal 13')
            ->assertSee('category_id='.$category->id.'&amp;page=2', false);
        $this->get('/admin/catalog/meals?category_id='.$category->id.'&page=2')->assertOk()
            ->assertSee('Grill Meal 13')->assertDontSee('Grill Meal 12');
        $this->get('/admin/catalog/categories')->assertOk()->assertSee('13 meals')->assertSee('View meals');
        $this->get("/admin/catalog/categories/{$category->id}/edit")->assertOk()
            ->assertSee('before deleting it')->assertDontSee('Delete category');
        $this->delete("/admin/catalog/categories/{$category->id}")->assertSessionHasErrors('delete');
        $this->assertDatabaseCount('meals', 13);
    }

    private function categoryCounts(): array
    {
        return Category::withCount('meals')->orderBy('position')->pluck('meals_count')->map(fn ($count) => (int) $count)->all();
    }

    private function meal(string $name): Meal
    {
        $category = Category::firstOrCreate(['slug' => 'grill'], ['name' => 'Grill']);
        return $category->meals()->create([
            'name' => $name, 'slug' => \Illuminate\Support\Str::slug($name), 'description' => 'Prepared for the table.',
            'price' => 800000, 'available' => true,
        ]);
    }
}


