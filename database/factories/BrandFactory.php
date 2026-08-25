<?php

namespace Database\Factories;

use App\Models\Brand;
use App\Models\Team;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Brand>
 */
class BrandFactory extends Factory
{
    protected $model = Brand::class;

    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'team_id' => Team::factory(),
            'name' => $name,
            'slug' => Str::slug($name),
            'status' => true,
        ];
    }
}
