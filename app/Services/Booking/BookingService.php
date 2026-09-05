<?php

namespace App\Services\Booking;

use App\Data\ReserveBookingData;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use Illuminate\Support\Facades\DB;

readonly class BookingService
{
    public function __construct(
        private AvailabilityService $availabilityService,
        private BookingPriceCalculator $priceCalculator,
    ) {}

    public function reserve(User $user, ReserveBookingData $data): Booking
    {
        return DB::transaction(function (User $user, ReserveBookingData $data) {
            $space = Space::with('category')->findOrFail($data->space_id);
            $is_available = $this->availabilityService->isAvailable($space, $data->start_date, $data->end_date);

            if (! $is_available) {
                // throw BookingTimeConflictExceprion;
            }

            $total_price = $this->priceCalculator->calculate($space, $data->start_date, $data->end_date);

            return Booking::create([
                'user_id' => $user->id,
                'space_id' => $data->space_id,
                'start_time' => $data->start_date,
                'end_time' => $data->end_date,
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
