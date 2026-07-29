<?php

namespace App\Support\SeedData;

class Categories
{
    public static function list(): array
    {

        return [
            [
                'short_code' => 'open_space',
                'name' => 'Open Space',
                'description' => 'Shared workspace with multiple desks.',
                'booking_buffer_minutes' => 0,
            ],
            [
                'short_code' => 'meeting_room',
                'name' => 'Meeting Room',
                'description' => 'Private room for meetings.',
                'booking_buffer_minutes' => 15,
            ],
            [
                'short_code' => 'private_office',
                'name' => 'Private Office',
                'description' => 'Separate office for small teams.',
                'booking_buffer_minutes' => 15,
            ],
            [
                'short_code' => 'conference_room',
                'name' => 'Conference Room',
                'description' => 'Large room for conferences and presentations.',
                'booking_buffer_minutes' => 20,
            ],
            [
                'short_code' => 'phone_booth',
                'name' => 'Phone Booth',
                'description' => 'Small soundproof booth for calls.',
                'booking_buffer_minutes' => 5,
            ],
            [
                'short_code' => 'training_room',
                'name' => 'Training Room',
                'description' => 'Room for workshops and training sessions.',
                'booking_buffer_minutes' => 10,
            ],
            [
                'short_code' => 'event_space',
                'name' => 'Event Space',
                'description' => 'Space for meetups and events.',
                'booking_buffer_minutes' => 20,
            ],
        ];

    }
}
