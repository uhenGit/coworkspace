<?php

namespace Database\Factories;

use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Space;
use App\Models\User;
use App\Support\Booking\BookingTimeGenerator;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Booking>
 */
class BookingFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $start = BookingTimeGenerator::generateBookingStart();
        $duration = fake()->randomElement([1, 2, 3, 4]);
        $end = (clone $start)->modify("+{$duration} hours"); // It's not the Carbone instance, so handle it like DateTime

        return [
            'start_time' => $start,
            'end_time' => $end,
            'total_price' => fake()->numberBetween(2, 100),
            'notes' => fake()->paragraph(),
            'status' => fake()->randomElement(BookingStatus::cases()),
            'space_id' => Space::factory(),
            'user_id' => User::factory(),
        ];
    }
}
