<?php

namespace Tests\Unit\Services\Booking;

use App\Models\Space;
use Carbon\Carbon;
use App\Services\Booking\BookingPriceCalculator;
use Tests\TestCase;

class BookingPriceCalculatorTest extends TestCase
{
    public function test_price_calculator_calculates_price_for_three_hours(): void
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

    public function test_price_calculator_calculates_price_for_one_and_a_half_hours(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('10:30');
        $end = Carbon::parse('12:00');
        $expectedTotalPrice = 150.00;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice, 0.01);
    }

    public function test_price_calculator_calculates_price_for_half_an_hour(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('10:30');
        $expectedTotalPrice = 50;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice, 0.01);
    }
}