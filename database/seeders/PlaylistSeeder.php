<?php

namespace Database\Seeders;

use App\Models\PlayList;
use App\Repositories\AlbamRepository;
use Illuminate\Database\Seeder;

class PlaylistSeeder extends Seeder
{
    /**
     * Run the database seeds.
     *
     * @return void
     */
    public function run()
    {
        $albams = (new AlbamRepository())->getAll();

        foreach($albams as $albam){
            for($i=0; $i < rand(10, 50); $i++){
                PlayList::factory()->create([
                    'category_id' => $albam->category_id,
                    'albam_id' => $albam->id,
                ]);
            }
        }
    }
}
