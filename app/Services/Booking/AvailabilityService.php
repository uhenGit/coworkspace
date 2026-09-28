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
    public function isAvailable(
        Space $space,
        Carbon $start,
        Carbon $end,
        ?int $excludeBookingId = null,
    ): bool
    {
        $booking_buffer_minutes = Category::where('id', $space->category_id)->value('booking_buffer_minutes');
        $corrected_start = $start->copy()->subMinutes($booking_buffer_minutes);
        $query = Booking::where([
            ['space_id', '=', $space->id],
            ['start_time', '<', $end],
            ['end_time', '>', $corrected_start],
        ])
            ->whereIn('status', BookingStatus::blockingStatuses());

        if ($excludeBookingId !== null) {
            $query->where('id', '!=', $excludeBookingId);
        }

        return ! $query->exists();
    }

    public function getAvailableSlots(Space $space, Carbon $day): array
    {
        $generator = new BookingSlotGenerator;
        $slots = $generator->generate($day);

        $start = $day->copy()->setTime(8, 0);
        $end = $day->copy()->setTime(21, 0);

        $bookings = Booking::where('space_id', $space->id)
            ->where('start_time', '<', $end)
            ->where('end_time', '>', $start)
            ->whereIn('status', BookingStatus::blockingStatuses())
            ->get();

        if ($bookings->isEmpty()) {
            return $slots;
        }

        $booking_buffer_minutes = Category::where('id', $space->category_id)->value('booking_buffer_minutes');
        $availableSlots = array_filter($slots, function ($slot) use ($bookings, $booking_buffer_minutes) {
            $corrected_end = $slot['end']->copy()->addMinutes($booking_buffer_minutes);

            foreach ($bookings as $booking) {
                if ($slot['start'] < $booking->end_time && $corrected_end > $booking->start_time) {
                    return false;
                }
            }

            return true;
        });

        return array_values($availableSlots);
    }
}
