<?php

namespace App\Data;

use Carbon\Carbon;

readonly class BookingSlotGenerator
{
    /**
     * Generate slots for Bookings using the date passed in.
     *
     * @param  Carbon  $day  'YYYY-MM-DD'
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function generate(Carbon $day): array
    {
        $start = Carbon::parse($day)->setTime(8, 0);
        $end = Carbon::parse($day)->setTime(21, 0);

        $slots = [];

        while ($start->lessThan($end)) {
            $slotEnd = $start->copy()->addHour();

            $slots[] = [
                'start' => $start->copy(),
                'end' => $slotEnd,
            ];

            $start = $slotEnd;
        }

        return $slots;
    }
}
