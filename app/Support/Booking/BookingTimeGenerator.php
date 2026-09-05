<?php

namespace App\Support\Booking;

class BookingTimeGenerator
{
    public static function generateBookingStart(): \DateTime
    {
        $start = new \DateTime('today 08:00');
        $end = new \DateTime('today 21:00');
        $start->setTime((int) $start->format('H'), 0, 0);
        $end->setTime((int) $end->format('H'), 0, 0);
        $timestamp = random_int($start->getTimestamp(), $end->getTimestamp());

        return (new \DateTime)->setTimestamp($timestamp);
    }
}
