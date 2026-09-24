<?php

namespace Tests\Unit\Services\Booking;

use App\Data\ReserveBookingData;
use App\Enums\BookingStatus;
use App\Exceptions\Booking\BookingConfirmationInvalidStatusException;
use App\Exceptions\Booking\BookingTimeConflictException;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Space;
use App\Models\User;
use App\Services\Booking\AvailabilityService;
use App\Services\Booking\BookingPriceCalculator;
use App\Services\Booking\BookingService;
use App\Services\Invoice\InvoiceService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

    public static function invalidConfirmationStatusesProvider(): array
    {
        return [
            'confirmed booking' => [BookingStatus::Confirmed],
            'cancelled booking' => [BookingStatus::Cancelled],
            'completed booking' => [BookingStatus::Completed],
        ];
    }

    private function createSpaceWithBuffer(int $buffer = 15, string $category_code = 'event_space'): Space
    {
        $category = Category::where('short_code', $category_code)->firstOrFail();

        $category->update([
            'booking_buffer_minutes' => $buffer,
        ]);

        return Space::factory()->create([
            'category_id' => $category->id,
        ]);
    }

    public function test_service_throw_time_conflict_exception_if_space_is_not_available(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('12:00');
        $user = User::factory()->create();
        Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(BookingService::class);

        $data = new ReserveBookingData(
            start_time: $start,
            end_time: $end,
            space_id: $space->id,
            notes: 'test',
        );

        // Assert
        $this->expectException(BookingTimeConflictException::class);
        // Check for the missing record with defined fields
        $this->assertDatabaseMissing('bookings', [
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'notes' => 'test',
        ]);
        // Check by quantity (should be only one record in the test DB)
        $this->assertDatabaseCount('bookings', 1);

        // Act
        $service->reserve($user, $data);
    }

    public function test_service_creates_booking_when_space_is_available(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $user = User::factory()->create();
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('12:00');
        $data = new ReserveBookingData(
            start_time: $start,
            end_time: $end,
            space_id: $space->id,
            notes: 'success test'
        );
        $service = app(BookingService::class);

        // Act
        $booking = $service->reserve($user, $data);

        // Assert
        $this->AssertInstanceOf(Booking::class, $booking);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'user_id' => $user->id,
            'space_id' => $space->id,
            'start_time' => $start,
            'end_time' => $end,
            'notes' => 'success test',
            'status' => BookingStatus::Pending,
            'total_price' => $booking->total_price,
        ]);
    }

    public function test_booking_rollback_when_invoice_service_throws_exception(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $user = User::factory()->create();
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('12:00');
        $data = new ReserveBookingData(
            start_time: $start,
            end_time: $end,
            space_id: $space->id,
            notes: 'rollback test',
        );

        // Booking created inside the transaction will be captured here
        $capturedBooking = null;

        $this->mock(InvoiceService::class)
            ->shouldReceive('create')
            ->once()
            ->andReturnUsing(function (Booking $booking) use (&$capturedBooking) {
                $capturedBooking = $booking;

                throw new \RuntimeException('Invoice creation failed.');
            });

        $service = app(BookingService::class);

        // Act
        try {
            $service->reserve($user, $data);
            $this->fail('RuntimeException was not thrown.');
            $this->assertSame(
                'Invoice creation failed.',
                $exception->getMessage()
            );
        } catch (\RuntimeException $exception) {
            // Expected exception, transaction must be rolled back
        }

        // Assert
        $this->assertNotNull($capturedBooking, 'InvoiceService did not receive the created booking.');
        // The booking created inside the transaction must not be persisted after rollback
        $this->assertDatabaseMissing('bookings', [
            'id' => $capturedBooking->id,
        ]);
    }

    public function test_confirm_changes_pending_booking_status_to_confirmed(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $availabilityService = $this->createMock(AvailabilityService::class);
        $priceCalculator = $this->createMock(BookingPriceCalculator::class);
        $invoiceService = $this->createMock(InvoiceService::class);

        $service = new BookingService(
            $availabilityService,
            $priceCalculator,
            $invoiceService,
        );

        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'space_id' => $space->id,
        ]);

        // Act
        $service->confirm($booking);

        // Assert
        $this->assertSame(BookingStatus::Confirmed, $booking->status);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Confirmed,
        ]);
    }

    #[DataProvider('invalidConfirmationStatusesProvider')]
    public function test_confirm_throws_exception_when_the_booking_has_wrong_status(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $availabilityService = $this->createMock(AvailabilityService::class);
        $priceCalculator = $this->createMock(BookingPriceCalculator::class);
        $invoiceService = $this->createMock(InvoiceService::class);

        $service = new BookingService(
            $availabilityService,
            $priceCalculator,
            $invoiceService,
        );

        $booking = Booking::factory()->create([
            'status' => $status,
            'space_id' => $space->id,
        ]);

        // Assert
        $this->expectException(BookingConfirmationInvalidStatusException::class);

        // Act
        $service->confirm($booking);
    }
}
