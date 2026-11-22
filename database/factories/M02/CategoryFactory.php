<?php

namespace Database\Factories\M02;

use App\Models\M02\Category;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Category> */
class CategoryFactory extends Factory
{
    protected $model = Category::class;

    public function definition(): array
    {
        $name = 'Danh mục '.fake()->unique()->numerify('####');

        return ['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'parent_id' => null, 'position' => 0];
    }

    public function childOf(Category $parent): static
    {
        return $this->state(fn () => ['parent_id' => $parent->id]);
    }
}
