<?php

namespace App\Services\Booking;

use App\Data\ReserveBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Exceptions\Booking\BookingTimeConflictException;
use Illuminate\Support\Facades\DB;

readonly class BookingService
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private BookingPriceCalculator $priceCalculator,
    ) {}

    public function reserve(User $user, ReserveBookingData $data): Booking
    {
        return DB::transaction(function () use ($user, $data) { // take user and data from outer scope
            $space = Space::with('category')->findOrFail($data->space_id);
            $is_available = $this->availabilityService->isAvailable($space, $data->start_time, $data->end_time);

            if (! $is_available) {
                throw new BookingTimeConflictException('The selected time already booked.');
            }

            $total_price = $this->priceCalculator->calculate($space, $data->start_time, $data->end_time);

            return Booking::create([
                'user_id' => $user->id,
                'space_id' => $data->space_id,
                'start_time' => $data->start_time,
                'end_time' => $data->end_time,
                'status' => BookingStatus::Pending,
                'notes' => $data->notes,
                'total_price' => $total_price,
            ]);
        });

        // receive DTO and start transaction
        // check if Space exists
        // AvailabilityService + PriceCalculator
        // model create()
        // commit
        // return Booking
    }

    public function confirm()
    {
        // payment
        // change status
    }

    public function cancel() {}

    public function changeTime() {}

    public function extend() {}
}
