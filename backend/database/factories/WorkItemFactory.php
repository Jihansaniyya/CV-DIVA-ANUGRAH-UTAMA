<?php

namespace Database\Factories;

use App\Models\Project;
use App\Models\Unit;
use App\Models\WorkItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<WorkItem> */
class WorkItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'project_id' => Project::factory(),
            'unit_id' => Unit::firstOrCreate(['code' => 'm3'], ['name' => 'Meter Kubik'])->id,
            'uraian_pekerjaan' => 'Pekerjaan '.fake()->unique()->words(2, true),
            'volume' => 100,
            'harga_satuan' => 100000,
            'urutan' => 1,
        ];
    }
}
