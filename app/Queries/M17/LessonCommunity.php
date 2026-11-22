<?php

namespace App\Queries\M17;

use App\Enums\M08\EnrollmentValidity;
use App\Enums\M17\DiscussionStatus;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionFollow;
use App\Models\M17\DiscussionReply;
use App\Models\M17\LessonNote;
use App\Models\User;
use App\Support\M08\EnrollmentValidityChecker;
use Illuminate\Support\Collection;

/** QT-18 (MH-HV-03): tab Hỏi đáp và Ghi chú của bài; đọc được cả khi hết hạn (CR-78), viết khi `CON_HAN`. */
final class LessonCommunity
{
    /** @return array<string, mixed> */
    public static function forStudent(User $user, Enrollment $enrollment, Lesson $lesson): array
    {
        $active = EnrollmentValidityChecker::for($enrollment) === EnrollmentValidity::ConHan;
        $discussions = Discussion::query()->where('course_id', $enrollment->course_id)->where('lesson_key', $lesson->lesson_key)
            ->where('status', '!=', DiscussionStatus::DaAn)->with(['user:id,name'])->orderByDesc('id')->limit(100)->get();
        $following = DiscussionFollow::query()->where('user_id', $user->id)->whereIn('discussion_id', $discussions->pluck('id'))->pluck('discussion_id')->flip();
        $replies = DiscussionReply::query()->whereIn('discussion_id', $discussions->pluck('id'))->with('user:id,name')->orderBy('id')->get()->groupBy('discussion_id');

        return [
            'can_write' => $active,
            'ask_url' => route('student.discussions.store', ['course' => $enrollment->course_id, 'lesson' => $lesson->id]),
            'note_url' => route('student.notes.store', ['course' => $enrollment->course_id, 'lesson' => $lesson->id]),
            'discussions' => $discussions->map(fn (Discussion $d) => self::row($d, $replies->get($d->id, collect()), $user, $active, $following->has($d->id), false))->all(),
            'notes' => LessonNote::query()->where('user_id', $user->id)->where('course_id', $enrollment->course_id)->where('lesson_key', $lesson->lesson_key)
                ->orderByRaw('video_second IS NULL, video_second')->orderBy('id')->get()->map(fn (LessonNote $n) => [
                    'id' => $n->id, 'body' => $n->body, 'second' => $n->video_second,
                    'update_url' => route('student.notes.update', $n), 'delete_url' => route('student.notes.destroy', $n),
                ])->all(),
        ];
    }

    /**
     * @param  Collection<int, DiscussionReply>  $replies
     * @return array<string, mixed>
     */
    public static function row(Discussion $d, $replies, User $viewer, bool $canWrite, bool $following, bool $teacher): array
    {
        $isAsker = $d->user_id === $viewer->id;
        $canAccept = $teacher || ($isAsker && $canWrite);

        return [
            'id' => $d->id,
            'body' => $d->body,
            'second' => $d->video_second,
            'status' => $d->status->value,
            'status_label' => $d->status->label(),
            'author' => $d->user?->name,
            'mine' => $isAsker,
            'created_at' => $d->created_at?->toIso8601String(),
            'follower_count' => $d->follower_count,
            'following' => $following,
            'follow_url' => ! $teacher && ! $isAsker && $canWrite ? route('student.discussions.follow', $d) : null,
            'unaccept_url' => $canAccept && $d->accepted_reply_id !== null
                ? route($teacher ? 'teacher.discussions.unaccept' : 'student.discussions.unaccept', $d) : null,
            'replies' => $replies->map(fn (DiscussionReply $r) => [
                'id' => $r->id, 'body' => $r->body, 'author' => $r->user?->name, 'accepted' => $d->accepted_reply_id === $r->id,
                'created_at' => $r->created_at?->toIso8601String(),
                'accept_url' => $canAccept && $d->accepted_reply_id !== $r->id
                    ? route($teacher ? 'teacher.discussions.accept' : 'student.discussions.accept', ['discussion' => $d->id, 'reply' => $r->id]) : null,
            ])->values()->all(),
        ];
    }
}
