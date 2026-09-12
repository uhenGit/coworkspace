<?php

namespace App\Data;

use Carbon\Carbon;

class BookingSlotGenerator
{
    /**
     * Генерирует слоты для бронирования на указанную дату.
     *
     * @param string $date Дата в формате 'YYYY-MM-DD'
     * @return array<int, array{start: Carbon, end: Carbon}>
     */
    public function generate(string $date): array
    {
        $start = Carbon::parse($date)->setTime(8, 0);
        $end = Carbon::parse($date)->setTime(21, 0);

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
