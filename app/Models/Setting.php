<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const DAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

    protected $fillable = ['id', 'content'];

    protected function casts(): array
    {
        return ['content' => 'array'];
    }

    public static function defaults(): array
    {
        return [
            'name' => "Becca's Table",
            'tagline' => 'Where Every Meal Feels Special.',
            'address' => '12 Admiralty Way, Lekki Phase 1, Lagos, Nigeria',
            'phone' => '+234 9031131536',
            'email' => '',
            'instagram' => 'https://www.instagram.com/beccastable/',
            'instagram_label' => '@beccastable',
            'facebook' => 'https://www.facebook.com/BeccasTable/',
            'facebook_label' => "Becca's Table",
            'whatsapp' => 'https://wa.me/2349031131536',
            'charcoal' => '#252525',
            'gold' => '#C5A15D',
            'ivory' => '#F5F0E6',
            'currency' => 'NGN',
            'intro_heading' => 'Nigerian favourites. Continental plates.',
            'intro' => 'Join us in Lekki Phase 1 for Nigerian and Continental food, or order ahead for pickup.',
            'about_heading' => 'A table for every occasion',
            'about' => "Becca’s Table is a Nigerian and Continental restaurant on Admiralty Way in Lekki Phase 1. Join us for a meal, reserve a table for your group, or order pickup when you’re eating elsewhere.",
            'reasons' => 'Nigerian and Continental cooking, online ordering for pickup and table reservations throughout the week.',
            'footer' => 'Nigerian and Continental dining in Lekki Phase 1.',
            'pickup' => 'Collect your order at the restaurant.',
            'delivery_enabled' => false,
            'delivery_areas' => [],
            'minimum' => 0,
            'bank_transfer' => [
                'enabled' => false,
                'bank_name' => '',
                'account_name' => '',
                'account_number' => '',
                'instructions' => 'Use your order reference as the transfer narration where your bank supports it.',
            ],
            'hours' => array_fill_keys(self::DAYS, ['open' => '09:00', 'close' => '22:00', 'closed' => false]),
            'reservations_enabled' => true,
            'capacity' => null,
            'duration' => 90,
            'lead_minutes' => 60,
            'max_days' => 60,
            'closed_dates' => [],
            'hero_photo_id' => null,
            'about_photo_id' => null,
            'logo_photo_id' => null,
            'privacy' => null,
            'terms' => null,
            'faq' => null,
        ];
    }

    public static function current(): array
    {
        $request = app()->runningInConsole() ? null : request();
        $key = self::class;
        if ($request?->attributes->has($key)) {
            return $request->attributes->get($key);
        }

        $settings = self::findOrFail(1)->values();
        $request?->attributes->set($key, $settings);

        return $settings;
    }

    public function values(): array
    {
        return array_replace(self::defaults(), $this->content);
    }

    public static function money(int $amount): string
    {
        return '₦'.number_format($amount / 100, 2);
    }

    public static function minor(string|int $amount): int
    {
        [$whole, $fraction] = array_pad(explode('.', (string) $amount, 2), 2, '');
        return (int) $whole * 100 + (int) str_pad($fraction, 2, '0');
    }
}


