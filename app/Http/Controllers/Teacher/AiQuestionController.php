<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M25\AiQuestions;
use App\Http\Controllers\Controller;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M05\Lesson;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** MH-GV-08 phần AI (QT-27a): gợi ý câu từ bài, duyệt, bỏ nháp, khôi phục. */
class AiQuestionController extends Controller
{
    public function generate(Request $request, QuestionBank $bank, AiQuestions $ai): RedirectResponse
    {
        Gate::authorize('createQuestion', $bank);
        $data = $request->validate([
            'lesson_id' => ['required', 'integer'],
            'count' => ['required', 'integer', 'min:1', 'max:'.AiQuestions::MAX_PER_REQUEST],
        ]);
        $lesson = Lesson::query()->whereKey($data['lesson_id'])->firstOrFail();
        $made = $ai->generate($request->user(), $bank, $lesson, (int) $data['count']);

        return back()->with('status', 'AI đã gợi ý '.count($made).' câu nháp. Đọc kỹ rồi duyệt từng câu.');
    }

    public function approve(Request $request, Question $question, AiQuestions $ai): RedirectResponse
    {
        Gate::authorize('update', $question);
        $ai->approve($request->user(), $question);

        return back()->with('status', 'Đã duyệt câu, câu chuyển sang Đang dùng.');
    }

    public function discard(Request $request, Question $question, AiQuestions $ai): RedirectResponse
    {
        Gate::authorize('update', $question);
        $ai->discard($request->user(), $question);

        return back()->with('status', 'Đã bỏ câu nháp.');
    }

    public function restore(Request $request, Question $question, AiQuestions $ai): RedirectResponse
    {
        Gate::authorize('update', $question);
        $ai->restore($request->user(), $question);

        return back()->with('status', 'Đã khôi phục câu về nháp.');
    }
}
