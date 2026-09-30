<?php

namespace Tests\Unit\Services\Booking;

use App\Data\ReserveBookingData;
use App\Enums\BookingStatus;
use App\Exceptions\Booking\BookingCancellationInvalidStatusException;
use App\Exceptions\Booking\BookingConfirmationInvalidStatusException;
use App\Exceptions\Booking\BookingExtensionInvalidStatusException;
use App\Exceptions\Booking\BookingTimeChangeInvalidStatusException;
use App\Exceptions\Booking\BookingTimeConflictException;
use App\Exceptions\Booking\InvalidBookingExtensionException;
use App\Exceptions\Booking\InvalidBookingTimeException;
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
use Illuminate\Support\Facades\Log;

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

    public static function invalidCancellationStatusesProvider(): array
    {
        return [
            'cancelled booking' => [BookingStatus::Cancelled],
            'completed booking' => [BookingStatus::Completed],
        ];
    }

    public static function validTimeChangeStatusesProvider(): array
    {
        return [
            'pending booking' => [BookingStatus::Pending],
            'confirmed booking' => [BookingStatus::Confirmed],
        ];
    }

    public static function invalidTimeChangeStatusesProvider(): array
    {
        return [
            'cancelled booking' => [BookingStatus::Cancelled],
            'completed booking' => [BookingStatus::Completed],
        ];
    }

    public static function validExtensionStatusesProvider(): array
    {
        return [
            'pending booking' => [BookingStatus::Pending],
            'confirmed booking' => [BookingStatus::Confirmed],
        ];
    }

    public static function invalidExtensionStatusesProvider(): array
    {
        return [
            'cancelled booking' => [BookingStatus::Cancelled],
            'completed booking' => [BookingStatus::Completed],
        ];
    }

    public static function invalidExtensionEndProvider(): array
    {
        return [
            'same end time' => [
                Carbon::parse('2026-07-01 09:00'),
            ],
            'earlier end time' => [
                Carbon::parse('2026-07-01 08:00'),
            ],
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

    public function test_cancel_changes_pending_booking_status_to_cancelled(): void
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
        $service->cancel($booking);

        // Assert
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled,
        ]);
    }

    public function test_cancel_changes_confirmed_booking_status_to_cancelled(): void
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
            'status' => BookingStatus::Confirmed,
            'space_id' => $space->id,
        ]);

        // Act
        $service->cancel($booking);

        // Assert
        $this->assertSame(BookingStatus::Cancelled, $booking->status);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'status' => BookingStatus::Cancelled,
        ]);
    }

    #[DataProvider('invalidCancellationStatusesProvider')]
    public function test_cancel_throws_exception_when_the_booking_has_wrong_status(BookingStatus $status): void
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
        $this->expectException(BookingCancellationInvalidStatusException::class);

        // Act
        $service->cancel($booking);
    }

    #[DataProvider('validTimeChangeStatusesProvider')]
    public function test_change_time_updates_booking_for_pending_and_confirmed_statuses(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'status' => $status,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
            'total_price' => 100,
        ]);
        $newStart = Carbon::parse('2026-07-01 10:00');
        $newEnd = Carbon::parse('2026-07-01 12:00');

        $availabilityService = $this->createMock(AvailabilityService::class);
        $availabilityService
            ->expects($this->once())
            ->method('isAvailable')
            ->with(
                $this->callback(
                    fn (Space $actualSpace) => $actualSpace->id === $space->id
                ),
                $newStart,
                $newEnd,
                $booking->id,
            )
            ->willReturn(true);

        $priceCalculator = $this->createMock(BookingPriceCalculator::class);
        $priceCalculator
            ->expects($this->once())
            ->method('calculate')
            ->with(
                $this->callback(
                    fn (Space $actualSpace) => $actualSpace->id === $space->id
                ),
                $newStart,
                $newEnd,
            )
            ->willReturn(275.50);

        $service = new BookingService(
            $availabilityService,
            $priceCalculator,
            $this->createMock(InvoiceService::class),
        );

        // Act
        $service->changeTime($booking, $newStart, $newEnd);

        $booking->refresh();

        // Assert
        $this->assertEquals($newStart, $booking->start_time);
        $this->assertEquals($newEnd, $booking->end_time);
        $this->assertEquals(275.50, (float) $booking->total_price);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'start_time' => $newStart,
            'end_time' => $newEnd,
            'total_price' => 275.50,
        ]);
    }

    #[DataProvider('invalidTimeChangeStatusesProvider')]
    public function test_change_time_throws_exception_when_booking_has_invalid_status(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $service = new BookingService(
            $this->createMock(AvailabilityService::class),
            $this->createMock(BookingPriceCalculator::class),
            $this->createMock(InvoiceService::class),
        );
        $booking = Booking::factory()->create([
            'status' => $status,
            'space_id' => $space->id,
        ]);

        // Assert
        $this->expectException(BookingTimeChangeInvalidStatusException::class);

        // Act
        $service->changeTime(
            $booking,
            Carbon::parse('2026-07-01 10:00'),
            Carbon::parse('2026-07-01 12:00'),
        );
    }

    public function test_change_time_throws_exception_when_new_time_conflicts_with_another_booking(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
        ]);
        Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 10:00'),
            'end_time' => Carbon::parse('2026-07-01 12:00'),
        ]);

        $service = new BookingService(
            app(AvailabilityService::class),
            $this->createMock(BookingPriceCalculator::class),
            $this->createMock(InvoiceService::class),
        );

        // Assert
        $this->expectException(BookingTimeConflictException::class);

        // Act
        $service->changeTime(
            $booking,
            Carbon::parse('2026-07-01 11:00'),
            Carbon::parse('2026-07-01 13:00'),
        );
    }

    #[DataProvider('validExtensionStatusesProvider')]
    public function test_extend_updates_booking_for_pending_and_confirmed_statuses(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'status' => $status,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
            'total_price' => 100,
        ]);
        $newEnd = Carbon::parse('2026-07-01 12:00');

        $availabilityService = $this->createMock(AvailabilityService::class);
        $availabilityService
            ->expects($this->once())
            ->method('isAvailable')
            ->with(
                $this->callback(
                    fn ($actualSpace) => $actualSpace->id === $space->id
                ),
                $booking->start_time,
                $newEnd,
                $booking->id,
            )
            ->willReturn(true);

        $priceCalculator = $this->createMock(BookingPriceCalculator::class);
        $priceCalculator
            ->expects($this->once())
            ->method('calculate')
            ->with(
                $this->callback(
                    fn ($actualSpace) => $actualSpace->id === $space->id
                ),
                $booking->start_time,
                $newEnd,
            )
            ->willReturn(275.50);

        $service = new BookingService(
            $availabilityService,
            $priceCalculator,
            $this->createMock(InvoiceService::class),
        );

        // Act
        $service->extend($booking, $newEnd);
        $booking->refresh();

        // Assert
        $this->assertEquals($newEnd, $booking->end_time);
        $this->assertEquals(275.50, (float) $booking->total_price);
        $this->assertDatabaseHas('bookings', [
            'id' => $booking->id,
            'end_time' => $newEnd,
            'total_price' => 275.50,
        ]);
    }

    #[DataProvider('invalidExtensionEndProvider')]
    public function test_extend_throws_exception_when_new_end_is_not_later_than_current_end(Carbon $newEnd): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
        ]);
        $availabilityService = $this->createMock(AvailabilityService::class);
        $availabilityService->expects($this->never())->method('isAvailable');
        $priceCalculator = $this->createMock(BookingPriceCalculator::class);
        $priceCalculator->expects($this->never())->method('calculate');

        $service = new BookingService(
            $availabilityService,
            $priceCalculator,
            $this->createMock(InvoiceService::class),
        );

        // Assert
        $this->expectException(InvalidBookingExtensionException::class);

        // Act
        $service->extend(
            $booking,
            $newEnd,
        );
    }

    #[DataProvider('invalidExtensionStatusesProvider')]
    public function test_extend_throws_exception_when_booking_has_invalid_status(BookingStatus $status): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $service = new BookingService(
            $this->createMock(AvailabilityService::class),
            $this->createMock(BookingPriceCalculator::class),
            $this->createMock(InvoiceService::class),
        );
        $booking = Booking::factory()->create([
            'status' => $status,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
        ]);

        // Assert
        $this->expectException(BookingExtensionInvalidStatusException::class);

        // Act
        $service->extend($booking, Carbon::parse('2026-07-01 12:00'));
    }

    public function test_extend_throws_exception_when_new_end_time_conflicts_with_another_booking(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'status' => BookingStatus::Pending,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 08:00'),
            'end_time' => Carbon::parse('2026-07-01 09:00'),
        ]);
        Booking::factory()->create([
            'status' => BookingStatus::Confirmed,
            'space_id' => $space->id,
            'start_time' => Carbon::parse('2026-07-01 10:00'),
            'end_time' => Carbon::parse('2026-07-01 12:00'),
        ]);

        $service = new BookingService(
            app(AvailabilityService::class),
            $this->createMock(BookingPriceCalculator::class),
            $this->createMock(InvoiceService::class),
        );

        // Assert
        $this->expectException(BookingTimeConflictException::class);

        // Act
        $service->extend($booking, Carbon::parse('2026-07-01 11:00'));
    }
}
