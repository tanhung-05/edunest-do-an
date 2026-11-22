<?php

namespace App\Actions\M18;

use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuizKind;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;

/**
 * QT-19 a1–a2: Giáo viên soạn đề thi thử (quiz `THI_THU`) từ một ngân hàng bảo mật của khóa mình được giao
 * (BR-ND-63, BR-TH-01). Sửa được tới khi đề bị khóa (kỳ đầu sang `SAP_MO`, BR-TH-04/05).
 */
final class ExamPapers
{
    /** @param list<int> $questionIds */
    public function create(User $teacher, QuestionBank $bank, string $title, array $questionIds): Quiz
    {
        $title = self::title($title);

        return Tx::run(function () use ($teacher, $bank, $title, $questionIds) {
            $bank = $this->guard($teacher, $bank);
            $quiz = Quiz::query()->create([
                'kind' => QuizKind::ThiThu, 'title' => $title, 'question_bank_id' => $bank->id, 'owner_teacher_id' => $teacher->id,
            ]);
            $this->sync($quiz, $bank, $questionIds);

            return $quiz;
        });
    }

    /** @param list<int> $questionIds */
    public function update(User $teacher, Quiz $quiz, string $title, array $questionIds): Quiz
    {
        $title = self::title($title);

        return Tx::run(function () use ($teacher, $quiz, $title, $questionIds) {
            $bank = $this->guard($teacher, QuestionBank::query()->findOrFail($quiz->question_bank_id));
            $quiz = Quiz::query()->whereKey($quiz->id)->lockForUpdate()->firstOrFail();
            if ($quiz->kind !== QuizKind::ThiThu || $quiz->owner_teacher_id !== $teacher->id) {
                throw DomainRejection::forbidden('Đề không phải của bạn.');
            }
            if ($quiz->locked_at !== null) {
                throw new DomainRejection('Đề đã khóa vì có kỳ thi sắp mở (BR-TH-04).', 'questions');
            }
            $quiz->update(['title' => $title]);
            $this->sync($quiz, $bank, $questionIds);

            return $quiz;
        });
    }

    private function guard(User $teacher, QuestionBank $bank): QuestionBank
    {
        if (! $teacher->hasEffectiveRole(RoleCode::GiaoVien)) {
            throw DomainRejection::forbidden('Chỉ Giáo viên soạn đề thi (BR-TH-01).');
        }
        $course = CourseLocks::course($bank->course_id);
        AuthoringScope::ensure($teacher, $course);
        $bank = QuestionBank::query()->whereKey($bank->id)->lockForUpdate()->firstOrFail();
        if (! $bank->is_confidential) {
            throw new DomainRejection('Đề thi chỉ lấy câu từ ngân hàng bảo mật (BR-ND-63).', 'question_bank_id');
        }

        return $bank;
    }

    /** @param list<int> $questionIds */
    private function sync(Quiz $quiz, QuestionBank $bank, array $questionIds): void
    {
        $questionIds = array_values(array_unique(array_map('intval', $questionIds)));
        if ($questionIds === [] || count($questionIds) > 200) {
            throw new DomainRejection('Đề cần từ 1 đến 200 câu.', 'questions');
        }
        $found = Question::query()->whereIn('id', $questionIds)->where('question_bank_id', $bank->id)
            ->where('status', QuestionStatus::DangDung)->pluck('id')->all();
        if (count($found) !== count($questionIds)) {
            throw new DomainRejection('Có câu không thuộc ngân hàng bảo mật này hoặc đã ngừng dùng.', 'questions');
        }
        QuizQuestion::query()->where('quiz_id', $quiz->id)->whereNull('removed_at')->update(['removed_at' => now()]);
        foreach ($questionIds as $i => $id) {
            QuizQuestion::query()->create(['quiz_id' => $quiz->id, 'question_id' => $id, 'position' => $i + 1]);
        }
    }

    private static function title(string $title): string
    {
        $title = trim($title);
        if ($title === '' || mb_strlen($title) > 200) {
            throw new DomainRejection('Tên đề từ 1 đến 200 ký tự.', 'title');
        }

        return $title;
    }
}
