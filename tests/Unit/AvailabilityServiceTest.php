<?php

namespace Tests\Unit;

use App\Data\BookingSlotGenerator;
use App\Enums\BookingStatus;
use App\Models\Booking;
use App\Models\Category;
use App\Models\Space;
use App\Services\Booking\AvailabilityService;
use Carbon\Carbon;
use Database\Seeders\CategorySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AvailabilityServiceTest extends TestCase
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

    /**
     * A basic unit tests to check spaces availability
     */
    public function test_space_is_available_when_no_bookings_exist(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer();

        $start = Carbon::parse('12:00');
        $end = Carbon::parse('14:00');

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertTrue($isAvailable);
    }

    public function test_space_is_not_available_when_booking_fully_overlaps(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('10:00');
        $end = Carbon::parse('12:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertFalse($isAvailable);
    }

    public function test_space_is_not_available_when_new_booking_inside_existed(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('11:00');
        $end = Carbon::parse('13:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => Carbon::parse('10:00'),
            'end_time' => Carbon::parse('14:00'),
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertFalse($isAvailable);
    }

    public function test_space_is_not_available_when_new_booking_overlaps_right(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('10:00');
        $end = Carbon::parse('14:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => Carbon::parse('11:00'),
            'end_time' => Carbon::parse('13:00'),
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertFalse($isAvailable);
    }

    public function test_space_is_not_available_when_new_booking_overlap_left(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('08:00');
        $end = Carbon::parse('11:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => Carbon::parse('10:00'),
            'end_time' => Carbon::parse('14:00'),
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertFalse($isAvailable);
    }

    public function test_space_is_available_when_new_booking_after_existed(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('14:15');
        $end = Carbon::parse('16:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => Carbon::parse('10:00'),
            'end_time' => Carbon::parse('14:00'),
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertTrue($isAvailable);
    }

    public function test_space_is_available_when_new_booking_before_existed(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $start = Carbon::parse('08:00');
        $end = Carbon::parse('10:00');
        $space = $this->createSpaceWithBuffer();
        $booking = Booking::factory()->create([
            'start_time' => Carbon::parse('10:00'),
            'end_time' => Carbon::parse('14:00'),
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space, $start, $end);

        // Assert
        $this->assertTrue($isAvailable);
    }

    public function test_space_is_available_when_booking_belongs_to_different_space(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space_one = $this->createSpaceWithBuffer(15, 'event_space');
        $space_two = $this->createSpaceWithBuffer(15, 'open_space');
        $start = Carbon::parse('10:00');
        $end = Carbon::parse('14:00');
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space_two->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $isAvailable = $service->isAvailable($space_one, $start, $end);

        // Assert
        $this->assertTrue($isAvailable);
    }

    public function test_booking_slot_generator_returns_list_of_slots(): void
    {
        // Arrange
        $day = Carbon::parse('2026-01-09');
        $generator = app(BookingSlotGenerator::class);

        // Act
        $slots = $generator->generate($day);

        // Assert
        $this->assertCount(13, $slots);
        $this->assertSame('08:00', $slots[0]['start']->format('H:i'));
        $this->assertSame('09:00', $slots[0]['end']->format('H:i'));

        $this->assertSame('20:00', $slots[12]['start']->format('H:i'));
        $this->assertSame('21:00', $slots[12]['end']->format('H:i'));
    }

    public function test_all_the_slots_are_available_if_there_are_no_bookings(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer(0);

        $day = Carbon::parse('2026-09-01');

        $service = app(AvailabilityService::class);

        // Act
        $slots = $service->getAvailableSlots($space, $day);

        // Assert
        $this->assertCount(13, $slots);
        $this->assertSame('08:00', $slots[0]['start']->format('H:i'));
        $this->assertSame('09:00', $slots[0]['end']->format('H:i'));

        $this->assertSame('20:00', $slots[12]['start']->format('H:i'));
        $this->assertSame('21:00', $slots[12]['end']->format('H:i'));
    }

    public function test_some_spaces_are_not_available_when_bookings_exist(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer(0);

        $day = Carbon::parse('2026-09-01');
        $start = Carbon::parse('2026-09-01 10:00');
        $end = Carbon::parse('2026-09-01 14:00');
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $slots = $service->getAvailableSlots($space, $day);

        // Assert
        $this->assertCount(9, $slots);
    }

    public function test_slots_ending_at_booking_start_and_starting_at_booking_end_are_available(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer(0);

        $day = Carbon::parse('2026-09-01');
        $start = Carbon::parse('2026-09-01 10:00');
        $end = Carbon::parse('2026-09-01 12:00');
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $slots = $service->getAvailableSlots($space, $day);
        $lastSlotBeforeBooking = array_find($slots, function ($slot) use ($start) {
            return $slot['end']->eq($start);
        });
        $firstSlotAfterBooking = array_find($slots, function ($slot) use ($end) {
            return $slot['start']->eq($end);
        });
        $occupiedSlots = array_filter($slots, function ($slot) use ($start, $end) {
            return $slot['start']->gte($start)
                && $slot['end']->lte($end);
        });

        // Assert
        $this->assertNotNull($lastSlotBeforeBooking);
        $this->assertNotNull($firstSlotAfterBooking);
        $this->assertEmpty($occupiedSlots);
    }

    public function test_cancelled_booking_status_not_blocking_slots(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer(0);

        $day = Carbon::parse('2026-09-01');
        $start = Carbon::parse('2026-09-01 10:00');
        $end = Carbon::parse('2026-09-01 12:00');
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Cancelled,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $slots = $service->getAvailableSlots($space, $day);
        $occupiedSlots = array_filter($slots, function ($slot) use ($start, $end) {
            return $slot['start']->gte($start)
                && $slot['end']->lte($end);
        });

        // Assert
        $this->assertCount(2, $occupiedSlots);
        $this->assertCount(13, $slots);
    }

    public function test_using_buffer_exclude_previous_slot(): void
    {
        // Arrange
        $this->seed(CategorySeeder::class);

        $space = $this->createSpaceWithBuffer(15);

        $day = Carbon::parse('2026-09-01');
        $start = Carbon::parse('2026-09-01 10:00');
        $end = Carbon::parse('2026-09-01 12:00');
        $booking = Booking::factory()->create([
            'start_time' => $start,
            'end_time' => $end,
            'space_id' => $space->id,
            'status' => BookingStatus::Confirmed,
        ]);

        $service = app(AvailabilityService::class);

        // Act
        $slots = $service->getAvailableSlots($space, $day);

        // Asssert
        $this->assertCount(10, $slots);
    }
}
