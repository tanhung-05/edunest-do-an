<?php

namespace App\Actions\M05;

use App\Actions\M10\CompleteEnrollment;
use App\Actions\M10\FinalizeAttempt;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\AttemptStatus;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M05\EnrollmentVersionSwitch;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;

/**
 * QT-21 (02c CB-1): học viên tự chuyển ghi danh sang bản `DA_CONG_BO` mới nhất. Giữ tiến độ bài không phải quiz có cùng
 * khóa bài và dấu vân tay; quiz làm lại; điểm/lượt cũ ở bản cũ; sau commit xét hoàn thành (CR-66/150).
 */
final class SwitchEnrollmentVersion
{
    public function __construct(private FinalizeAttempt $finalize, private CompleteEnrollment $complete) {}

    public function handle(User $student, Enrollment $enrollment): Enrollment
    {
        // 4a: chủ ghi danh trước; rồi chốt lượt quiz đã quá hạn mà tác vụ quét chưa chạy (QT-10 E26).
        OrderLocks::student($student, $enrollment->user_id);
        $overdue = QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('status', AttemptStatus::DangLam)
            ->where('deadline_at', '<', now()->floorMillisecond())->get();
        foreach ($overdue as $attempt) {
            $this->finalize->handle($attempt);
        }

        $switched = Tx::run(function () use ($student, $enrollment) {
            OrderLocks::student(User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail(), $enrollment->user_id);
            $course = Course::query()->whereKey($enrollment->course_id)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            if ($enrollment->learning_status !== LearningStatus::DangHoc) {
                throw new DomainRejection('Khóa đã hoàn thành, không chuyển phiên bản.', 'version');
            }
            if (EnrollmentValidityChecker::for($enrollment) !== EnrollmentValidity::ConHan) {
                throw new DomainRejection('Quyền học đã hết hạn hoặc bị thu hồi.', 'version');
            }
            if (QuizAttempt::query()->where('enrollment_id', $enrollment->id)->where('status', AttemptStatus::DangLam)->exists()) {
                throw new DomainRejection('Bạn đang có lượt quiz chưa nộp. Hãy nộp trước khi chuyển.', 'version');
            }
            $target = $course->current_version_id === null ? null : CourseVersion::query()->find($course->current_version_id);
            if ($target === null || $target->status !== CourseVersionStatus::DaCongBo || $target->id === $enrollment->course_version_id) {
                throw new DomainRejection('Không có phiên bản mới hơn để chuyển.', 'version');
            }
            $old = Lesson::query()->where('course_version_id', $enrollment->course_version_id)->whereNull('removed_at')->get()->keyBy('lesson_key');
            $progress = LessonProgress::query()->where('enrollment_id', $enrollment->id)->whereIn('lesson_id', $old->pluck('id'))->get()->keyBy('lesson_id');
            $kept = 0;
            foreach (Lesson::query()->where('course_version_id', $target->id)->whereNull('removed_at')->get() as $lesson) {
                $prev = $old->get($lesson->lesson_key);
                if ($lesson->type === LessonType::Quiz || $prev === null || $prev->content_fingerprint === null
                    || $prev->content_fingerprint !== $lesson->content_fingerprint) {
                    continue;
                }
                $row = $progress->get($prev->id);
                if ($row === null) {
                    continue;
                }
                LessonProgress::query()->updateOrCreate(['enrollment_id' => $enrollment->id, 'lesson_id' => $lesson->id], [
                    'started_at' => $row->started_at, 'completed_at' => $row->completed_at, 'watched_segments' => $row->watched_segments,
                    'valid_watch_ms' => $row->valid_watch_ms, 'last_position_ms' => $row->last_position_ms, 'last_beat_at' => $row->last_beat_at,
                    'last_viewed_at' => $row->last_viewed_at,
                ]);
                $kept++;
            }
            EnrollmentVersionSwitch::query()->create(['enrollment_id' => $enrollment->id, 'from_version_id' => $enrollment->course_version_id,
                'to_version_id' => $target->id, 'kept_lessons' => $kept, 'switched_at' => now()->floorMillisecond()]);
            $enrollment->update(['course_version_id' => $target->id]);

            return $enrollment;
        });

        // Bước 7 (CR-150): QT-11 xét hoàn thành trên bản mới, idempotent.
        DB::afterCommit(fn () => $this->complete->handle($switched->fresh() ?? $switched));

        return $switched;
    }
}
