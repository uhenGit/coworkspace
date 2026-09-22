<?php

namespace Tests\Unit\Services\Booking;

use App\Models\Space;
use Carbon\Carbon;
use App\Services\Booking\BookingPriceCalculator;
use Tests\TestCase;
use App\Exceptions\Booking\InvalidBookingTimeException;
use App\Exceptions\Booking\InvalidBookingDurationException;
use App\Exceptions\Booking\InvalidBookingPriceException;

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

    public function test_price_calculator_throws_exception_when_start_is_out_of_work_range(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('07:00');
        $end = Carbon::parse('10:00');

        // Assert
        $this->expectException(InvalidBookingTimeException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_throws_exception_when_end_is_out_of_work_range(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('21:04');

        // Assert
        $this->expectException(InvalidBookingTimeException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_calculates_price_when_start_is_exact_at_the_day_start(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('10:00');
        $expectedTotalPrice = 200.00;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice);
    }

    public function test_price_calculator_calculates_price_when_end_is_exact_at_the_day_end(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('20:00');
        $end = Carbon::parse('21:00');
        $expectedTotalPrice = 100.00;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice);
    }

    public function test_price_calculator_throws_exception_when_start_equals_end(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('08:00');

        // Assert
        $this->expectException(InvalidBookingDurationException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_throws_exception_when_start_is_greater_than_end(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('09:00');
        $end = Carbon::parse('08:00');

        // Assert
        $this->expectException(InvalidBookingDurationException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_throws_exception_when_price_is_negative(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = -100.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('09:00');

        // Assert
        $this->expectException(InvalidBookingPriceException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_throws_exception_when_price_equals_zero(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 0.00;
        $calculator = new BookingPriceCalculator();
        $start = Carbon::parse('08:00');
        $end = Carbon::parse('09:00');

        // Assert
        $this->expectException(InvalidBookingPriceException::class);

        // Act
        $calculator->calculate($space, $start, $end);
    }

    public function test_price_calculator_calculates_price_when_booking_is_on_the_next_day(): void
    {
        // Arrange
        $space = new Space();
        $space->price_per_hour = 100.00;
        $calculator = new BookingPriceCalculator();
        $today = Carbon::now();
        $tomorrow = $today->addDay();
        $start = $tomorrow->copy()->setTime(9, 0);
        $end = $tomorrow->copy()->setTime(10, 0);
        $expectedTotalPrice = 100.00;

        // Act
        $totalPrice = $calculator->calculate($space, $start, $end);

        // Assert
        $this->assertEquals($expectedTotalPrice, $totalPrice);
    }
}