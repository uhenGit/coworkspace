<?php

namespace Tests\Unit\Services\Booking;

use App\Models\Space;
use Carbon\Carbon;
use App\Services\Booking\BookingPriceCalculator;
use Tests\TestCase;

class BookingPriceCalculatorTest extends TestCase
{
    public function test_price_calculator_returns_exact_value(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 138.50;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('13:00');
        $expectedTotalPrice = 415.50;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice, 0.01);
    }
}