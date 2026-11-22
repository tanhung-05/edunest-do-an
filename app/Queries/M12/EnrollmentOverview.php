<?php

namespace App\Queries\M12;

use App\Enums\M10\AttemptStatus;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Queries\M10\QuizAttempts;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\M10\QuizScore;
use Brick\Math\BigRational;

final class EnrollmentOverview
{
    /** @return array{completed: int, required: int, percent: string|null} */
    public static function progress(Enrollment $enrollment): array
    {
        $required = LearningAccess::lessons($enrollment)->where('is_required', true);
        $completed = LessonProgress::query()->where('enrollment_id', $enrollment->id)
            ->whereIn('lesson_id', $required->modelKeys())->whereNotNull('completed_at')->count();

        $total = $required->count();

        return ['completed' => $completed, 'required' => $total,
            'percent' => $total === 0 ? null : QuizScore::display(BigRational::of($completed)->dividedBy($total))];
    }

    /**
     * P9.1: tiến độ của nhiều ghi danh bằng hai truy vấn (bài bắt buộc theo phiên bản, số bài đã xong theo ghi danh),
     * cùng luật với `progress` (bài và chương chưa gỡ). Chỉ dùng để hiển thị.
     *
     * @param  iterable<Enrollment>  $enrollments
     * @return array<int, array{completed: int, required: int, percent: string|null}>
     */
    public static function progressMany(iterable $enrollments): array
    {
        $list = [];
        foreach ($enrollments as $enrollment) {
            $list[] = $enrollment;
        }
        if ($list === []) {
            return [];
        }
        $versions = array_values(array_unique(array_map(fn (Enrollment $e) => $e->course_version_id, $list)));
        $required = [];
        foreach (Lesson::query()->select('lessons.id', 'lessons.course_version_id')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.course_section_id')
            ->whereIn('lessons.course_version_id', $versions)
            ->whereColumn('course_sections.course_version_id', 'lessons.course_version_id')
            ->whereNull('lessons.removed_at')->whereNull('course_sections.removed_at')
            ->where('lessons.is_required', true)->toBase()->get() as $row) {
            $required[(int) $row->course_version_id][(int) $row->id] = true;
        }
        $done = [];
        foreach (LessonProgress::query()->whereIn('enrollment_id', array_map(fn (Enrollment $e) => $e->id, $list))
            ->whereNotNull('completed_at')->toBase()->get(['enrollment_id', 'lesson_id']) as $row) {
            $done[(int) $row->enrollment_id][] = (int) $row->lesson_id;
        }
        $out = [];
        foreach ($list as $enrollment) {
            $lessons = $required[$enrollment->course_version_id] ?? [];
            $completed = 0;
            foreach ($done[$enrollment->id] ?? [] as $lessonId) {
                if (isset($lessons[$lessonId])) {
                    $completed++;
                }
            }
            $total = count($lessons);
            $out[$enrollment->id] = ['completed' => $completed, 'required' => $total,
                'percent' => $total === 0 ? null : QuizScore::display(BigRational::of($completed)->dividedBy($total))];
        }

        return $out;
    }

    /**
     * @param  array{completed: int, required: int, percent: string|null}|null  $progress  tiến độ tính sẵn bằng `progressMany`
     * @return array<string, mixed>
     */
    public static function row(Enrollment $enrollment, bool $withGrants = false, ?array $progress = null): array
    {
        $validity = EnrollmentValidityChecker::for($enrollment);
        $grants = [];
        if ($withGrants) {
            $required = LearningAccess::lessons($enrollment)->where('is_required', true);
            foreach ($required->load('quiz') as $lesson) {
                $quiz = $lesson->quiz;
                if ($quiz === null) {
                    continue;
                }
                $attempts = QuizAttempts::for($enrollment, $quiz);
                $limit = QuizAttempts::limit($enrollment, $quiz);
                if ($limit > 0 && (clone $attempts)->count() === $limit && ! $attempts->where(fn ($q) => $q
                    ->where('status', AttemptStatus::DangLam)->orWhere('passed', true))->exists()) {
                    $grants[] = [
                        'title' => $lesson->title,
                        'url' => route('academic.enrollments.quizzes.extra-attempts.store', ['enrollment' => $enrollment->id, 'quiz' => $quiz->id]),
                    ];
                }
            }
        }

        return [
            'id' => $enrollment->id, 'student' => $enrollment->user?->name,
            'student_url' => route('academic.students.show', $enrollment->user_id),
            'course' => $enrollment->course?->title, 'version' => $enrollment->version?->version_no,
            'status' => $enrollment->learning_status->label(), 'validity' => $validity->label(),
            'expires_at' => $enrollment->het_han_luc?->toISOString(),
            ...($progress ?? self::progress($enrollment)), 'grants' => $grants,
            // QT-15 A8/A9 (CR-134): Giáo vụ thu hồi hoặc khôi phục quyền học.
            'revoked' => $enrollment->revoked_at !== null,
            'revoke_url' => $enrollment->revoked_at === null ? route('academic.enrollments.revoke', $enrollment) : null,
            'restore_url' => $enrollment->revoked_at !== null ? route('academic.enrollments.restore', $enrollment) : null,
        ];
    }
}
