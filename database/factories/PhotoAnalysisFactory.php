<?php

namespace Database\Factories;

use App\Models\PhotoAnalysis;
use App\Models\PhotoRecord;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PhotoAnalysis>
 */
class PhotoAnalysisFactory extends Factory
{
    /**
     * Define the model's default state: a request still waiting for the queue worker.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'photo_record_id' => PhotoRecord::factory(),
            'user_id' => fn (array $attributes) => PhotoRecord::find($attributes['photo_record_id'])->user_id,
            'status' => PhotoAnalysis::PENDING,
            'provider' => 'roboflow',
            'model_id' => 'concrete-crack-ver.1/2',
            'confidence_threshold' => 0.4,
            'requested_at' => now(),
        ];
    }
}
