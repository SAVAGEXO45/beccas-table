<?php

namespace App\Services;

use App\Models\{Category, Meal};
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

class Cart
{
    public function lines(bool $lock = false): Collection
    {
        $entries = collect(session('cart', []))->map(function ($entry, $key) {
            return is_int($entry)
                ? ['meal_id' => (int) $key, 'quantity' => $entry, 'variation_id' => null, 'addons' => []]
                : $entry;
        });

        $query = Meal::with(['category', 'variations', 'addons', 'photo'])
            ->whereIn('id', $entries->pluck('meal_id'))->orderBy('id');
        $meals = ($lock ? $query->lockForUpdate() : $query)->get()->keyBy('id');
        if ($lock) {
            $categories = Category::whereIn('id', $meals->pluck('category_id'))->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            foreach ($meals as $meal) {
                $meal->setRelation('category', $categories->get($meal->category_id));
            }
        }

        return $entries->map(function (array $entry, $key) use ($meals) {
            $meal = $meals->get($entry['meal_id']);
            $quantity = $entry['quantity'];
            $selection = $meal ? $this->selection($meal, $entry['variation_id'], $entry['addons']) : null;

            return [
                'key' => (string) $key, 'id' => $entry['meal_id'], 'meal' => $meal, 'quantity' => $quantity,
                'available' => $selection !== null, 'unit_price' => $selection['price'] ?? 0,
                'options' => $selection['options'] ?? ($entry['labels'] ?? []),
                'total' => ($selection['price'] ?? 0) * $quantity,
            ];
        })->values();
    }

    public function selection(Meal $meal, ?int $variationId, array $addonIds): ?array
    {
        if (!$meal->canOrder()) {
            return null;
        }
        $variation = $meal->variations->firstWhere('id', $variationId);
        if ($meal->variations->isNotEmpty() ? !$variation?->available : $variationId !== null) {
            return null;
        }
        $addons = $meal->addons->whereIn('id', $addonIds)->where('available', true);
        if ($addons->count() !== count($addonIds)) {
            return null;
        }

        return [
            'price' => ($variation?->price ?? $meal->price) + $addons->sum('price'),
            'options' => array_values(array_filter([$variation?->label(), ...$addons->pluck('name')->all()])),
        ];
    }

    public function quote(Collection $lines, array $business): string
    {
        return hash('sha256', json_encode([
            $lines->map(fn ($line) => [$line['key'], $line['quantity'], $line['unit_price'], $line['options']])->all(),
            $business['minimum'], $business['delivery_enabled'], $business['delivery_areas'], $business['bank_transfer'],
        ], JSON_THROW_ON_ERROR));
    }

    public function set(int $id, int $quantity, bool $add = false, ?int $variationId = null, array $addonIds = [], ?string $line = null): void
    {
        $cart = session('cart', []);
        $addonIds = array_map('intval', $addonIds);
        sort($addonIds);
        $key = $line ?? ($variationId || $addonIds ? $id.'-'.hash('sha256', json_encode([$variationId, $addonIds])) : $id);
        $existing = $cart[$key] ?? null;
        if ($line !== null && (!isset($cart[$key]) || (is_array($existing) ? $existing['meal_id'] !== $id : (string) $key !== (string) $id))) {
            throw ValidationException::withMessages(['cart' => 'That cart line could not be found.']);
        }
        if ($quantity === 0) {
            unset($cart[$key]);
        } else {
            if ($line !== null && is_array($existing)) {
                $variationId = $existing['variation_id'];
                $addonIds = $existing['addons'];
            }
            $meal = Meal::with(['category', 'variations', 'addons'])->find($id);
            $selection = $meal ? $this->selection($meal, $variationId, $addonIds) : null;
            if (!$selection) {
                throw ValidationException::withMessages(['cart' => 'This meal or its selected options are unavailable.']);
            }
            $previous = is_array($existing) ? $existing['quantity'] : ($existing ?? 0);
            $quantity += $add ? $previous : 0;
            $cart[$key] = $variationId || $addonIds
                ? ['meal_id' => $id, 'quantity' => $quantity, 'variation_id' => $variationId, 'addons' => $addonIds, 'labels' => $selection['options']]
                : $quantity;
            if ($quantity > 30 || count($cart) > 30) {
                throw ValidationException::withMessages(['cart' => 'Orders can contain up to 30 different selections and 30 portions of each.']);
            }
        }
        session(['cart' => $cart]);
        session()->forget(['checkout_reference', 'checkout_quote']);
    }
}
