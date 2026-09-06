<?php

namespace Tests\Unit;

use App\Enums\BookingStatus;
use App\Exceptions\Booking\BookingTimeConflictException;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Space;
use App\Models\User;
use App\Data\ReserveBookingData;
use App\Services\Booking\BookingService;
use Database\Seeders\CategorySeeder;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BookingServiceTest extends TestCase
{
    use RefreshDatabase;

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
            'user_id' => $booking->user_id,
            'space_id' => $booking->space_id,
            'start_time' => $booking->start_time,
            'end_time' => $booking->end_time,
            'notes' => 'success test',
            'status' => BookingStatus::Pending,
            'total_price' => $booking->total_price,
        ]);
    }
}
