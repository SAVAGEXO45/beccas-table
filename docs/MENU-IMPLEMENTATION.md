# Becca’s Table menu implementation

The existing Laravel 12 project was extended in place. The menus use the same Blade layout, branding, admin authorization, session cart, checkout, payment records, and order history.

## Catalog and pricing

The supplied item list contains **60 distinct names**. It mentions 59 in the checklist, so the seed keeps every named dish.

| Category | Dishes |
| --- | ---: |
| Rice & Nigerian Staples | 7 |
| Soups & Swallows | 8 |
| Grills & Proteins | 8 |
| Continental Mains | 8 |
| Small Plates & Starters | 8 |
| Burgers, Sandwiches & Wraps | 7 |
| Drinks | 8 |
| Desserts & Sweet Bites | 6 |
| **Total** | **60** |

All 60 have original descriptions and suggested NGN prices. The seed includes **127** separately priced portion, protein, or named variations and **55** optional extras. The search supports names and descriptions. Customers can filter by category, featured, popular, available, and sold out. Featured and popular flags start empty for staff to select.

The public local menu shows the suggested prices with a pending-confirmation label. Seeded items become orderable individually once the owner confirms the prices in the dashboard. Staff can set availability independently. Adjusting an option price or adding or removing an option requires reconfirmation. Existing menu prices retain their prior ordering behavior when migrated.

Lagos restaurant menus informed the suggested price ranges. The restaurant owner still sets the actual prices. [Terra Kulture menu](https://restaurant.terrakulture.com/menu) · [Kulture Yard menu](https://kultureyard.com/menu.html) · [Haven Lagos menu](https://www.havenlagos.com/restaurant)

## Administration

- **Meals** lists 20 dishes per page with search and category filters. Create, edit and delete dishes, edit descriptions and prices, arrange display position, assign a category, and set availability, featured and popular flags. On the edit screen, use **Preview customer card** to inspect how the dish appears.
- **Variations and extras** on a saved meal manages sizes, proteins, names, prices, availability and order. A variation price is the full price per portion. An extra is added to the price of each selected portion.
- **Manage photographs** on a saved meal accepts up to eight files at once, with visual previews before upload. Set the primary image, arrange the others using position numbers, or replace or remove an individual image. A meal can have up to 12 images. JPEG, PNG and WebP are validated and then re-encoded into three WebP sizes. Removing an assignment preserves the library photo and any other meal using it. To permanently delete an unused upload, use the **Photos** library.
- **Categories** manages names, descriptions, position and activation. A category containing dishes must be emptied by moving or removing its dishes before it can be deleted.
- **Prices confirmed for public ordering** on the meal edit screen enables ordering at the reviewed price. The dashboard and meal list carry the requested suggested-price notice.

Each meal card and meal detail page has its own photo state. Photograph galleries support arrow buttons, thumbnails, swiping, and a larger keyboard-accessible gallery. A generic label distinguishes stock photographs from restaurant photographs on the menu. The admin identifies illustrative stock photos without showing supplier credits.

The local menu includes **eight licensed stock photos across five dishes**. The other **55 dishes** use the menu placeholder until suitable real photos are available. Source records are retained with the bundled seed and in the database. [Pexels license](https://www.pexels.com/license/)

## Schema and local work executed

| Table | Change |
| --- | --- |
| `categories` | Added activation and display position. Existing foreign keys protect categories containing meals. |
| `meals` | Added price-confirmation, popularity and display position. Current availability, featured flag, and category and main-photo relationships are reused. |
| `meal_variations` | Each meal has many variants. Each variant stores its name, optional size and protein, its own price, availability and position. |
| `meal_addons` | Each meal has many optional extras, with a price, availability and position per extra. |
| `meal_photo`, `photos` | Added order to the many-to-many relationship and an illustrative-stock flag to the photo record. |
| `order_items` | Added a nullable snapshot of selected options. New orders preserve the options and final unit price after later menu edits. |

The existing ZIP was backed up before edits. The existing SQLite preview was left intact. The full Laravel migration set and the additive menu migration were run on a separate, loopback-only **MySQL 8.4.13** database for this project. `RestaurantMenuSeeder` and `RestaurantMenuPhotographySeeder` were both executed. MySQL returned the catalog counts above. Neither seeder overwrites existing approved prices or attached photos. The updated catalog is available locally at `http://127.0.0.1:8124/menu` while its preview processes are running.

## Verification

- The Laravel regression and menu suite passed on MySQL and SQLite with **24 tests and 364 assertions per database**. The suite covers seed counts and re-running seeds, draft and sold-out order protection, search and category filters, options and price confirmation, priced cart combinations and quantity changes, checkout and the order snapshot, role protection, photo upload validation, batch upload, replacement, reordering, and removal. Existing authentication, admin operations, delivery, reservations, and payment tests ran alongside them.
- Browser checks verified previous and next arrows, thumbnail selection, independence between two food cards, large gallery navigation, closing by button and Escape, and category navigation. Viewports at **390, 768, 1024 and 1440 pixels** fit without horizontal overflow.
- Two Node tests dispatched simulated touch events to verify horizontal swipes in both directions and that cancelled, short, vertical and multi-touch gestures leave galleries stationary. Physical Android and iPhone touch interaction was not tested.
- The Vite production build passed. Eight local HTTP pages and 16 responsive/full-size photo URLs were served successfully. A cart request without a CSRF token returned HTTP 419. An upgrade test on a copy of the previous SQLite preview preserved existing row counts across eight tables. The checkout regression and option snapshot test use mocked Paystack responses. No live payment was attempted, and no hosting environment was changed.

## Files

**Created**

- `database/migrations/2026_11_23_100000_extend_restaurant_menu.php`
- `database/seeders/RestaurantMenuSeeder.php`, `database/seeders/RestaurantMenuPhotographySeeder.php`, and eight licensed JPEGs in `database/seeders/menu-photos/`
- `app/Models/MealVariation.php`, `app/Models/MealAddon.php`, and `app/Http/Controllers/Admin/MealConfigurationController.php`
- `resources/views/admin/catalog/meal-photos.blade.php`, `options.blade.php`, and `preview.blade.php`
- `resources/views/components/admin/menu-option-row.blade.php`, `resources/views/components/meal-gallery.blade.php`, `meal-order.blade.php`, and `menu-price-notice.blade.php`
- `resources/css/menu.css`, `resources/js/menu.js`, and `resources/js/menu-gestures.js`
- `tests/Feature/MenuTest.php`, `tests/Frontend/menu-gestures.test.mjs`, and this report

**Modified**

- `app/Models/Meal.php`, `Category.php`, `Photo.php`, and `OrderItem.php`
- `app/Http/Controllers/SiteController.php`, `OrderController.php`, and `Admin/CatalogController.php`
- `app/Services/Cart.php` and `routes/web.php`
- `resources/views/site/menu.blade.php`, `meal.blade.php`, and `home.blade.php`
- `resources/views/admin/catalog/index.blade.php`, `form.blade.php`, and `resources/views/admin/dashboard.blade.php`
- `resources/views/components/layout.blade.php`, `meal-list.blade.php`, and `resources/views/orders/cart.blade.php`, `checkout.blade.php`, and `show.blade.php`
- `resources/css/app.css`, `resources/js/app.js`, `README.md`, and the compiled assets in `public/build/`

## Owner input and configuration

Review all prices and available combinations, verify the dish descriptions and any ingredients or allergen information, and supply approved restaurant photographs for the 55 dishes with placeholders and replacements for the illustrative photos. Confirm each item before enabling ordering. The current local environment uses MySQL and has live Paystack and email turned off. A PHP runtime that permits eight 6 MB uploads needs `post_max_size` at least 50 MB and `max_file_uploads` at least 8. The installer and migration commands are in the README.

