<?php

namespace App\Queries\M23;

use App\Actions\M21\XpLedger;
use App\Enums\M10\AttemptStatus;
use App\Enums\M18\ExamStatus;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamRanking;
use App\Models\User;
use App\Queries\M10\QuizAttempts;
use App\Queries\M12\EnrollmentOverview;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;

/**
 * QT-25 bước 7, A3: năm nhóm dữ liệu Phụ huynh được xem về con (BR-MR-05/06): tiến độ, điểm quiz, kết quả thi thử
 * theo quy tắc công bố, chuỗi ngày học, thời hạn khóa. Không có đáp án, lời giải, chứng nhận, đơn, ghi chú, hỏi đáp.
 */
final class ChildReport
{
    /** @return array{student: array{id: int, name: string}, streak: int, courses: list<array<string, mixed>>, exams: list<array<string, mixed>>} */
    public static function for(User $student, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now();
        $courses = [];
        $enrollments = Enrollment::query()->where('user_id', $student->id)->whereNull('revoked_at')->with('course')->orderBy('id')->get();
        foreach ($enrollments as $enrollment) {
            $quizzes = [];
            foreach (LearningAccess::lessons($enrollment)->load('quiz') as $lesson) {
                if ($lesson->quiz === null) {
                    continue;
                }
                $quizzes[] = ['title' => $lesson->title, 'best_percent' => QuizScore::display(QuizAttempts::best($enrollment, $lesson->quiz))];
            }
            $courses[] = [
                'id' => $enrollment->id, 'course' => $enrollment->course?->title, ...EnrollmentOverview::progress($enrollment),
                'validity' => EnrollmentValidityChecker::for($enrollment, $at)->label(),
                'expires_at' => $enrollment->het_han_luc?->toISOString(), 'quizzes' => $quizzes,
            ];
        }

        $exams = [];
        $attempts = QuizAttempt::query()->where('user_id', $student->id)->whereNotNull('exam_event_id')
            ->where('status', '!=', AttemptStatus::DangLam)->orderByDesc('id')->limit(20)->get();
        foreach ($attempts as $attempt) {
            $event = ExamEvent::query()->find($attempt->exam_event_id);
            if ($event === null) {
                continue;
            }
            $published = $event->status === ExamStatus::DaCongBo;
            $rank = $published ? ExamRanking::query()->where('exam_event_id', $event->id)->where('user_id', $student->id)->value('rank') : null;
            $exams[] = ['title' => $event->title, 'published' => $published,
                'score_percent' => $published ? QuizScore::display(QuizScore::ratio($attempt)) : null, 'rank' => $rank];
        }

        return ['student' => ['id' => $student->id, 'name' => $student->name], 'streak' => XpLedger::currentStreak($student->id, $at),
            'courses' => $courses, 'exams' => $exams];
    }
}
