<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\SubscriptionPlan>
 */
class SubscriptionPlanFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition()
    {
        return [
            'name' => $this->faker->sentence(),
            'feature_1' => $this->faker->sentence(),
            'feature_2' => $this->faker->sentence(),
            'feature_3' => $this->faker->sentence(),
            'feature_4' => $this->faker->sentence(),
            'duration' => 1,
            'amount' => $this->faker->randomFloat(2, 100, 500)
        ];
    }
}
