<?php

namespace App\Actions\M10;

use App\Enums\M10\AttemptStatus;
use App\Models\M04\QuizQuestion;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M10\AttemptAnswer;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M10\LockedQuizAttempt;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;

final class SaveAttemptAnswer
{
    public function __construct(private FinalizeAttempt $finalize) {}

    public function handle(User $user, QuizAttempt $attempt, QuizQuestion $question, mixed $value): AttemptAnswer
    {
        // ERD L26: received_at là giờ máy chủ nhận câu (trước khi chờ khóa); xét hạn dùng giờ dưới khóa.
        $receivedAt = now()->floorMillisecond();

        return LockedQuizAttempt::run($user, $attempt, function (User $user, Enrollment $enrollment, QuizAttempt $attempt, Lesson $lesson, CarbonImmutable $at) use ($question, $value, $receivedAt) {
            $this->finalize->underLock($enrollment, $attempt, $at);
            if ($attempt->status !== AttemptStatus::DangLam) {
                return new DomainRejection('Lượt đã chốt, câu trả lời chưa được lưu.', 'answer');
            }
            if (! LockedQuizAttempt::mayContinue($user, $enrollment, $attempt, $lesson, $at)) {
                throw DomainRejection::forbidden('Bạn không có quyền tiếp tục lượt này.');
            }
            if ($question->quiz_id !== $attempt->quiz_id || $question->removed_at !== null || $question->snapshot === null) {
                throw DomainRejection::forbidden('Câu hỏi không thuộc lượt này.');
            }
            $answer = QuizScore::answer($question, $value);
            if ($answer === null && $value !== null) {
                throw new DomainRejection('Câu trả lời không hợp lệ.', 'answer');
            }

            return AttemptAnswer::query()->updateOrCreate(
                ['quiz_attempt_id' => $attempt->id, 'quiz_question_id' => $question->id],
                ['answer' => $answer, 'received_at' => $receivedAt],
            );
        });
    }
}
