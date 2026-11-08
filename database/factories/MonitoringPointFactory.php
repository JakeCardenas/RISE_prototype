<?php

namespace Database\Factories;

use App\Models\MonitoringPoint;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<MonitoringPoint>
 */
class MonitoringPointFactory extends Factory
{
    /**
     * Define the model's default state. Labels are synthetic sample data.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'label' => fake()->randomElement([
                'Bedroom wall beside the window',
                'Kitchen wall above the sink',
                'Hallway ceiling corner',
                'Living room wall by the door frame',
            ]),
            'surface_description' => fake()->optional()->randomElement([
                'Painted plaster, thin diagonal crack from the window corner',
                'Concrete block wall, crack along the mortar line',
            ]),
        ];
    }
}
