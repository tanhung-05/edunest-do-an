<?php

namespace Database\Factories\M10;

use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttemptGrant;
use App\Models\User;
use Database\Factories\M09\LearningEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizAttemptGrant> */
final class QuizAttemptGrantFactory extends Factory
{
    protected $model = QuizAttemptGrant::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'enrollment_id' => LearningEnrollmentFactory::new(),
            'quiz_id' => fn (array $a) => LearningEnrollmentFactory::quiz(Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail())->id,
            'granted_by_id' => User::factory(), 'extra_attempts' => 1,
            'reason' => 'Cho làm lại sau khi ôn.', 'created_at' => now(),
        ];
    }
}
