<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Cancelled = 'cancelled';

    public static function blockingStatuses(): array
    {
        return ['pending', 'confirmed'];
    }
}
