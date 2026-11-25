<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class RestaurantMenuSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function () {
            foreach (self::menu() as $index => [$categoryName, $categoryDescription, $meals]) {
                $category = Category::firstOrCreate(['slug' => Str::slug($categoryName)], [
                    'name' => $categoryName, 'description' => $categoryDescription, 'position' => ($index + 1) * 10,
                ]);
                foreach ($meals as $position => [$name, $price, $description]) {
                    $meal = \App\Models\Meal::firstOrCreate(['slug' => Str::slug($name)], [
                        'category_id' => $category->id, 'name' => $name, 'description' => $description,
                        'price' => $price * 100, 'available' => true, 'price_confirmed' => false, 'position' => ($position + 1) * 10,
                    ]);
                    if (!$meal->wasRecentlyCreated) {
                        continue;
                    }
                    $this->addOptions($meal, $index, $price);
                }
            }
        }, 3);
    }

    private function addOptions(\App\Models\Meal $meal, int $section, int $price): void
    {
        $variations = match ($section) {
            0 => [
                ['Classic portion', 'Small', null, $price - 1500], ['Classic portion', 'Medium', null, $price],
                ['Classic portion', 'Large', null, $price + 2500], ['With protein', 'Medium', 'Chicken', $price + 3500],
                ['With protein', 'Medium', 'Beef', $price + 3000],
            ],
            1 => [['Soup plate', 'Medium', null, $price], ['Soup plate', 'Large', null, $price + 3000],
                ['Soup plate', 'Medium', 'Goat meat', $price + 3500], ['Soup plate', 'Medium', 'Fish', $price + 4500]],
            2, 3 => [['Standard portion', 'Medium', null, $price], ['Generous portion', 'Large', null, $price + 4000]],
            4 => in_array($meal->slug, ['small-chops-platter', 'peppered-gizzard', 'chicken-wings', 'pepper-soup'], true)
                ? [['Single serving', null, null, $price], ['Sharing portion', null, null, $price + 4000]] : [],
            6 => in_array($meal->slug, ['soft-drinks', 'bottled-water'], true) ? []
                : [['Small glass', 'Small', null, $price - 500], ['Regular glass', 'Medium', null, $price], ['Large glass', 'Large', null, $price + 1200]],
            7 => $meal->slug === 'vanilla-ice-cream'
                ? [['One scoop', null, null, $price], ['Two scoops', null, null, $price + 2000]] : [],
            default => [],
        };
        foreach ($variations as $i => [$name, $size, $protein, $amount]) {
            $meal->variations()->create(['name' => $name, 'size' => $size, 'protein' => $protein, 'price' => $amount * 100, 'position' => $i + 1]);
        }
        $addons = match ($section) {
            0 => ['Fried plantain' => 2200, 'Extra stew' => 1600],
            1 => ['Extra swallow' => 2200],
            2 => ['Fried plantain' => 2200, 'Side of jollof rice' => 3500],
            3 => ['Side salad' => 3000],
            5 => ['Side of fries' => 3000],
            7 => in_array($meal->slug, ['chocolate-brownie', 'pancakes'], true) ? ['Vanilla ice cream' => 2800] : [],
            default => [],
        };
        foreach ($addons as $name => $amount) {
            $meal->addons()->create(['name' => $name, 'price' => $amount * 100]);
        }
    }

    private static function menu(): array
    {
        return [
            ['Rice & Nigerian Staples', 'Smoky rice, pepper sauces and familiar Nigerian favourites.', [
                ['Party Jollof Rice', 6500, 'Smoky party-style rice simmered in a rich tomato and pepper base.'],
                ['Nigerian Fried Rice', 7000, 'Savoury seasoned rice tossed with carrots, peas and crisp vegetables.'],
                ['Coconut Rice', 7500, 'Fragrant rice gently cooked in coconut milk for a mellow, creamy finish.'],
                ['Ofada Rice with Ayamase Sauce', 9500, 'Nutty Ofada rice paired with a deeply savoury green pepper sauce.'],
                ['Native Rice', 7800, 'Palm-oil rice layered with peppers and traditional Nigerian seasoning.'],
                ['White Rice with Stew', 5800, 'Fluffy white rice with a generous spoonful of slow-cooked tomato and pepper stew.'],
                ['Jollof Spaghetti', 6800, 'Spaghetti tossed in a lively Nigerian-style tomato and pepper sauce.'],
            ]],
            ['Soups & Swallows', 'Traditional soups with bold seasoning and smooth, satisfying swallows.', [
                ['Egusi Soup with Pounded Yam', 12000, 'Ground melon seeds and leafy greens in a rich soup with smooth pounded yam.'],
                ['Efo Riro with Amala', 11500, 'Leafy greens simmered in a deeply seasoned pepper base, served with soft amala.'],
                ['Ogbono Soup with Eba', 11500, 'A silky wild mango seed soup with the satisfying tang of fresh eba.'],
                ['Okra Soup with Fufu', 11000, 'Tender chopped okra in a savoury broth, paired with soft fufu.'],
                ['Afang Soup', 12500, 'Finely sliced afang leaves and waterleaf in a rich, palm-oil broth.'],
                ['Edikang Ikong', 12500, 'Fluted pumpkin leaves and waterleaf cooked down into a deeply savoury vegetable soup.'],
                ['Oha Soup', 12000, 'A delicate cocoyam-thickened soup finished with fragrant oha leaves.'],
                ['Banga Soup with Starch', 13000, 'A spiced palm-fruit broth with a smooth helping of traditional starch.'],
            ]],
            ['Grills & Proteins', 'Flame-grilled favourites and bold peppered plates.', [
                ['Grilled Chicken', 9500, 'Juicy marinated chicken grilled until golden with lightly charred edges.'],
                ['Peppered Chicken', 9800, 'Tender chicken tossed in a bright, punchy pepper and onion sauce.'],
                ['Grilled Croaker Fish', 16500, 'Croaker grilled over high heat for crisp skin and tender, flaky flesh.'],
                ['Grilled Tilapia Fish', 14500, 'Spiced tilapia grilled to a smoky finish while staying tender inside.'],
                ['Peppered Goat Meat', 11500, 'Tender goat pieces finished in a concentrated pepper sauce with fragrant onions.'],
                ['Beef Suya', 7800, 'Thin slices of grilled beef dusted with aromatic suya spice.'],
                ['Turkey Wings', 11500, 'Generously seasoned turkey wings cooked until tender with a golden exterior.'],
                ['Peppered Snail', 15500, 'Tender snail pieces coated in a hot, savoury pepper and onion sauce.'],
            ]],
            ['Continental Mains', 'Comforting pastas, noodle dishes and carefully prepared mains.', [
                ['Chicken Alfredo Pasta', 16500, 'Pasta and tender chicken folded through a silky, creamy Alfredo sauce.'],
                ['Seafood Pasta', 22500, 'Pasta tossed with a savoury seafood medley and a fragrant garlic sauce.'],
                ['Spaghetti Bolognese', 14500, 'Spaghetti coated with a slow-simmered beef and tomato ragù.'],
                ['Chicken Stir-Fry Noodles', 12500, 'Wok-tossed noodles with tender chicken, crisp vegetables and a savoury glaze.'],
                ['Beef Steak with Mashed Potatoes', 24500, 'Seared beef steak served with buttery mashed potatoes.'],
                ['Chicken Escalope', 14500, 'A thin chicken cutlet with a crisp golden coating and a tender centre.'],
                ['Creamy Mushroom Pasta', 14500, 'Pasta coated in a smooth sauce made with deeply browned mushrooms.'],
                ['Chicken Fried Rice', 11500, 'Wok-fried rice with seasoned chicken and vegetables.'],
            ]],
            ['Small Plates & Starters', 'Crisp bites, shareable snacks and warming bowls.', [
                ['Small Chops Platter', 10500, 'An assortment of crisp savoury bites and lightly sweet puff-puff for sharing.'],
                ['Puff-Puff', 2500, 'Pillowy fried dough bites with a golden crust and lightly sweet centre.'],
                ['Samosa', 3200, 'Crisp pastry parcels filled with a warmly spiced savoury mixture.'],
                ['Spring Rolls', 3400, 'Golden rolls with a crisp shell and a finely sliced vegetable filling.'],
                ['Peppered Gizzard', 7500, 'Tender chicken gizzards tossed with chillies, peppers and onions.'],
                ['Chicken Wings', 8500, 'Seasoned chicken wings with crisp edges and juicy meat.'],
                ['Moi Moi', 3200, 'A soft steamed bean pudding blended with peppers and savoury seasoning.'],
                ['Pepper Soup', 10000, 'A warming broth with layered pepper-soup spices and tender pieces of protein.'],
            ]],
            ['Burgers, Sandwiches & Wraps', 'Handheld favourites and loaded fries.', [
                ['Classic Beef Burger', 9500, 'A juicy beef patty with crisp vegetables and burger sauce in a toasted bun.'],
                ['Chicken Burger', 8800, 'Seasoned chicken with crisp lettuce and creamy dressing in a soft bun.'],
                ['Club Sandwich', 9800, 'A generous stack of toasted bread, chicken, crisp greens and creamy dressing.'],
                ['Grilled Chicken Sandwich', 8500, 'Smoky sliced chicken and fresh greens layered between toasted bread.'],
                ['Chicken Shawarma', 6800, 'Seasoned chicken, crunchy vegetables and creamy sauce wrapped in warm flatbread.'],
                ['Beef Shawarma', 7500, 'Savoury strips of beef with fresh vegetables and sauce in soft flatbread.'],
                ['Loaded Fries', 7200, 'Crisp fries piled with savoury toppings and a generous drizzle of sauce.'],
            ]],
            ['Drinks', 'Fresh fruit juices, Nigerian favourites and cold refreshments.', [
                ['Chapman', 4800, 'A sparkling Nigerian favourite with citrus, grenadine and a gentle bitter finish.'],
                ['Zobo', 3200, 'A chilled hibiscus drink with a bright, tangy finish.'],
                ['Fresh Watermelon Juice', 3800, 'Cold watermelon juice with a light, naturally sweet finish.'],
                ['Fresh Pineapple Juice', 4200, 'Fresh pineapple juice with a balance of tropical sweetness and tang.'],
                ['Orange Juice', 4200, 'Bright orange juice served cold for an easy, refreshing finish.'],
                ['Lemonade', 3500, 'A chilled, tart-sweet lemonade with a clean citrus finish.'],
                ['Soft Drinks', 1600, 'Choose from the chilled sodas available on the day.'],
                ['Bottled Water', 1000, 'Bottled drinking water, served chilled on request.'],
            ]],
            ['Desserts & Sweet Bites', 'Something sweet to finish or share.', [
                ['Chocolate Brownie', 5500, 'A rich chocolate square with a soft centre and a delicate crackled top.'],
                ['Cheesecake', 6500, 'Smooth, creamy cheesecake on a buttery biscuit base.'],
                ['Red Velvet Cake', 6000, 'Tender red velvet sponge layered with velvety cream cheese frosting.'],
                ['Vanilla Ice Cream', 3500, 'Creamy vanilla ice cream with a delicate, fragrant finish.'],
                ['Pancakes', 6500, 'A stack of soft golden pancakes served warm with syrup.'],
                ['Fruit Salad', 4500, 'Colourful pieces of fresh fruit served chilled for a light finish.'],
            ]],
        ];
    }
}

