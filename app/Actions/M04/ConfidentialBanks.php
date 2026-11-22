<?php

namespace App\Actions\M04;

use App\Enums\M04\QuizKind;
use App\Enums\M18\ExamStatus;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M18\ExamEvent;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;

/**
 * M04 ngân hàng bảo mật (BR-ND-63/69/70): bật cờ khi chưa có câu nào nằm trong quiz bài học;
 * tắt cờ chỉ khi mọi kỳ thi dùng câu của ngân hàng đã công bố (và không còn kỳ chưa công bố).
 */
final class ConfidentialBanks
{
    public function set(User $actor, QuestionBank $bank, bool $confidential): QuestionBank
    {
        return Tx::run(function () use ($actor, $bank, $confidential) {
            $course = CourseLocks::course($bank->course_id);
            AuthoringScope::ensure($actor, $course);
            $bank = QuestionBank::query()->whereKey($bank->id)->lockForUpdate()->firstOrFail();
            if ($bank->is_confidential === $confidential) {
                return $bank;
            }
            $questionIds = Question::query()->where('question_bank_id', $bank->id)->select('id');
            if ($confidential) {
                $inLessonQuiz = QuizQuestion::query()->whereIn('question_id', $questionIds)->whereNull('removed_at')
                    ->whereIn('quiz_id', Quiz::query()->where('kind', QuizKind::BaiHoc)->select('id'))->exists();
                if ($inLessonQuiz) {
                    throw new DomainRejection('Ngân hàng đang có câu dùng trong quiz bài học, không bật bảo mật được.', 'is_confidential');
                }
                // E38, AC-04-16: còn hoạt động luyện tập đang dùng ngân hàng.
                $inActivity = DB::table('activity_question_bank')->join('activities', 'activities.id', '=', 'activity_question_bank.activity_id')
                    ->where('activity_question_bank.question_bank_id', $bank->id)->whereNull('activities.archived_at')->exists();
                if ($inActivity) {
                    throw new DomainRejection('Ngân hàng đang dùng cho hoạt động luyện tập, không bật bảo mật được.', 'is_confidential');
                }
            } else {
                $examQuizIds = QuizQuestion::query()->whereIn('question_id', $questionIds)
                    ->whereIn('quiz_id', Quiz::query()->where('kind', QuizKind::ThiThu)->select('id'))->select('quiz_id');
                $pending = ExamEvent::query()->whereIn('quiz_id', $examQuizIds)->where('status', '!=', ExamStatus::DaCongBo)->exists();
                if ($pending) {
                    throw new DomainRejection('Còn kỳ thi dùng ngân hàng này chưa công bố kết quả (BR-ND-69).', 'is_confidential');
                }
            }
            $bank->forceFill(['is_confidential' => $confidential])->save();
            Audit::log($confidential ? 'QUESTION_BANK_CONFIDENTIAL_ON' : 'QUESTION_BANK_CONFIDENTIAL_OFF', $actor, $bank, null, null);

            return $bank;
        });
    }
}
