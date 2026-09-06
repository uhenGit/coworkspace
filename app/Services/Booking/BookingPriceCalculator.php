<?php

namespace App\Services\Booking;

use App\Models\Space;
use Carbon\Carbon;

readonly class BookingPriceCalculator
{
    public function calculate(Space $space, Carbon $start, Carbon $end): float
    {
        return $start->floatDiffInHours($end) * (float) $space->price_per_hour;
    }
}
