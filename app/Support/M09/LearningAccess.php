<?php

namespace App\Support\M09;

use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M08\LearningStatus;
use App\Enums\M09\Activity;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M08\EnrollmentValidityChecker;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Collection;

final class LearningAccess
{
    public static function owns(User $user, Enrollment $enrollment): bool
    {
        return $user->id === $enrollment->user_id && $user->hasEffectiveRole(RoleCode::HocVien);
    }

    public static function allows(User $user, Enrollment $enrollment, Activity $activity, ?Lesson $lesson = null, ?QuizAttempt $attempt = null, ?CarbonImmutable $at = null): bool
    {
        if (! self::owns($user, $enrollment)) {
            return false;
        }
        if (in_array($activity, [Activity::ViewHistory, Activity::ViewCertificate, Activity::DownloadCertificate], true)) {
            return true;
        }
        if ($lesson === null || ! self::matches($enrollment, $lesson)) {
            return false;
        }
        $validity = EnrollmentValidityChecker::for($enrollment, $at);
        if ($activity === Activity::ContinueQuizAttempt) {
            return $attempt !== null && $attempt->enrollment_id === $enrollment->id
                && $attempt->user_id === $user->id && $attempt->quiz?->lesson_id === $lesson->id
                && ($validity === EnrollmentValidity::ConHan
                    || ($validity === EnrollmentValidity::HetHan && $enrollment->het_han_luc !== null
                        && $attempt->started_at->lt($enrollment->het_han_luc)));
        }

        return $validity === EnrollmentValidity::ConHan && self::blocker($enrollment, $lesson) === null && self::prerequisiteMet($enrollment);
    }

    /** CR-67: khóa có khóa tiên quyết chặn bắt đầu học (không chặn mua) tới khi khóa tiên quyết HOAN_THANH. */
    public static function prerequisiteMet(Enrollment $enrollment): bool
    {
        if ($enrollment->started_at !== null) {
            return true;
        }
        $prerequisite = Course::query()->whereKey($enrollment->course_id)->value('prerequisite_course_id');

        return $prerequisite === null || Enrollment::query()->where('user_id', $enrollment->user_id)->where('course_id', $prerequisite)
            ->where('learning_status', LearningStatus::HoanThanh)->exists();
    }

    public static function prerequisiteTitle(Enrollment $enrollment): ?string
    {
        if (self::prerequisiteMet($enrollment)) {
            return null;
        }
        $id = Course::query()->whereKey($enrollment->course_id)->value('prerequisite_course_id');

        return Course::query()->whereKey($id)->value('title');
    }

    public static function authorize(User $user, Enrollment $enrollment, Activity $activity, Lesson $lesson, ?QuizAttempt $attempt = null, ?CarbonImmutable $at = null): void
    {
        if (self::allows($user, $enrollment, $activity, $lesson, $attempt, $at)) {
            return;
        }

        $message = 'Bạn không có quyền mở nội dung này.';
        if (self::owns($user, $enrollment) && self::matches($enrollment, $lesson)) {
            $message = match (EnrollmentValidityChecker::for($enrollment, $at)) {
                EnrollmentValidity::HetHan => 'Đã hết hạn truy cập.',
                EnrollmentValidity::DaThuHoi => 'Quyền học đã bị thu hồi.',
                EnrollmentValidity::ConHan => ($pre = self::prerequisiteTitle($enrollment)) !== null
                    ? 'Hoàn thành khóa tiên quyết trước: '.$pre.'.'
                    : (($blocker = self::blocker($enrollment, $lesson)) === null ? $message : 'Hoàn thành bài bắt buộc trước: '.$blocker->title.'.'),
            };
        }
        throw DomainRejection::forbidden($message);
    }

    public static function matches(Enrollment $enrollment, Lesson $lesson): bool
    {
        return $lesson->course_version_id === $enrollment->course_version_id && $lesson->removed_at === null
            && $lesson->section !== null && $lesson->section->removed_at === null
            && $lesson->section->course_version_id === $enrollment->course_version_id;
    }

    /** @return Collection<int, Lesson> */
    public static function lessons(Enrollment $enrollment): Collection
    {
        return Lesson::query()->select('lessons.*')
            ->join('course_sections', 'course_sections.id', '=', 'lessons.course_section_id')
            ->where('lessons.course_version_id', $enrollment->course_version_id)
            ->where('course_sections.course_version_id', $enrollment->course_version_id)
            ->whereNull('lessons.removed_at')->whereNull('course_sections.removed_at')
            ->orderBy('course_sections.position')->orderBy('course_sections.id')
            ->orderBy('lessons.position')->orderBy('lessons.id')->with('section')->get();
    }

    public static function blocker(Enrollment $enrollment, Lesson $lesson): ?Lesson
    {
        if ($enrollment->course?->learning_mode !== LearningMode::TuanTu) {
            return null;
        }
        $completed = LessonProgress::query()->where('enrollment_id', $enrollment->id)
            ->whereNotNull('completed_at')->pluck('lesson_id')->flip();

        foreach (self::lessons($enrollment) as $previous) {
            if ($previous->id === $lesson->id) {
                break;
            }
            if ($previous->is_required && ! $previous->is_preview && ! $completed->has($previous->id)) {
                return $previous;
            }
        }

        return null;
    }
}
