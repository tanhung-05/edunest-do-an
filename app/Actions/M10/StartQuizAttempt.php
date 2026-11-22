<?php

namespace App\Actions\M10;

use App\Enums\M04\QuizKind;
use App\Enums\M05\LessonType;
use App\Enums\M09\Activity;
use App\Enums\M10\AttemptStatus;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Queries\M10\QuizAttempts;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use App\Support\M09\LearningLocks;
use App\Support\M10\QuizScore;
use App\Support\TestBarrier;
use App\Support\Tx;
use Brick\Math\BigDecimal;

final class StartQuizAttempt
{
    public function __construct(private ContinueQuizAttempt $continue) {}

    public function handle(User $user, Enrollment $enrollment, Quiz $quiz): QuizAttempt
    {
        $result = Tx::run(function () use ($user, $enrollment, $quiz) {
            [$user, $enrollment] = LearningLocks::student($user, $enrollment);
            TestBarrier::reach('start-quiz-attempt.enrollment-locked');
            $lesson = $quiz->lesson;
            if (! LearningAccess::owns($user, $enrollment) || $lesson === null
                || ! LearningAccess::matches($enrollment, $lesson) || $quiz->course_version_id !== $enrollment->course_version_id
                || $lesson->type !== LessonType::Quiz || $quiz->kind !== QuizKind::BaiHoc) {
                throw DomainRejection::forbidden('Bạn không có quyền mở quiz này.');
            }
            $attempts = QuizAttempts::for($enrollment, $quiz);
            $open = (clone $attempts)->where('status', AttemptStatus::DangLam)->lockForUpdate()->first();
            $at = now()->floorMillisecond();
            if ($open !== null) {
                return $this->continue->underLock($user, $enrollment, $open, $lesson, $at);
            }
            LearningAccess::authorize($user, $enrollment, Activity::StartQuizAttempt, $lesson, at: $at);
            if ((clone $attempts)->where('passed', true)->exists()) {
                throw new DomainRejection('Bạn đã đạt quiz này.');
            }
            if ($attempts->count() >= QuizAttempts::limit($enrollment, $quiz)) {
                throw new DomainRejection('Bạn đã hết lượt.');
            }
            $questions = $quiz->items()->get();
            if ($quiz->duration_minutes === null || $quiz->duration_minutes < 1 || $quiz->pass_percent === null || $questions->isEmpty()
                || $questions->contains(fn ($question) => $question->points === null || ! BigDecimal::of($question->points)->isPositive()
                    || $question->snapshot === null || QuizScore::options($question) === [])) {
                throw new DomainRejection('Quiz chưa đủ cấu hình hoặc bản chụp câu hỏi.');
            }

            return QuizAttempt::query()->create([
                'enrollment_id' => $enrollment->id,
                'quiz_id' => $quiz->id,
                'user_id' => $user->id,
                'attempt_no' => ((int) $attempts->max('attempt_no')) + 1,
                'started_at' => $at,
                'deadline_at' => $at->addMinutes($quiz->duration_minutes),
            ]);
        });

        if ($result instanceof DomainRejection) {
            throw $result;
        }

        return $result;
    }
}
