<?php

namespace Tests\Feature\Services\Invoice;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\Booking\NotAllowedBookingStatusException;
use App\Exceptions\Invoice\InvoiceAmountInvalidException;
use App\Exceptions\Invoice\InvoiceDiscountInvalidException;
use App\Exceptions\Invoice\PendingInvoiceAlreadyExistsException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class InvoiceServiceTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidDiscountProvider(): array
    {
        return [
            'negative discount' => [-1],
            'discount above maximum' => [51],
        ];
    }

    public static function invalidAmountProvider(): array
    {
        return [
            'zero amount' => ['0'],
            'negative amount' => ['-10.00'],
            'string amount' => ['abc'],
            'amount has extra signs' => ['10.999'],
            'amount above maximum' => ['1000000.00'],
        ];
    }

    public static function notAllowedBookingStatusProvider(): array
    {
        return [
            'cancelled' => [BookingStatus::Cancelled],
            'completed' => [BookingStatus::Completed],
        ];
    }

    public static function validAmountWithDecimals(): array
    {
        return [
            '10,01' => ['10.01', '8.51'],
            '19,99' => ['19.99', '16.99'],
        ];
    }

    public function test_create_creates_invoice_without_discount(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);
        $service = app(InvoiceService::class);

        // Act
        $invoice = $service->create($booking, '100.00');

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount' => '100.00',
            'discount' => 0,
            'total' => '100.00',
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    public function test_create_creates_invoice_with_discount(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
        ]);
        $service = app(InvoiceService::class);

        // Act
        $invoice = $service->create($booking, '100.00', 50);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount' => '100.00',
            'discount' => 50,
            'total' => '50.00',
            'status' => InvoiceStatus::Pending->value,
        ]);
    }

    #[DataProvider('invalidDiscountProvider')]
    public function test_create_throws_exception_when_discount_is_out_of_range(int $discount): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);
        $service = app(InvoiceService::class);

        // Assert
        $this->expectException(InvoiceDiscountInvalidException::class);

        // Act
        $service->create($booking, '100.00', $discount);
    }

    public function test_create_throws_exception_when_booking_already_has_pending_invoice(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);
        $service = app(InvoiceService::class);
        $service->create($booking, '100.00', 0);

        // Assert
        $this->expectException(PendingInvoiceAlreadyExistsException::class);

        // Act
        $service->create($booking, '200.00', 0);
    }

    #[DataProvider('invalidAmountProvider')]
    public function test_create_throws_exception_when_amount_has_invalid_value(string $amount): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);
        $service = app(InvoiceService::class);

        // Assert
        $this->expectException(InvoiceAmountInvalidException::class);

        // Act
        $service->create($booking, $amount, 0);
    }

    #[DataProvider('notAllowedBookingStatusProvider')]
    public function test_create_throws_exception_when_booking_has_not_allowed_status(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => $status,
        ]);
        $service = app(InvoiceService::class);

        // Assert
        $this->expectException(NotAllowedBookingStatusException::class);

        // Act
        $service->create($booking, '100.00');
    }

    public function test_create_sets_expires_at_fifteen_minutes_after_creation(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $now = Carbon::parse('2026-01-01 10:00:00');
        Carbon::setTestNow($now);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
        ]);
        $service = app(InvoiceService::class);

        // Act
        $invoice = $service->create($booking, '100.00');

        // Assert
        $this->assertTrue($invoice->expires_at->equalTo($now->copy()->addMinutes(15)));

        Carbon::setTestNow();
    }

    #[DataProvider('validAmountWithDecimals')]
    public function test_create_creates_invoice_with_discount_and_decimal_amount(
        string $amount,
        string $expectedTotal
    ): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
        ]);
        $service = app(InvoiceService::class);

        // Act
        $invoice = $service->create($booking, $amount, 15);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount' => $amount,
            'discount' => 15,
            'total' => $expectedTotal,
            'status' => InvoiceStatus::Pending->value,
        ]);
    }
}
