<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\CarbonImmutable;
use Illuminate\Validation\ValidationException;

class Tables
{
    public function assertAvailable(array $settings, string $date, string $time, int $guests, ?Reservation $existing = null): CarbonImmutable
    {
        $timezone = config('restaurant.timezone');
        $start = CarbonImmutable::parse("$date ".substr($time, 0, 5), $timezone);
        $now = CarbonImmutable::now($timezone);
        $end = $existing ? CarbonImmutable::parse($existing->ends_at, $timezone) : $start->addMinutes((int) $settings['duration']);
        $hours = $settings['hours'][$start->englishDayOfWeek];

        if ((!$existing && !$settings['reservations_enabled']) || $hours['closed'] || in_array($date, $settings['closed_dates'], true)
            || $start < $now->addMinutes($existing ? 0 : (int) $settings['lead_minutes'])
            || (!$existing && $start->toDateString() > $now->addDays((int) $settings['max_days'])->toDateString())
            || $start->format('H:i') < $hours['open'] || $end->toDateString() !== $date || $end->format('H:i') > $hours['close']) {
            throw ValidationException::withMessages(['time' => 'Choose an available date and time within the restaurant’s booking hours.']);
        }

        $overlaps = Reservation::where('date', $date)
            ->whereIn('status', ['pending', 'approved'])
            ->when($existing, fn ($query) => $query->whereKeyNot($existing->id))
            ->where('time', '<', $end->format('H:i:s'))
            ->where('ends_at', '>', $start->toDateTimeString());
        $capacity = $settings['capacity'];
        if ($capacity === null ? $overlaps->exists() : $this->peakGuests($overlaps->get(), $start, $end) + $guests > $capacity) {
            throw ValidationException::withMessages(['time' => 'There isn’t enough availability at that time. Please choose a different time.']);
        }

        return $end;
    }

    private function peakGuests(iterable $reservations, CarbonImmutable $start, CarbonImmutable $end): int
    {
        $changes = [];
        foreach ($reservations as $reservation) {
            $arrival = max($start->format('H:i:s'), substr($reservation->time, 0, 5).':00');
            $departure = min($end->format('H:i:s'), substr($reservation->ends_at, 11));
            $changes[$arrival] = ($changes[$arrival] ?? 0) + $reservation->guests;
            $changes[$departure] = ($changes[$departure] ?? 0) - $reservation->guests;
        }
        ksort($changes);
        $current = 0;
        $peak = 0;
        foreach ($changes as $change) {
            $current += $change;
            $peak = max($peak, $current);
        }

        return $peak;
    }
}

