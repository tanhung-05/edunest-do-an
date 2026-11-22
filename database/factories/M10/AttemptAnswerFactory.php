<?php

namespace Database\Factories\M10;

use App\Models\M04\QuizQuestion;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Support\M10\QuizScore;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<AttemptAnswer> */
final class AttemptAnswerFactory extends Factory
{
    protected $model = AttemptAnswer::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'quiz_attempt_id' => QuizAttemptFactory::new(),
            'quiz_question_id' => fn (array $a) => QuizAttempt::query()->whereKey($a['quiz_attempt_id'])->firstOrFail()
                ->quiz()->firstOrFail()->items()->firstOrFail()->id,
            'answer' => null, 'received_at' => now(),
        ];
    }

    public function correct(): static
    {
        return $this->state(fn () => [
            'answer' => fn (array $a) => array_column(array_filter(QuizScore::options(
                QuizQuestion::query()->whereKey($a['quiz_question_id'])->firstOrFail(),
            ), fn (array $option) => $option['is_correct']), 'id'),
            'is_correct' => true,
            'points_awarded' => fn (array $a) => QuizQuestion::query()->whereKey($a['quiz_question_id'])->firstOrFail()->points,
        ]);
    }
}
