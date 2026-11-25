<?php

namespace Database\Seeders;

use App\Models\{Category, Meal, Setting};
use Illuminate\Database\Seeder;

class LocalMenuSeeder extends Seeder
{
    public function run(): void
    {
        abort_unless(app()->environment('local', 'testing'), 403);
        $category = Category::firstOrCreate(['slug' => 'development-only'], ['name' => 'Development only']);
        Meal::firstOrCreate(['slug' => 'development-checkout-item'], [
            'category_id' => $category->id, 'name' => 'Development checkout item',
            'description' => 'Test data for local checkout verification.',
            'price' => Setting::minor('100.00'), 'available' => true, 'featured' => true,
        ]);
    }
}
