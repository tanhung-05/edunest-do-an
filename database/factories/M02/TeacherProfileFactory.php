<?php

namespace Database\Factories\M02;

use App\Models\M02\TeacherProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<TeacherProfile> */
class TeacherProfileFactory extends Factory
{
    protected $model = TeacherProfile::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->teacher(),
            'bio' => fake()->sentence(),
            'achievements' => fake()->sentence(),
            'created_by_id' => User::factory()->academic(),
        ];
    }
}
