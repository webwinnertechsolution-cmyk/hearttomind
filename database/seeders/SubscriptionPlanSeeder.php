<?php

namespace Database\Seeders;

use App\Models\SubscriptionPlan;
use Illuminate\Database\Seeder;

class SubscriptionPlanSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $durations = [6,12,24];

        for($i=0; $i < count($durations); $i++){
            SubscriptionPlan::factory()->create([
                'duration' => $durations[$i]
            ]);
        }
    }
}
