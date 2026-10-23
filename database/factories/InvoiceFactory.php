<?php

namespace Database\Factories;

use App\Models\Invoice;
use App\Models\Booking;
use App\Enums\InvoiceStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Invoice>
 */
class InvoiceFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->regexify('^(?:10000(?:\.0+)?|[1-9]\d{0,3}(?:\.\d+)?|\d\.\d+)$');
        $discount = fake()->numberBetween(0, 50);
        $discountFactor = bcdiv((string) $discount, '100', 4); // e.g., "15" -> "0.1500"
        $multiplier = bcsub('1', $discountFactor, 4); // e.g., "1 - 0.1500" -> "0.8500"
        $rawTotal = bcmul($amount, $multiplier, 4);
        $total = bcadd($rawTotal, '0.005', 2);

        return [
            'booking_id' => Booking::factory(),
            'discount' => $discount,
            'amount' => $amount,
            'status' => InvoiceStatus::Pending,
            'expires_at' => now()->addMinutes(15),
            'paid_at' => null,
            'total' => $total,
        ];
    }

    public function paid(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => InvoiceStatus::Paid,
            'paid_at' => now(),
        ]);
    }

    public function refunded(): static
    {
        return $this->paid()->state([
            'status' => InvoiceStatus::Refunded,
        ]);
    }
}
