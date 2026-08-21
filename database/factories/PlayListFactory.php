<?php

namespace Database\Factories;

use App\Models\Media;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PlayList>
 */
class PlayListFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->sentence(4),
            'description' => $this->faker->sentence(10),
            'status' => $this->faker->boolean(),
            'duration' => rand(5, 20) . ':' . rand(1, 59),
            'media_id' => null,
            'category_id' => 1, // update when run seeder
            'albam_id' => 1, // update when run seeder
            'audio_id' => Media::factory()->create([
                'src' => 'audio/dummy-audio.mp3'
            ])
        ];
    }
}
