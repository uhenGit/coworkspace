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
            ],
            [
                'short_code' => 'meeting_room',
                'name' => 'Meeting Room',
                'description' => 'Private room for meetings.',
            ],
            [
                'short_code' => 'private_office',
                'name' => 'Private Office',
                'description' => 'Separate office for small teams.',
            ],
            [
                'short_code' => 'conference_room',
                'name' => 'Conference Room',
                'description' => 'Large room for conferences and presentations.',
            ],
            [
                'short_code' => 'phone_booth',
                'name' => 'Phone Booth',
                'description' => 'Small soundproof booth for calls.',
            ],
            [
                'short_code' => 'training_room',
                'name' => 'Training Room',
                'description' => 'Room for workshops and training sessions.',
            ],
            [
                'short_code' => 'event_space',
                'name' => 'Event Space',
                'description' => 'Space for meetups and events.',
            ],
        ];

    }
}
