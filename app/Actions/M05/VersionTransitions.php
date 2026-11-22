<?php

namespace App\Actions\M05;

use App\Enums\M04\QuestionStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\ReviewResult;
use App\Events\M05\CourseVersionPublished;
use App\Events\M05\CourseVersionReviewed;
use App\Events\M05\CourseVersionSubmitted;
use App\Models\M04\Question;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseReview;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M05\ReviewComment;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\M03\ReviewerIndependence;
use App\Support\M05\DraftChecklist;
use App\Support\Tx;
use Illuminate\Validation\ValidationException;

/**
 * PB-3..PB-9 (03 §3): SubmitVersionForReview, WithdrawVersion, ReopenReturnedVersion,
 * ReturnVersion, ApproveVersion, PublishCourseVersion. Mỗi cạnh một giao dịch courses → course_versions.
 */
final class VersionTransitions
{
    /** PB-3: kiểm danh sách kiểm dưới khóa, chụp câu còn sống, đổi trạng thái, nhật ký. */
    public function submit(User $actor, CourseVersion $version): CourseVersion
    {
        $version = Tx::run(function () use ($actor, $version) {
            $version = CourseLocks::version($version->id);
            AuthoringScope::ensure($actor, $version->course);
            if ($version->status !== CourseVersionStatus::Nhap) {
                throw new DomainRejection('Chỉ gửi duyệt được bản nháp.', 'general');
            }
            $items = DraftChecklist::evaluate($version);
            if ($items !== []) {
                $messages = [];
                foreach ($items as $i => $item) {
                    $messages["checklist.{$i}"] = $item['message'];
                }
                throw ValidationException::withMessages($messages);
            }
            $this->snapshot($version);
            $now = now();
            $version->forceFill(['status' => CourseVersionStatus::ChoDuyet, 'submitted_at' => $now, 'submitted_by_id' => $actor->id])->save();
            Audit::log('VERSION_SUBMIT', $actor, $version, ['status' => CourseVersionStatus::Nhap->value], ['status' => CourseVersionStatus::ChoDuyet->value]);

            return $version;
        });
        event(new CourseVersionSubmitted($version->id));

        return $version;
    }

    /** PB-4 (E5): rút `CHO_DUYET` về `NHAP`, xóa bản chụp. */
    public function withdraw(User $actor, CourseVersion $version): CourseVersion
    {
        return Tx::run(function () use ($actor, $version) {
            $version = CourseLocks::version($version->id);
            AuthoringScope::ensure($actor, $version->course);
            if ($version->status !== CourseVersionStatus::ChoDuyet) {
                throw new DomainRejection('Chỉ rút về nháp được bản đang chờ duyệt.', 'general');
            }
            $this->clearSnapshot($version);
            $version->forceFill(['status' => CourseVersionStatus::Nhap])->save();
            Audit::log('VERSION_WITHDRAW', $actor, $version, ['status' => CourseVersionStatus::ChoDuyet->value], ['status' => CourseVersionStatus::Nhap->value]);

            return $version;
        });
    }

    /** PB-6 (A11): "Bắt đầu sửa" bản `TRA_LAI`. */
    public function reopen(User $actor, CourseVersion $version): CourseVersion
    {
        return Tx::run(function () use ($actor, $version) {
            $version = CourseLocks::version($version->id);
            AuthoringScope::ensure($actor, $version->course);
            if ($version->status !== CourseVersionStatus::TraLai) {
                throw new DomainRejection('Chỉ bắt đầu sửa được bản bị trả lại.', 'general');
            }
            $this->clearSnapshot($version);
            $version->forceFill(['status' => CourseVersionStatus::Nhap])->save();
            Audit::log('VERSION_REOPEN', $actor, $version, ['status' => CourseVersionStatus::TraLai->value], ['status' => CourseVersionStatus::Nhap->value]);

            return $version;
        });
    }

    /**
     * PB-5 (A10): E6 trước, rồi lý do không rỗng (E4); ghi lần duyệt và nhận xét theo bài.
     *
     * @param  array<int|string, string>  $comments  lesson_id (hoặc 0 = chung) => nhận xét
     */
    public function returnVersion(User $actor, CourseVersion $version, ?string $reason, array $comments = []): CourseVersion
    {
        $version = Tx::run(function () use ($actor, $version, $reason, $comments) {
            $version = $this->reviewable($actor, $version);
            $reason = trim((string) $reason);
            if ($reason === '') {
                throw new DomainRejection('Hãy nhập lý do trả lại.', 'reason');
            }
            $review = CourseReview::query()->create([
                'course_version_id' => $version->id, 'reviewer_id' => $actor->id,
                'result' => ReviewResult::TraLai, 'reason' => $reason, 'created_at' => now(),
            ]);
            $aliveLessons = Lesson::query()->where('course_version_id', $version->id)->pluck('id')->all();
            foreach ($comments as $lessonId => $text) {
                if (trim($text) === '') {
                    continue;
                }
                $lid = (int) $lessonId;
                ReviewComment::query()->create([
                    'course_review_id' => $review->id,
                    'lesson_id' => in_array($lid, $aliveLessons, true) ? $lid : null,
                    'comment' => trim($text),
                    'created_at' => now(),
                ]);
            }
            $version->forceFill(['status' => CourseVersionStatus::TraLai])->save();
            Audit::log('VERSION_RETURN', $actor, $version, ['status' => CourseVersionStatus::ChoDuyet->value], ['status' => CourseVersionStatus::TraLai->value], $reason);

            return $version;
        });
        event(new CourseVersionReviewed($version->id, ReviewResult::TraLai));

        return $version;
    }

    /** PB-7: duyệt sau E6. */
    public function approve(User $actor, CourseVersion $version): CourseVersion
    {
        $version = Tx::run(function () use ($actor, $version) {
            $version = $this->reviewable($actor, $version);
            CourseReview::query()->create([
                'course_version_id' => $version->id, 'reviewer_id' => $actor->id,
                'result' => ReviewResult::Duyet, 'reason' => null, 'created_at' => now(),
            ]);
            $version->forceFill(['status' => CourseVersionStatus::DaDuyet, 'approved_at' => now()])->save();
            Audit::log('VERSION_APPROVE', $actor, $version, ['status' => CourseVersionStatus::ChoDuyet->value], ['status' => CourseVersionStatus::DaDuyet->value]);

            return $version;
        });
        event(new CourseVersionReviewed($version->id, ReviewResult::Duyet));

        return $version;
    }

    /** PB-8/PB-9: bản cũ → DA_THAY_THE, bản mới → DA_CONG_BO, rồi courses.current_version_id/last_published_at. */
    public function publish(User $actor, CourseVersion $version): CourseVersion
    {
        $version = Tx::run(function () use ($actor, $version) {
            $courseId = (int) $version->course_id;
            $course = CourseLocks::course($courseId);
            $versions = CourseVersion::query()->where('course_id', $courseId)
                ->where(fn ($q) => $q->whereKey($version->id)->orWhereNotNull('published_key'))
                ->orderBy('id')->lockForUpdate()->get()->keyBy('id');
            /** @var CourseVersion $target */
            $target = $versions->get($version->id);
            if ($target->status !== CourseVersionStatus::DaDuyet) {
                throw new DomainRejection('Chỉ công bố được bản đã duyệt.', 'general');
            }
            $now = now();
            /** @var CourseVersion|null $old */
            $old = $versions->first(fn (CourseVersion $v) => $v->status === CourseVersionStatus::DaCongBo);
            if ($old !== null) {
                $old->forceFill(['status' => CourseVersionStatus::DaThayThe, 'superseded_at' => $now])->save();
            }
            $target->forceFill(['status' => CourseVersionStatus::DaCongBo, 'published_at' => $now, 'published_by_id' => $actor->id])->save();
            $course->forceFill(['current_version_id' => $target->id, 'last_published_at' => $now])->save();
            Audit::log('VERSION_PUBLISH', $actor, $target,
                ['status' => CourseVersionStatus::DaDuyet->value, 'current_version_id' => $old?->id],
                ['status' => CourseVersionStatus::DaCongBo->value, 'current_version_id' => $target->id]);

            return $target;
        });
        event(new CourseVersionPublished($version->id));

        return $version;
    }

    /** Khóa và kiểm chung cho Duyệt/Trả lại: E6 dưới khóa (403) rồi trạng thái `CHO_DUYET` (422). */
    private function reviewable(User $actor, CourseVersion $version): CourseVersion
    {
        $version = CourseLocks::version($version->id);
        $violation = ReviewerIndependence::violation($actor, $version);
        if ($violation !== null) {
            throw DomainRejection::forbidden($violation);
        }
        if ($version->status !== CourseVersionStatus::ChoDuyet) {
            throw new DomainRejection('Bản không còn ở trạng thái chờ duyệt.', 'general');
        }

        return $version;
    }

    /** BR-ND-58, CR-18: chụp đề, loại, ảnh, lựa chọn, đáp án, lời giải, điểm của mọi câu còn sống. */
    private function snapshot(CourseVersion $version): void
    {
        $quizIds = Quiz::query()->where('course_version_id', $version->id)
            ->whereIn('lesson_id', Lesson::query()->where('course_version_id', $version->id)->whereNull('removed_at')->select('id'))
            ->pluck('id');
        $items = QuizQuestion::query()->whereIn('quiz_id', $quizIds)->whereNull('removed_at')->get();
        $ids = $items->pluck('question_id')->unique()->sort()->values()->all();
        $questions = Question::query()->with('options')->whereIn('id', $ids)->orderBy('id')->sharedLock()->get()->keyBy('id');
        $now = now();
        foreach ($items as $item) {
            /** @var Question $q */
            $q = $questions->get($item->question_id);
            if ($q->status !== QuestionStatus::DangDung) {
                throw new DomainRejection('Quiz có câu đã ngừng dùng: hãy bỏ câu đó trước khi gửi duyệt.', 'checklist.0');
            }
            $item->forceFill([
                'points' => $q->points,
                'snapshot' => [
                    'question_id' => $q->id,
                    'type' => $q->type->value,
                    'difficulty' => $q->difficulty->value,
                    'stem' => $q->stem,
                    'image_path' => $q->image_path,
                    'explanation' => $q->explanation,
                    'points' => (string) $q->points,
                    'options' => $q->options->map(fn ($o) => [
                        'id' => $o->id, 'content' => $o->content, 'is_correct' => $o->is_correct, 'position' => $o->position,
                    ])->values()->all(),
                ],
                'snapshotted_at' => $now,
            ])->save();
        }
        Lesson::query()->where('course_version_id', $version->id)->whereNull('removed_at')->get()
            ->each(fn (Lesson $l) => $l->forceFill(['content_fingerprint' => hash('sha256', json_encode([
                $l->type->value, $l->title, $l->content, $l->video_path, $l->pdf_path, $l->is_required, $l->is_final_test,
            ]) ?: '')])->save());
    }

    private function clearSnapshot(CourseVersion $version): void
    {
        QuizQuestion::query()->whereIn('quiz_id', Quiz::query()->where('course_version_id', $version->id)->select('id'))
            ->update(['points' => null, 'snapshot' => null, 'snapshotted_at' => null]);
    }
}
