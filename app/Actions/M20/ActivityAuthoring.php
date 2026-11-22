<?php

namespace App\Actions\M20;

use App\Enums\M20\DrawMode;
use App\Models\M03\Course;
use App\Models\M04\QuestionBank;
use App\Models\M20\PracticeActivity;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/** QT-22 A1a/A1b: Giáo viên được giao tạo/sửa/lưu trữ hoạt động luyện tập (BR-XP-70, CR-153, E38). */
final class ActivityAuthoring
{
    /** @param array{title: string, question_count: int, draw_mode: string, seconds_per_question: int, bank_ids: list<int>} $data */
    public function save(User $actor, Course $course, array $data, ?PracticeActivity $activity = null): PracticeActivity
    {
        return Tx::run(function () use ($actor, $course, $data, $activity) {
            $course = CourseLocks::course($course->id);
            AuthoringScope::ensure($actor, $course);
            // E38: khóa ngân hàng cùng thứ tự với bật cờ bảo mật, đọc lại cờ lúc ghi.
            $banks = QuestionBank::query()->whereKey($data['bank_ids'])->orderBy('id')->lockForUpdate()->get();
            if ($banks->count() !== count(array_unique($data['bank_ids'])) || $banks->contains(fn (QuestionBank $b) => $b->course_id !== $course->id)) {
                throw new DomainRejection('Chọn ngân hàng câu hỏi của chính khóa này.', 'bank_ids');
            }
            if ($banks->contains(fn (QuestionBank $b) => $b->is_confidential)) {
                throw new DomainRejection('Không dùng ngân hàng bảo mật cho luyện tập (E38).', 'bank_ids');
            }
            $fields = ['title' => $data['title'], 'question_count' => $data['question_count'], 'draw_mode' => DrawMode::from($data['draw_mode']),
                'seconds_per_question' => $data['seconds_per_question'], 'updated_by_id' => $actor->id];
            if ($activity === null) {
                $activity = PracticeActivity::query()->create($fields + ['course_id' => $course->id, 'created_by_id' => $actor->id]);
                Audit::log('ACTIVITY_CREATE', $actor, $activity, null, $fields + ['bank_ids' => $data['bank_ids']]);
            } else {
                $activity = PracticeActivity::query()->whereKey($activity->id)->where('course_id', $course->id)->lockForUpdate()->firstOrFail();
                $old = $activity->only(['title', 'question_count', 'draw_mode', 'seconds_per_question']);
                $activity->fill($fields)->save();
                Audit::log('ACTIVITY_UPDATE', $actor, $activity, $old, $fields + ['bank_ids' => $data['bank_ids']]);
            }
            $activity->banks()->sync($banks->pluck('id')->all());

            return $activity;
        });
    }

    public function archive(User $actor, PracticeActivity $activity, bool $archived): PracticeActivity
    {
        return Tx::run(function () use ($actor, $activity, $archived) {
            $course = CourseLocks::course($activity->course_id);
            AuthoringScope::ensure($actor, $course);
            $activity = PracticeActivity::query()->whereKey($activity->id)->lockForUpdate()->firstOrFail();
            $activity->forceFill(['archived_at' => $archived ? now() : null, 'updated_by_id' => $actor->id])->save();
            Audit::log($archived ? 'ACTIVITY_ARCHIVE' : 'ACTIVITY_RESTORE', $actor, $activity);

            return $activity;
        });
    }
}
