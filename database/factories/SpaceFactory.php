<?php

namespace Database\Factories;

use App\Models\Category;
use App\Models\Space;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Space>
 */
class SpaceFactory extends Factory
{
    // protected static ?Collection $categories = null;
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $roomNames = ['Room A', 'Meeting Room 1', 'Small Conference hall', 'Luxury Space', 'Cheep Variant'];
        $availableFeatures = [
            'WiFi',
            'Coffee',
            'Projector',
            'Whiteboard',
            'Air Conditioning',
            'Parking',
            'Printer',
        ];

        return [
            'category_id' => Category::inRandomOrder()->value('id'),
            'title' => fake()->randomElement($roomNames),
            'description' => fake()->paragraph(),
            'capacity' => fake()->numberBetween(2, 40),
            'price_per_hour' => fake()->randomFloat(2, 10, 200),
            'is_active' => fake()->boolean(80),
            'image' => null,
            'features' => fake()->optional(0.9)->randomElements(
                $availableFeatures,
                random_int(2, 5)
            ),
        ];
    }
}
