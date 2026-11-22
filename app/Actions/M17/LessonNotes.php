<?php

namespace App\Actions\M17;

use App\Enums\M05\LessonType;
use App\Enums\M09\Activity;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M17\LessonNote;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M09\LearningAccess;
use App\Support\Tx;

/** QT-18 ghi chú (CR-79/139): gắn khóa bài, giữ khi chuyển bản; tạo mới cần ghi danh còn hạn; chủ ghi chú sửa/xóa. */
final class LessonNotes
{
    public function create(User $student, Enrollment $enrollment, Lesson $lesson, string $body, ?int $second): LessonNote
    {
        $body = Discussions::text($body, 'body');

        return Tx::run(function () use ($student, $enrollment, $lesson, $body, $second) {
            OrderLocks::student($student, $enrollment->user_id);
            $enrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            Discussions::requireActive($enrollment);
            if (! LearningAccess::matches($enrollment, $lesson) || ! LearningAccess::allows($student, $enrollment, Activity::ViewLesson, $lesson)) {
                throw DomainRejection::forbidden('Bạn chưa mở được bài này.');
            }
            if ($second !== null) {
                $max = $lesson->video_duration_ms === null ? null : intdiv($lesson->video_duration_ms + 999, 1000);
                if ($lesson->type !== LessonType::Video || $second < 0 || ($max !== null && $second > $max)) {
                    throw new DomainRejection('Mốc giây vượt thời lượng video.', 'video_second');
                }
            }

            return LessonNote::query()->create([
                'user_id' => $student->id, 'course_id' => $enrollment->course_id, 'lesson_key' => $lesson->lesson_key,
                'lesson_id' => $lesson->id, 'video_second' => $second, 'body' => $body,
            ]);
        });
    }

    public function update(User $student, LessonNote $note, string $body): LessonNote
    {
        $body = Discussions::text($body, 'body');
        if ($note->user_id !== $student->id) {
            throw DomainRejection::forbidden('Ghi chú không phải của bạn.');
        }
        $note->update(['body' => $body]);

        return $note;
    }

    public function delete(User $student, LessonNote $note): void
    {
        if ($note->user_id !== $student->id) {
            throw DomainRejection::forbidden('Ghi chú không phải của bạn.');
        }
        $note->delete();
    }
}
