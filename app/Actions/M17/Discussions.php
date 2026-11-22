<?php

namespace App\Actions\M17;

use App\Enums\M05\LessonType;
use App\Enums\M08\EnrollmentValidity;
use App\Enums\M09\Activity;
use App\Enums\M15\NotificationKind;
use App\Enums\M17\DiscussionStatus;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionFollow;
use App\Models\M17\DiscussionReply;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M07\OrderLocks;
use App\Support\M08\EnrollmentValidityChecker;
use App\Support\M09\LearningAccess;
use App\Support\M15\Notifier;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;

/**
 * QT-18 (02c HD-1..4): hỏi đáp theo bài. Câu hỏi gắn khóa bài trong khóa (CR-138); một dấu chấp nhận mỗi câu (CR-140);
 * chỉ Giáo viên được giao trả lời (CR-78); Giáo vụ ẩn/hiện có lý do.
 */
final class Discussions
{
    public function ask(User $student, Enrollment $enrollment, Lesson $lesson, string $body, ?int $second): Discussion
    {
        $body = self::text($body, 'body');

        return Tx::run(function () use ($student, $enrollment, $lesson, $body, $second) {
            $student = User::query()->whereKey($student->id)->lockForUpdate()->firstOrFail();
            OrderLocks::student($student, $enrollment->user_id);
            $enrollment = Enrollment::query()->whereKey($enrollment->id)->lockForUpdate()->firstOrFail();
            self::requireActive($enrollment);
            if (! LearningAccess::matches($enrollment, $lesson) || ! LearningAccess::allows($student, $enrollment, Activity::ViewLesson, $lesson)) {
                throw DomainRejection::forbidden('Bạn chưa mở được bài này.');
            }
            if ($second !== null) {
                // CR-138: mốc giây chỉ với bài video, từ 0 tới thời lượng lúc gửi (gồm hai biên).
                $max = $lesson->video_duration_ms === null ? null : intdiv($lesson->video_duration_ms + 999, 1000);
                if ($lesson->type !== LessonType::Video || $second < 0 || ($max !== null && $second > $max)) {
                    throw new DomainRejection('Mốc giây không hợp lệ với bài này.', 'video_second');
                }
            }

            return Discussion::query()->create([
                'course_id' => $enrollment->course_id, 'lesson_key' => $lesson->lesson_key, 'lesson_id' => $lesson->id,
                'user_id' => $student->id, 'body' => $body, 'video_second' => $second, 'status' => DiscussionStatus::ChuaTraLoi,
            ]);
        });
    }

    public function reply(User $teacher, Discussion $discussion, string $body): DiscussionReply
    {
        $body = self::text($body, 'body');

        $reply = Tx::run(function () use ($teacher, $discussion, $body) {
            $course = Course::query()->whereKey($discussion->course_id)->lockForUpdate()->firstOrFail();
            AuthoringScope::ensure($teacher, $course);
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            if ($discussion->status === DiscussionStatus::DaAn) {
                throw new DomainRejection('Câu hỏi đã bị ẩn.', 'body');
            }
            $reply = DiscussionReply::query()->create(['discussion_id' => $discussion->id, 'user_id' => $teacher->id, 'body' => $body]);
            if ($discussion->status === DiscussionStatus::ChuaTraLoi) {
                $discussion->update(['status' => DiscussionStatus::DaTraLoi]);
            }

            return $reply;
        });

        $discussionId = $discussion->id;
        $replyId = $reply->id;
        DB::afterCommit(function () use ($discussionId, $replyId) {
            $d = Discussion::query()->find($discussionId);
            if ($d === null) {
                return;
            }
            $recipients = DiscussionFollow::query()->where('discussion_id', $d->id)->pluck('user_id')->push($d->user_id)->unique();
            foreach (User::query()->whereIn('id', $recipients)->get() as $user) {
                Notifier::send($user, NotificationKind::TraLoiHoiDap, 'Có trả lời hỏi đáp', mb_strimwidth($d->body, 0, 120, '…'),
                    route('student.courses.index', ['course' => $d->course_id]), 'discussion-reply:'.$replyId.':'.$user->id);
            }
        });

        return $reply;
    }

    /** HD-3: đặt hoặc đổi dấu chấp nhận; tối đa một dấu (cột duy nhất trên câu hỏi). */
    public function accept(User $actor, Discussion $discussion, DiscussionReply $reply): Discussion
    {
        return Tx::run(function () use ($actor, $discussion, $reply) {
            $course = Course::query()->whereKey($discussion->course_id)->lockForUpdate()->firstOrFail();
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            self::mayMark($actor, $discussion, $course);
            if ($reply->discussion_id !== $discussion->id) {
                throw DomainRejection::forbidden('Câu trả lời không thuộc câu hỏi này.');
            }
            $discussion->update(['accepted_reply_id' => $reply->id, 'status' => DiscussionStatus::DaChapNhan]);

            return $discussion;
        });
    }

    public function unaccept(User $actor, Discussion $discussion): Discussion
    {
        return Tx::run(function () use ($actor, $discussion) {
            $course = Course::query()->whereKey($discussion->course_id)->lockForUpdate()->firstOrFail();
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            self::mayMark($actor, $discussion, $course);
            if ($discussion->status === DiscussionStatus::DaChapNhan) {
                $discussion->update(['accepted_reply_id' => null, 'status' => DiscussionStatus::DaTraLoi]);
            }

            return $discussion;
        });
    }

    /** "Cũng thắc mắc": học viên ghi danh còn hạn của khóa, không phải người hỏi; bấm lại là bỏ. */
    public function toggleFollow(User $student, Discussion $discussion): bool
    {
        return Tx::run(function () use ($student, $discussion) {
            OrderLocks::student($student);
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            $enrollment = Enrollment::query()->where('user_id', $student->id)->where('course_id', $discussion->course_id)->first();
            if ($enrollment === null || EnrollmentValidityChecker::for($enrollment) !== EnrollmentValidity::ConHan
                || $discussion->status === DiscussionStatus::DaAn || $discussion->user_id === $student->id) {
                throw DomainRejection::forbidden('Bạn không theo dõi được câu hỏi này.');
            }
            $existing = DiscussionFollow::query()->where('discussion_id', $discussion->id)->where('user_id', $student->id)->first();
            if ($existing !== null) {
                $existing->delete();
                $discussion->decrement('follower_count');

                return false;
            }
            DiscussionFollow::query()->create(['discussion_id' => $discussion->id, 'user_id' => $student->id, 'created_at' => now()]);
            $discussion->increment('follower_count');

            return true;
        });
    }

    public function hide(User $actor, Discussion $discussion, string $reason): Discussion
    {
        $reason = self::text($reason, 'reason', 500);

        return Tx::run(function () use ($actor, $discussion, $reason) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail());
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            if ($discussion->status === DiscussionStatus::DaAn) {
                return $discussion;
            }
            $old = $discussion->status;
            $discussion->update(['status_before_hide' => $old, 'status' => DiscussionStatus::DaAn, 'hidden_reason' => $reason,
                'hidden_by_id' => $actor->id, 'hidden_at' => now()]);
            Audit::log('DISCUSSION_HIDE', $actor, $discussion, ['status' => $old->value], ['status' => 'DA_AN'], $reason);

            return $discussion;
        });
    }

    public function unhide(User $actor, Discussion $discussion): Discussion
    {
        return Tx::run(function () use ($actor, $discussion) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail());
            $discussion = Discussion::query()->whereKey($discussion->id)->lockForUpdate()->firstOrFail();
            if ($discussion->status !== DiscussionStatus::DaAn) {
                return $discussion;
            }
            $back = $discussion->status_before_hide ?? DiscussionStatus::ChuaTraLoi;
            $discussion->update(['status' => $back, 'status_before_hide' => null, 'hidden_reason' => null, 'hidden_by_id' => null, 'hidden_at' => null]);
            Audit::log('DISCUSSION_UNHIDE', $actor, $discussion, ['status' => 'DA_AN'], ['status' => $back->value]);

            return $discussion;
        });
    }

    public static function requireActive(Enrollment $enrollment): void
    {
        $validity = EnrollmentValidityChecker::for($enrollment);
        if ($validity !== EnrollmentValidity::ConHan) {
            throw new DomainRejection($validity === EnrollmentValidity::DaThuHoi
                ? 'Quyền học đã bị thu hồi.' : 'Quyền học đã hết hạn, bạn chỉ đọc được.', 'body');
        }
    }

    private static function mayMark(User $actor, Discussion $discussion, Course $course): void
    {
        $owner = $discussion->user_id === $actor->id && $actor->isActive();
        if (! $owner && ! AuthoringScope::isCurrentAuthor($actor, $course)) {
            throw DomainRejection::forbidden('Chỉ người hỏi hoặc Giáo viên của khóa được chấp nhận câu trả lời.');
        }
        if ($discussion->status === DiscussionStatus::DaAn || $discussion->status === DiscussionStatus::ChuaTraLoi) {
            throw new DomainRejection('Câu hỏi chưa có trả lời hoặc đã bị ẩn.', 'reply');
        }
    }

    public static function text(string $text, string $field, int $max = 2000): string
    {
        $text = trim($text);
        if ($text === '' || mb_strlen($text) > $max) {
            throw new DomainRejection('Nội dung từ 1 đến '.number_format($max, 0, ',', '.').' ký tự.', $field);
        }

        return $text;
    }
}
