<?php

namespace App\Queries\M10;

use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Support\M10\QuizScore;

final class AttemptDetails
{
    /** @return array<string, mixed> */
    public static function summary(QuizAttempt $attempt): array
    {
        return [
            'id' => $attempt->id,
            'attempt_no' => $attempt->attempt_no,
            'status' => $attempt->status->value,
            'status_label' => $attempt->status->label(),
            'close_reason' => $attempt->close_reason?->label(),
            'started_at' => $attempt->started_at->toISOString(),
            'deadline_at' => $attempt->deadline_at->toISOString(),
            'closed_at' => $attempt->closed_at?->toISOString(),
            'score_earned' => $attempt->score_earned,
            'score_max' => $attempt->score_max,
            'percent' => QuizScore::display(QuizScore::ratio($attempt)),
            'passed' => $attempt->passed,
            'url' => route('student.attempts.result', $attempt),
            // QT-10 A1, AC-10-09/37: số câu đúng / tổng câu đã chụp của lượt đã chốt.
            'correct_count' => $attempt->closed_at === null ? null : AttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->where('is_correct', true)->count(),
            'question_count' => $attempt->quiz()->firstOrFail()->items()->count(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function questions(QuizAttempt $attempt, bool $reveal, ?\Closure $saveUrl = null): array
    {
        $saveUrl ??= fn ($question) => route('student.attempts.answers.update', ['attempt' => $attempt->id, 'quizQuestion' => $question->id]);
        $answers = AttemptAnswer::query()->where('quiz_attempt_id', $attempt->id)->get()->keyBy('quiz_question_id');
        $quiz = $attempt->quiz()->firstOrFail();

        return array_values($quiz->items()->get()->map(function ($question) use ($answers, $reveal, $attempt, $saveUrl) {
            $answer = $answers->get($question->id);
            $options = QuizScore::options($question);
            $type = $question->snapshot['type'] ?? null;
            $public = array_map(fn ($option) => ['id' => $option['id'], 'content' => $option['content']], $options);
            if (! $reveal && $type === 'DIEN_TU') {
                // M04 (N): đáp án chấp nhận của câu điền từ không lộ trước khi chốt.
                $public = [];
            } elseif (! $reveal && $type === 'SAP_XEP') {
                // Thứ tự xáo trộn cố định theo lượt, không trùng thứ tự đúng khi có thể.
                usort($public, fn ($a, $b) => crc32($attempt->id.':'.$a['id']) <=> crc32($attempt->id.':'.$b['id']));
                if (count($public) > 1 && array_column($public, 'id') === array_column($options, 'id')) {
                    $public = array_reverse($public);
                }
            }

            return [
                'id' => $question->id,
                'stem' => $question->snapshot['stem'] ?? null,
                'type' => $question->snapshot['type'] ?? null,
                'choices' => $reveal ? $options : $public,
                'answer' => $answer?->answer,
                'save_url' => $saveUrl($question),
                ...($reveal ? ['explanation' => $question->snapshot['explanation'] ?? null, 'correct' => $answer->is_correct ?? false] : []),
            ];
        })->all());
    }
}
