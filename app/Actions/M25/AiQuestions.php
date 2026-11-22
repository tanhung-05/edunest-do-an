<?php

namespace App\Actions\M25;

use App\Actions\M04\QuestionBankActions;
use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionSource;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M11\Setting;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\M25\AiProvider;
use App\Support\Tx;
use Throwable;

/**
 * QT-27a (02e §3): AI gợi ý câu Đúng/Sai từ một bài vào ngân hàng thường (`NHAP`, nguồn AI), Giáo viên duyệt (CH-5),
 * bỏ nháp (CH-6) và khôi phục (CH-7, CR-169). Mọi ghi khóa `courses` trước rồi `questions`, kiểm lại phạm vi giao khóa
 * và E38 (ngân hàng bảo mật) dưới khóa. Gợi ý câu không tính hạn mức ngày (chỉ 27b, 27c — QT-27 §2).
 */
final class AiQuestions
{
    public const MAX_PER_REQUEST = 10;

    public function __construct(private AiProvider $provider) {}

    /** @return list<Question> */
    public function generate(User $teacher, QuestionBank $bank, Lesson $lesson, int $count): array
    {
        if ($count < 1 || $count > self::MAX_PER_REQUEST) {
            throw new DomainRejection('Số câu gợi ý từ 1 đến '.self::MAX_PER_REQUEST.'.', 'count');
        }
        // Bước 2: chuẩn bị dữ liệu, chưa quyết định quyền gửi.
        $text = Tx::run(function () use ($teacher, $bank, $lesson) {
            self::gate($teacher, $bank, $lesson);

            return trim(($lesson->objectives ?? '').' '.($lesson->content ?? ''));
        });
        try {
            $drafts = $this->provider->suggestQuestions($text, $count);
        } catch (Throwable $e) {
            report($e);
            throw new DomainRejection('AI đang gặp lỗi, bạn thử lại sau.', 'count');
        }
        if ($drafts === []) {
            throw new DomainRejection('Bài này chưa đủ nội dung để AI gợi ý câu hỏi.', 'count');
        }

        // Lưu kết quả: kiểm lại phiên, vai, khóa được giao, ngân hàng đích và cờ AI.
        return Tx::run(function () use ($teacher, $bank, $lesson, $drafts) {
            [$teacher, $bank] = self::gate($teacher, $bank, $lesson);
            $out = [];
            foreach ($drafts as $d) {
                $q = Question::query()->create([
                    'question_bank_id' => $bank->id, 'type' => QuestionType::DungSai, 'difficulty' => QuestionDifficulty::TrungBinh,
                    'stem' => mb_substr($d['stem'], 0, 2000), 'points' => 1, 'explanation' => $d['explanation'],
                    'status' => QuestionStatus::Nhap, 'source' => QuestionSource::Ai,
                    'created_by_id' => $teacher->id, 'updated_by_id' => $teacher->id,
                ]);
                $q->options()->create(['content' => 'Đúng', 'is_correct' => $d['is_true'], 'position' => 1]);
                $q->options()->create(['content' => 'Sai', 'is_correct' => ! $d['is_true'], 'position' => 2]);
                $out[] = $q;
            }
            Audit::log('AI_QUESTIONS_SUGGESTED', $teacher, $bank, null, ['lesson_id' => $lesson->id, 'count' => count($out)]);

            return $out;
        });
    }

    /** CH-5: `NHAP` → `DANG_DUNG`, đặt `ai_first_approved_at` lần đầu. Kiểm điều kiện câu theo QT-04 (BR-ND-88). */
    public function approve(User $teacher, Question $question): Question
    {
        return $this->transition($teacher, $question, QuestionStatus::Nhap, function (Question $q) use ($teacher) {
            $options = [];
            foreach ($q->options()->get() as $o) {
                $options[] = ['content' => $o->content, 'is_correct' => $o->is_correct];
            }
            QuestionBankActions::validateOptions($q->type, $options);
            if (trim($q->stem) === '') {
                throw new DomainRejection('Câu hỏi chưa có nội dung.', 'general');
            }
            $q->forceFill(['status' => QuestionStatus::DangDung, 'updated_by_id' => $teacher->id,
                'ai_first_approved_at' => $q->ai_first_approved_at ?? now()])->save();
        });
    }

    /** CH-6 (A9): bỏ câu AI nháp chưa từng duyệt, giữ bản ghi và nguồn. */
    public function discard(User $teacher, Question $question): Question
    {
        return $this->transition($teacher, $question, QuestionStatus::Nhap, function (Question $q) use ($teacher) {
            $q->forceFill(['status' => QuestionStatus::NgungDung, 'updated_by_id' => $teacher->id])->save();
        });
    }

    /** CH-7 (A8, CR-169): khôi phục câu AI chưa từng duyệt về `NHAP`. */
    public function restore(User $teacher, Question $question): Question
    {
        return $this->transition($teacher, $question, QuestionStatus::NgungDung, function (Question $q) use ($teacher) {
            if ($q->ai_first_approved_at !== null) {
                throw new DomainRejection('Câu đã từng duyệt: dùng lệnh Dùng lại của ngân hàng.', 'general');
            }
            $q->forceFill(['status' => QuestionStatus::Nhap, 'updated_by_id' => $teacher->id])->save();
        });
    }

    /** @param  callable(Question): void  $apply */
    private function transition(User $teacher, Question $question, QuestionStatus $from, callable $apply): Question
    {
        return Tx::run(function () use ($teacher, $question, $from, $apply) {
            $bank = QuestionBank::query()->findOrFail($question->question_bank_id);
            $course = CourseLocks::course($bank->course_id);
            AuthoringScope::ensure($teacher, $course);
            $bank = QuestionBank::query()->whereKey($bank->id)->sharedLock()->firstOrFail();
            if ($bank->is_confidential) {
                throw new DomainRejection('Ngân hàng bảo mật không dùng AI.', 'general');
            }
            $q = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            if ($q->source !== QuestionSource::Ai) {
                throw new DomainRejection('Chỉ áp dụng cho câu do AI gợi ý.', 'general');
            }
            if ($q->status !== $from) {
                throw new DomainRejection('Trạng thái câu đã thay đổi, tải lại trang.', 'general');
            }
            $old = $q->status->value;
            $apply($q);
            Audit::log('AI_QUESTION_'.$q->status->value, $teacher, $q, ['status' => $old], ['status' => $q->status->value]);

            return $q;
        });
    }

    /**
     * Khóa khóa học, kiểm Giáo viên được giao, ngân hàng thường (E38), bài thuộc khóa, cờ AI (X2).
     *
     * @return array{0: User, 1: QuestionBank}
     */
    private static function gate(User $teacher, QuestionBank $bank, Lesson $lesson): array
    {
        $course = CourseLocks::course($bank->course_id);
        AuthoringScope::ensure($teacher, $course);
        $flag = Setting::query()->where('key', 'ai.enabled')->sharedLock()->first();
        if (($flag === null ? Setting::DEFAULTS['ai.enabled'] : $flag->value) !== true) {
            throw new DomainRejection('Trợ lý AI đang tắt.', 'count');
        }
        $bank = QuestionBank::query()->whereKey($bank->id)->sharedLock()->firstOrFail();
        if ($bank->is_confidential) {
            throw new DomainRejection('Ngân hàng bảo mật không dùng AI.', 'question_bank_id');
        }
        $versionCourse = CourseVersion::query()->whereKey($lesson->course_version_id)->value('course_id');
        if ((int) $versionCourse !== $bank->course_id || $lesson->removed_at !== null) {
            throw new DomainRejection('Bài nguồn không thuộc khóa của ngân hàng.', 'lesson_id');
        }

        return [$teacher, $bank];
    }
}
