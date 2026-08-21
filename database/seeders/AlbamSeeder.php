<?php

namespace Database\Seeders;

use App\Models\Albam;
use App\Repositories\CategoryRepository;
use Illuminate\Database\Seeder;

class AlbamSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $cetegories = (new CategoryRepository())->getAll();

        foreach($cetegories as $cetegory){
            for($i=0; $i < rand(10, 50); $i++){
                Albam::factory()->create([
                    'category_id' => $cetegory->id
                ]);
            }
        }
    }
}
