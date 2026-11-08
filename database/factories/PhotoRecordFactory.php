<?php

namespace Database\Factories;

use App\Models\MonitoringPoint;
use App\Models\PhotoRecord;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<PhotoRecord>
 */
class PhotoRecordFactory extends Factory
{
    /**
     * Define the model's default state. No image file is written; tests put one on a fake disk when needed.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'monitoring_point_id' => MonitoringPoint::factory(),
            'user_id' => fn (array $attributes) => MonitoringPoint::find($attributes['monitoring_point_id'])->user_id,
            'image_disk' => 'local',
            'image_path' => fn (array $attributes) => "photo-records/{$attributes['user_id']}/".Str::random(40).'.jpg',
            'image_mime' => 'image/jpeg',
            'image_size' => 150_000,
            'image_width' => 1200,
            'image_height' => 900,
            'note' => null,
            'photo_taken_at' => null,
            'photo_taken_offset' => null,
            'file_modified_at' => null,
            'submitted_at' => now(),
        ];
    }
}
