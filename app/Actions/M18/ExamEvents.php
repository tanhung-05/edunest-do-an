<?php

namespace App\Actions\M18;

use App\Enums\M01\RoleCode;
use App\Enums\M04\QuizKind;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M18\ExamEvent;
use App\Models\M18\ExamEventCourse;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Carbon\CarbonImmutable;

/**
 * QT-19a (02c KT-1..3): tạo, sửa nháp, chuyển `SAP_MO` (chụp và khóa đề), dời lịch, hẹn công bố.
 * Người tổ chức: Giáo viên (đề của mình) hoặc Giáo vụ (đề bất kỳ, không thấy đáp án, CR-75). Chỉ người tạo kỳ sửa kỳ.
 */
final class ExamEvents
{
    /**
     * @param  array{title: string, quiz_id: int, opens_at: CarbonImmutable, closes_at: CarbonImmutable, duration_minutes: int, audience: ExamAudience, requires_registration: bool, course_ids: list<int>, publish_at: ?CarbonImmutable}  $data
     */
    public function create(User $organizer, array $data): ExamEvent
    {
        return Tx::run(function () use ($organizer, $data) {
            $organizer = self::organizerLock($organizer);
            $quiz = self::paper($organizer, (int) $data['quiz_id']);
            self::validateDraft($data);
            $event = ExamEvent::query()->create([
                'quiz_id' => $quiz->id, 'title' => trim($data['title']), 'created_by_id' => $organizer->id, 'status' => ExamStatus::Nhap,
                'opens_at' => $data['opens_at'], 'closes_at' => $data['closes_at'], 'duration_minutes' => $data['duration_minutes'],
                'audience' => $data['audience'], 'requires_registration' => $data['requires_registration'], 'publish_at' => $data['publish_at'],
            ]);
            self::syncCourses($event, $data);
            Audit::log('EXAM_CREATE', $organizer, $event, null, ['quiz_id' => $quiz->id]);

            return $event;
        });
    }

    /**
     * A6: sửa kỳ nháp (lịch, đối tượng, đổi đề); xét lại mốc mở đáp án của đề cũ (CR-144).
     *
     * @param  array{title: string, quiz_id: int, opens_at: CarbonImmutable, closes_at: CarbonImmutable, duration_minutes: int, audience: ExamAudience, requires_registration: bool, course_ids: list<int>, publish_at: ?CarbonImmutable}  $data
     */
    public function update(User $organizer, ExamEvent $event, array $data): ExamEvent
    {
        return Tx::run(function () use ($organizer, $event, $data) {
            $organizer = self::organizerLock($organizer);
            $event = self::owned($organizer, $event);
            if ($event->status !== ExamStatus::Nhap) {
                throw new DomainRejection('Chỉ sửa được kỳ ở trạng thái Nháp.', 'title');
            }
            self::validateDraft($data);
            $oldQuizId = $event->quiz_id;
            $quiz = self::paper($organizer, (int) $data['quiz_id']);
            $event->update([
                'quiz_id' => $quiz->id, 'title' => trim($data['title']), 'opens_at' => $data['opens_at'], 'closes_at' => $data['closes_at'],
                'duration_minutes' => $data['duration_minutes'], 'audience' => $data['audience'],
                'requires_registration' => $data['requires_registration'], 'publish_at' => $data['publish_at'],
            ]);
            self::syncCourses($event, $data);
            if ($oldQuizId !== $quiz->id) {
                self::markAnswersOpen(Quiz::query()->whereKey($oldQuizId)->lockForUpdate()->firstOrFail());
            }

            return $event;
        });
    }

    /** KT-2: `NHAP` → `SAP_MO`; chụp đề một lần, dùng chung cho mọi kỳ của đề (BR-TH-04/05, CR-141). */
    public function schedule(User $organizer, ExamEvent $event): ExamEvent
    {
        return Tx::run(function () use ($organizer, $event) {
            $organizer = self::organizerLock($organizer);
            $event = self::owned($organizer, $event);
            $quiz = Quiz::query()->whereKey($event->quiz_id)->lockForUpdate()->firstOrFail();
            $now = now()->floorMillisecond();
            if ($event->status !== ExamStatus::Nhap) {
                throw new DomainRejection('Kỳ không ở trạng thái Nháp.', 'status');
            }
            if ($quiz->answers_open_at !== null) {
                throw new DomainRejection('Đề đã công bố đáp án, không dùng cho kỳ mới (BR-TH-20).', 'quiz_id');
            }
            self::window($event->opens_at, $event->closes_at, $event->duration_minutes, $now);
            $items = QuizQuestion::query()->where('quiz_id', $quiz->id)->whereNull('removed_at')->orderBy('position')->lockForUpdate()->get();
            if ($items->isEmpty()) {
                throw new DomainRejection('Đề chưa có câu hỏi.', 'quiz_id');
            }
            if ($quiz->locked_at === null) {
                $questions = Question::query()->with('options')->whereIn('id', $items->pluck('question_id'))->sharedLock()->get()->keyBy('id');
                foreach ($items as $item) {
                    /** @var Question $q */
                    $q = $questions->get($item->question_id);
                    $item->forceFill(['points' => $q->points, 'snapshot' => self::snapshot($q), 'snapshotted_at' => $now])->save();
                }
                $quiz->forceFill(['locked_at' => $now])->save();
            }
            $event->update(['status' => ExamStatus::SapMo, 'scheduled_at' => $now]);
            Audit::log('EXAM_SCHEDULE', $organizer, $event, ['status' => 'NHAP'], ['status' => 'SAP_MO']);

            return $event;
        });
    }

    /** KT-3, A1: dời lịch khi `SAP_MO` và trước giờ mở hiện hành; có nhật ký (BR-TH-36). */
    public function reschedule(User $organizer, ExamEvent $event, CarbonImmutable $opensAt, CarbonImmutable $closesAt, int $duration): ExamEvent
    {
        return Tx::run(function () use ($organizer, $event, $opensAt, $closesAt, $duration) {
            $organizer = self::organizerLock($organizer);
            $event = self::owned($organizer, $event);
            $now = now()->floorMillisecond();
            if ($event->status !== ExamStatus::SapMo || $now->gte($event->opens_at)) {
                throw new DomainRejection('Chỉ dời lịch kỳ sắp mở, trước giờ mở hiện hành.', 'opens_at');
            }
            self::window($opensAt, $closesAt, $duration, $now);
            $old = ['opens_at' => $event->opens_at->toIso8601String(), 'closes_at' => $event->closes_at->toIso8601String(), 'duration_minutes' => $event->duration_minutes];
            $event->update(['opens_at' => $opensAt, 'closes_at' => $closesAt, 'duration_minutes' => $duration, 'reminded_at' => null]);
            Audit::log('EXAM_RESCHEDULE', $organizer, $event, $old, ['opens_at' => $opensAt->toIso8601String(), 'closes_at' => $closesAt->toIso8601String(), 'duration_minutes' => $duration]);

            return $event;
        });
    }

    /** A2: hẹn giờ công bố, đổi được tới trước `DA_CONG_BO`. */
    public function setPublishAt(User $organizer, ExamEvent $event, ?CarbonImmutable $publishAt): ExamEvent
    {
        return Tx::run(function () use ($organizer, $event, $publishAt) {
            $organizer = self::organizerLock($organizer);
            $event = self::owned($organizer, $event);
            if ($event->status === ExamStatus::DaCongBo) {
                throw new DomainRejection('Kỳ đã công bố.', 'publish_at');
            }
            $event->update(['publish_at' => $publishAt]);

            return $event;
        });
    }

    /**
     * CR-71/144: ghi mốc mở đáp án khi đề còn kỳ dùng và mọi kỳ đó đã đóng hoặc công bố. Gọi dưới khóa đề.
     */
    public static function markAnswersOpen(Quiz $quiz): void
    {
        if ($quiz->answers_open_at !== null) {
            return;
        }
        $statuses = ExamEvent::query()->where('quiz_id', $quiz->id)->pluck('status');
        if ($statuses->isNotEmpty() && $statuses->every(fn (ExamStatus $s) => in_array($s, [ExamStatus::DaDong, ExamStatus::DaCongBo], true))) {
            $quiz->forceFill(['answers_open_at' => now()->floorMillisecond()])->save();
        }
    }

    /** @return array<string, mixed> */
    public static function snapshot(Question $q): array
    {
        return [
            'question_id' => $q->id, 'type' => $q->type->value, 'difficulty' => $q->difficulty->value, 'stem' => $q->stem,
            'image_path' => $q->image_path, 'explanation' => $q->explanation, 'points' => (string) $q->points,
            'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'content' => $o->content, 'is_correct' => $o->is_correct, 'position' => $o->position])->values()->all(),
        ];
    }

    public static function isOrganizerRole(User $user): bool
    {
        return $user->isActive() && $user->hasEffectiveRole(RoleCode::GiaoVien, RoleCode::GiaoVu);
    }

    private static function organizerLock(User $organizer): User
    {
        $organizer = User::query()->whereKey($organizer->id)->lockForUpdate()->firstOrFail();
        if (! self::isOrganizerRole($organizer)) {
            throw DomainRejection::forbidden('Chỉ Giáo viên hoặc Giáo vụ tổ chức kỳ thi.');
        }

        return $organizer;
    }

    private static function owned(User $organizer, ExamEvent $event): ExamEvent
    {
        $event = ExamEvent::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
        if ($event->created_by_id !== $organizer->id) {
            throw DomainRejection::forbidden('Bạn không phải người tổ chức kỳ này.');
        }

        return $event;
    }

    private static function paper(User $organizer, int $quizId): Quiz
    {
        $quiz = Quiz::query()->whereKey($quizId)->lockForUpdate()->first();
        if ($quiz === null || $quiz->kind !== QuizKind::ThiThu) {
            throw new DomainRejection('Đề thi không tồn tại.', 'quiz_id');
        }
        $academic = $organizer->hasEffectiveRole(RoleCode::GiaoVu);
        if (! $academic && $quiz->owner_teacher_id !== $organizer->id) {
            throw DomainRejection::forbidden('Giáo viên chỉ chọn đề của mình (BR-TH-02).');
        }
        if ($quiz->answers_open_at !== null) {
            throw new DomainRejection('Đề đã công bố đáp án, không tạo kỳ mới được (BR-TH-20).', 'quiz_id');
        }

        return $quiz;
    }

    /** @param array<string, mixed> $data */
    private static function validateDraft(array $data): void
    {
        $title = trim((string) ($data['title'] ?? ''));
        if ($title === '' || mb_strlen($title) > 200) {
            throw new DomainRejection('Tên kỳ từ 1 đến 200 ký tự.', 'title');
        }
        if ($data['closes_at']->lte($data['opens_at'])) {
            throw new DomainRejection('Giờ đóng phải sau giờ mở.', 'closes_at');
        }
        if ($data['duration_minutes'] < 1 || $data['duration_minutes'] > 600) {
            throw new DomainRejection('Thời lượng từ 1 đến 600 phút.', 'duration_minutes');
        }
        if ($data['audience'] === ExamAudience::Khoa && ($data['course_ids'] ?? []) === []) {
            throw new DomainRejection('Kỳ dành cho khóa cần chọn ít nhất một khóa.', 'course_ids');
        }
    }

    /** CR-141: giờ mở sau lúc ghi, giờ đóng sau giờ mở, thời lượng không vượt cửa sổ. */
    private static function window(CarbonImmutable $opens, CarbonImmutable $closes, int $duration, CarbonImmutable $now): void
    {
        if ($opens->lte($now)) {
            throw new DomainRejection('Giờ mở phải sau thời điểm hiện tại.', 'opens_at');
        }
        if ($closes->lte($opens)) {
            throw new DomainRejection('Giờ đóng phải sau giờ mở.', 'closes_at');
        }
        if ($duration < 1 || $opens->addMinutes($duration)->gt($closes)) {
            throw new DomainRejection('Thời lượng không được vượt độ dài cửa sổ thi.', 'duration_minutes');
        }
    }

    /** @param array<string, mixed> $data */
    private static function syncCourses(ExamEvent $event, array $data): void
    {
        ExamEventCourse::query()->where('exam_event_id', $event->id)->delete();
        if ($data['audience'] !== ExamAudience::Khoa) {
            return;
        }
        $ids = Course::query()->whereIn('id', array_map('intval', $data['course_ids']))->pluck('id');
        foreach ($ids as $id) {
            ExamEventCourse::query()->create(['exam_event_id' => $event->id, 'course_id' => $id]);
        }
    }
}
