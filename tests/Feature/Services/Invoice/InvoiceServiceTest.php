<?php

namespace Tests\Feature\Services\Invoice;

use App\Enums\BookingStatus;
use App\Enums\InvoiceStatus;
use App\Exceptions\Invoice\InvoiceDiscountInvalidException;
use App\Exceptions\Invoice\PendingInvoiceAlreadyExistsException;
use App\Models\Booking;
use App\Models\Invoice;
use App\Services\Invoice\InvoiceService;
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
        $invoice = $service->create($booking, '100.00', 15);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertDatabaseHas('invoices', [
            'id' => $invoice->id,
            'booking_id' => $booking->id,
            'amount' => '100.00',
            'discount' => 15,
            'total' => '85.00',
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
}
