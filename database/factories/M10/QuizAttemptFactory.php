<?php

namespace Database\Factories\M10;

use App\Enums\M10\AttemptCloseReason;
use App\Enums\M10\AttemptStatus;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use Brick\Math\BigDecimal;
use Database\Factories\M09\LearningEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<QuizAttempt> */
final class QuizAttemptFactory extends Factory
{
    protected $model = QuizAttempt::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'enrollment_id' => LearningEnrollmentFactory::new(),
            'user_id' => fn (array $a) => Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail()->user_id,
            'quiz_id' => fn (array $a) => LearningEnrollmentFactory::quiz(Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail())->id,
            'attempt_no' => fn (array $a) => (int) QuizAttempt::query()->where('enrollment_id', $a['enrollment_id'])
                ->where('quiz_id', $a['quiz_id'])->max('attempt_no') + 1,
            'status' => AttemptStatus::DangLam, 'started_at' => now(), 'deadline_at' => now()->addMinutes(10),
        ];
    }

    public function closed(string $earned = '0.0000', AttemptCloseReason $reason = AttemptCloseReason::Nop): static
    {
        return $this->state(fn () => [
            'status' => $reason === AttemptCloseReason::HetGio ? AttemptStatus::HetGio : AttemptStatus::DaNop,
            'close_reason' => $reason, 'closed_at' => now(), 'finalized_at' => now(),
            'started_at' => now()->subMinutes(10), 'deadline_at' => now(),
            'score_earned' => $earned, 'score_max' => '1.0000', 'passed' => BigDecimal::of($earned)->isGreaterThanOrEqualTo('0.7000'),
        ]);
    }
}
