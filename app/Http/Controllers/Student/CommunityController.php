<?php

namespace App\Http\Controllers\Student;

use App\Actions\M05\SwitchEnrollmentVersion;
use App\Actions\M17\Discussions;
use App\Actions\M17\LessonNotes;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionReply;
use App\Models\M17\LessonNote;
use App\Models\User;
use App\Queries\M09\LearningEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/** QT-18 hỏi đáp, ghi chú trong trình học; QT-21 chuyển phiên bản (MH-HV-03). */
final class CommunityController extends Controller
{
    public function ask(Request $request, Course $course, Lesson $lesson, Discussions $discussions): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'second' => ['nullable', 'integer', 'min:0']]);
        $user = $this->student($request);
        $discussions->ask($user, LearningEntry::enrollment($user, $course), $lesson, $data['body'], $data['second'] ?? null);

        return back()->with('status', 'Đã gửi câu hỏi.');
    }

    public function follow(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $on = $discussions->toggleFollow($this->student($request), $discussion);

        return back()->with('status', $on ? 'Đã đánh dấu "Cũng thắc mắc".' : 'Đã bỏ "Cũng thắc mắc".');
    }

    public function accept(Request $request, Discussion $discussion, DiscussionReply $reply, Discussions $discussions): RedirectResponse
    {
        $discussions->accept($this->student($request), $discussion, $reply);

        return back()->with('status', 'Đã chấp nhận câu trả lời.');
    }

    public function unaccept(Request $request, Discussion $discussion, Discussions $discussions): RedirectResponse
    {
        $discussions->unaccept($this->student($request), $discussion);

        return back()->with('status', 'Đã bỏ chấp nhận.');
    }

    public function storeNote(Request $request, Course $course, Lesson $lesson, LessonNotes $notes): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'second' => ['nullable', 'integer', 'min:0']]);
        $user = $this->student($request);
        $notes->create($user, LearningEntry::enrollment($user, $course), $lesson, $data['body'], $data['second'] ?? null);

        return back()->with('status', 'Đã lưu ghi chú.');
    }

    public function updateNote(Request $request, LessonNote $note, LessonNotes $notes): RedirectResponse
    {
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        $notes->update($this->student($request), $note, $data['body']);

        return back()->with('status', 'Đã sửa ghi chú.');
    }

    public function destroyNote(Request $request, LessonNote $note, LessonNotes $notes): RedirectResponse
    {
        $notes->delete($this->student($request), $note);

        return back()->with('status', 'Đã xóa ghi chú.');
    }

    public function switchVersion(Request $request, Enrollment $enrollment, SwitchEnrollmentVersion $switch): RedirectResponse
    {
        $switch->handle($this->student($request), $enrollment);

        // Bài của bản cũ không còn khớp ghi danh; quay về danh sách khóa để vào lại bài tiếp tục học.
        return to_route('student.courses.index')->with('status', 'Đã chuyển sang phiên bản mới. Tiến độ các bài không đổi được giữ lại.');
    }

    private function student(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
