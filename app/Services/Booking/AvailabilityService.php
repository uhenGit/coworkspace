<?php

namespace App\Services\Booking;

use App\Data\BookingSlotGenerator;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Space;
use Carbon\Carbon;

readonly class AvailabilityService
{
    public function isAvailable(Space $space, Carbon $start, Carbon $end): bool
    {
        $booking_buffer_minutes = Category::where('id', $space->category_id)->value('booking_buffer_minutes');
        $corrected_start = $start->copy()->subMinutes($booking_buffer_minutes);
        $hasConflict = Booking::with('space.category')
            ->where([
                ['space_id', '=', $space->id],
                ['start_time', '<', $end],
                ['end_time', '>', $corrected_start],
            ])
            ->whereIn('status', BookingStatus::blockingStatuses())
            ->exists();

        return ! $hasConflict;
    }

    public function getAvailableSlots(Space $space, Carbon $day): array
    {
        $generator = new BookingSlotGenerator;
        $slots = $generator->generate($day);

        $start = Carbon::parse($day)->setTime(8, 0);
        $end = Carbon::parse($day)->setTime(21, 0);

        $bookings = Booking::where('space_id', $space->id)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->whereIn('status', BookingStatus::blockingStatuses())
            ->get();

        if ($bookings->isEmpty()) {
            return $slots;
        }

        $availableSlots = array_filter($slots, function ($slot) use ($bookings) {
            foreach ($bookings as $booking) {
                if ($slot['start'] < $booking->end_time && $slot['end'] > $booking->start_time) {
                    return false;
                }
            }

            return true;
        });

        return array_values($availableSlots);
    }
}
