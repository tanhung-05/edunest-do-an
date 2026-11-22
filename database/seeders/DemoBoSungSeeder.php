<?php

namespace Database\Seeders;

use App\Actions\M09\MarkLessonCompleted;
use App\Actions\M10\StartQuizAttempt;
use App\Enums\M05\LessonType;
use App\Enums\M10\AttemptStatus;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M08\Enrollment;
use App\Models\M10\QuizAttempt;
use App\Models\M20\PracticeActivity;
use App\Models\M20\PracticeSession;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M09\LearningAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * P9.3 — bổ sung cho kịch bản demo §14: `an.nguyen` có dữ liệu XP 30 ngày (có ba ngày nghỉ để lịch học có khoảng trống,
 * chuỗi tới hôm nay 9 ngày) và một lượt quiz đã quá giờ còn để mở (mở lại thấy tự chốt, hoặc TV chốt lượt quá hạn).
 * Chạy lại không tạo trùng.
 */
final class DemoBoSungSeeder extends Seeder
{
    public function run(): void
    {
        $an = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->whereNotNull('current_version_id')->oldest('id')->first();
        if ($an === null || $course === null) {
            return;
        }
        $this->xp($an, $course);
        $this->overdueAttempt($an, $course);
        Carbon::setTestNow();
    }

    private function xp(User $an, Course $course): void
    {
        $activity = PracticeActivity::query()->where('course_id', $course->id)->orderBy('id')->first();
        if ($activity === null || PracticeSession::query()->where('user_id', $an->id)->where('created_at', '<', now()->subDays(7))->exists()) {
            return;
        }
        $seeder = new PracticeDemoSeeder;
        $evening = CarbonImmutable::now('Asia/Ho_Chi_Minh')->setTime(20, 0);
        foreach (range(29, 1) as $daysAgo) {
            if (in_array($daysAgo, [22, 15, 9], true)) {
                continue;
            }
            $seeder->session($an, $activity, $evening->subDays($daysAgo)->utc(), $daysAgo % 4);
        }
    }

    private function overdueAttempt(User $an, Course $course): void
    {
        if (QuizAttempt::query()->where('user_id', $an->id)->where('status', AttemptStatus::DangLam)->exists()) {
            return;
        }
        // Lấy quiz đầu tiên học viên đang được mở (khóa tuần tự chặn quiz khi chưa xong bài trước).
        foreach (Enrollment::query()->where('user_id', $an->id)->orderByRaw('course_id = ? desc', [$course->id])->orderBy('id')->get() as $enrollment) {
            $quizzes = Quiz::query()->whereHas('lesson', fn ($q) => $q->where('course_version_id', $enrollment->course_version_id)->whereNull('removed_at'))
                ->whereNotNull('duration_minutes')->orderBy('id')->get();
            foreach ($quizzes as $quiz) {
                Carbon::setTestNow(CarbonImmutable::now()->subMinutes(((int) $quiz->duration_minutes) + 40));
                try {
                    // Khóa tuần tự: đọc xong các bài đọc bắt buộc đứng trước quiz (giống học viên tự học).
                    foreach (LearningAccess::lessons($enrollment) as $lesson) {
                        if ($lesson->id === $quiz->lesson_id) {
                            break;
                        }
                        if ($lesson->is_required && $lesson->type === LessonType::BaiDoc) {
                            app(MarkLessonCompleted::class)->handle($an, $enrollment, $lesson);
                        }
                    }
                    Carbon::setTestNow(CarbonImmutable::now()->addMinutes(5));
                    app(StartQuizAttempt::class)->handle($an, $enrollment, $quiz);

                    return;
                } catch (DomainRejection) {
                    Carbon::setTestNow();
                }
            }
        }
    }
}
