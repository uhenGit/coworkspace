<?php

namespace App\Services\Booking;

use App\Models\Space;
use Carbon\Carbon;
use App\Exceptions\Booking\InvalidBookingTimeException;
use App\Exceptions\Booking\InvalidBookingDurationException;
use App\Exceptions\Booking\InvalidBookingPriceException;

readonly class BookingPriceCalculator
{
    private function validateBookingTime(Carbon $start, Carbon $end): bool
    {
        return $start->gte(Carbon::parse('08:00')) && $end->lte(Carbon::parse('21:00'));
    }

    private function validateBookingDuration(Carbon $start, Carbon $end): bool
    {
        return $start->lt($end);
    }

    private function validateBookingPrice(float $price): bool
    {
        return $price > 0;
    }

    public function calculate(Space $space, Carbon $start, Carbon $end): float
    {
        if (! $this->validateBookingTime($start, $end)) {
            throw new InvalidBookingTimeException();
        }

        if (! $this->validateBookingDuration($start, $end)) {
            throw new InvalidBookingDurationException();
        }

        if (! $this->validateBookingPrice($space->price_per_hour)) {
            throw new InvalidBookingPriceException();
        }

        return $start->floatDiffInHours($end) * (float) $space->price_per_hour;
    }
}
