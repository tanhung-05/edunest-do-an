<?php

namespace App\Actions\M04;

use App\Actions\M03\CourseTaxonomy;
use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionSource;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Enums\M05\CourseVersionStatus;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\QuizQuestion;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\M10\QuizScore;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Http\UploadedFile;

/**
 * QT-04 (03 §5, 03 §14): CreateQuestionBank, RenameQuestionBank, CreateQuestion, UpdateQuestion,
 * DeactivateQuestion (CH-2), ReactivateQuestion (CH-3). Mọi ghi khóa courses trước, đọc lại người soạn hiện tại.
 */
final class QuestionBankActions
{
    public function createBank(User $actor, Course $course, string $name): QuestionBank
    {
        return $this->bankWrite(function () use ($actor, $course, $name) {
            $course = CourseLocks::course($course->id);
            AuthoringScope::ensure($actor, $course);
            $name = self::name($name);
            if (QuestionBank::query()->where('course_id', $course->id)->where('name', $name)->exists()) {
                throw new DomainRejection('Khóa đã có ngân hàng cùng tên.', 'name');
            }

            return QuestionBank::query()->create(['course_id' => $course->id, 'name' => $name, 'created_by_id' => $actor->id]);
        });
    }

    public function renameBank(User $actor, QuestionBank $bank, string $name): QuestionBank
    {
        return $this->bankWrite(function () use ($actor, $bank, $name) {
            $course = CourseLocks::course($bank->course_id);
            AuthoringScope::ensure($actor, $course);
            $name = self::name($name);
            if (QuestionBank::query()->where('course_id', $course->id)->where('name', $name)->whereKeyNot($bank->id)->exists()) {
                throw new DomainRejection('Khóa đã có ngân hàng cùng tên.', 'name');
            }
            $bank->forceFill(['name' => $name])->save();

            return $bank;
        });
    }

    /**
     * CH-1: lưu câu thủ công `DANG_DUNG`, nguồn Giáo viên.
     *
     * @param  array{type: QuestionType, difficulty?: QuestionDifficulty, stem: string, points?: float|string|null, hint?: string|null, explanation?: string|null, options: list<array{content: string, is_correct: bool}>, tag_ids?: list<int>}  $data
     */
    public function createQuestion(User $actor, QuestionBank $bank, array $data, ?UploadedFile $image = null): Question
    {
        $options = self::validateOptions($data['type'], $data['options']);
        $points = self::points($data['points'] ?? null);
        $imagePath = $image?->store('question-images', 'public');

        return Tx::run(function () use ($actor, $bank, $data, $options, $points, $imagePath) {
            $course = CourseLocks::course($bank->course_id);
            AuthoringScope::ensure($actor, $course);
            CourseTaxonomy::lockTags($data['tag_ids'] ?? []);
            $q = Question::query()->create([
                'question_bank_id' => $bank->id,
                'type' => $data['type'],
                'difficulty' => $data['difficulty'] ?? QuestionDifficulty::TrungBinh,
                'stem' => trim($data['stem']),
                'image_path' => is_string($imagePath) ? $imagePath : null,
                'points' => $points,
                'hint' => $data['hint'] ?? null,
                'explanation' => $data['explanation'] ?? null,
                'status' => QuestionStatus::DangDung,
                'source' => QuestionSource::GiaoVien,
                'created_by_id' => $actor->id,
                'updated_by_id' => $actor->id,
            ]);
            foreach ($options as $i => $o) {
                $q->options()->create(['content' => $o['content'], 'is_correct' => $o['is_correct'], 'position' => $i + 1]);
            }
            $q->tags()->sync($data['tag_ids'] ?? []);

            return $q;
        });
    }

    /**
     * Sửa câu trong ngân hàng: không ảnh hưởng bản chụp đã có (CR-18, E30).
     *
     * @param  array{difficulty?: QuestionDifficulty, stem?: string, points?: float|string|null, hint?: string|null, explanation?: string|null, options?: list<array{content: string, is_correct: bool}>, tag_ids?: list<int>}  $data
     */
    public function updateQuestion(User $actor, Question $question, array $data, ?UploadedFile $image = null): Question
    {
        $options = isset($data['options']) ? self::validateOptions($question->type, $data['options']) : null;
        $points = array_key_exists('points', $data) ? self::points($data['points']) : null;
        $imagePath = $image?->store('question-images', 'public');

        return Tx::run(function () use ($actor, $question, $data, $options, $points, $imagePath) {
            $course = CourseLocks::course((int) $question->bank->course_id);
            AuthoringScope::ensure($actor, $course);
            $newTags = array_values(array_diff($data['tag_ids'] ?? [], $question->tags()->pluck('tags.id')->map(fn ($v) => (int) $v)->all()));
            CourseTaxonomy::lockTags($newTags);
            $q = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            $fill = ['updated_by_id' => $actor->id];
            foreach (['difficulty', 'hint', 'explanation'] as $k) {
                if (array_key_exists($k, $data)) {
                    $fill[$k] = $data[$k];
                }
            }
            if (isset($data['stem'])) {
                $fill['stem'] = trim($data['stem']);
            }
            if ($points !== null) {
                $fill['points'] = $points;
            }
            if (is_string($imagePath)) {
                $fill['image_path'] = $imagePath;
            }
            $q->forceFill($fill)->save();
            if ($options !== null) {
                $q->options()->delete();
                foreach ($options as $i => $o) {
                    $q->options()->create(['content' => $o['content'], 'is_correct' => $o['is_correct'], 'position' => $i + 1]);
                }
            }
            if (array_key_exists('tag_ids', $data)) {
                $q->tags()->sync($data['tag_ids']);
            }

            return $q;
        });
    }

    /** CH-2: ngừng dùng khi câu không còn trong quiz của bản `NHAP`/`TRA_LAI`/`CHO_DUYET`/`DA_DUYET`. */
    public function deactivate(User $actor, Question $question): Question
    {
        return Tx::run(function () use ($actor, $question) {
            $course = CourseLocks::course((int) $question->bank->course_id);
            AuthoringScope::ensure($actor, $course);
            $q = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            if ($q->status !== QuestionStatus::DangDung) {
                throw new DomainRejection('Câu không ở trạng thái đang dùng.', 'general');
            }
            $inOpenDraft = QuizQuestion::query()
                ->join('quizzes', 'quizzes.id', '=', 'quiz_questions.quiz_id')
                ->join('lessons', 'lessons.id', '=', 'quizzes.lesson_id')
                ->join('course_sections', 'course_sections.id', '=', 'lessons.course_section_id')
                ->join('course_versions', 'course_versions.id', '=', 'quizzes.course_version_id')
                ->where('quiz_questions.question_id', $q->id)
                ->whereNull('quiz_questions.removed_at')->whereNull('lessons.removed_at')->whereNull('course_sections.removed_at')
                ->whereIn('course_versions.status', [CourseVersionStatus::Nhap->value, CourseVersionStatus::TraLai->value, CourseVersionStatus::ChoDuyet->value, CourseVersionStatus::DaDuyet->value])
                ->exists();
            if ($inOpenDraft) {
                throw new DomainRejection('Câu đang nằm trong quiz của một bản chưa công bố: hãy bỏ câu khỏi quiz trước.', 'general');
            }
            $q->forceFill(['status' => QuestionStatus::NgungDung, 'updated_by_id' => $actor->id])->save();

            return $q;
        });
    }

    /** CH-3: dùng lại câu Giáo viên (hoặc câu AI đã từng duyệt, CR-169). */
    public function reactivate(User $actor, Question $question): Question
    {
        return Tx::run(function () use ($actor, $question) {
            $course = CourseLocks::course((int) $question->bank->course_id);
            AuthoringScope::ensure($actor, $course);
            $q = Question::query()->whereKey($question->id)->lockForUpdate()->firstOrFail();
            if ($q->status !== QuestionStatus::NgungDung) {
                throw new DomainRejection('Câu không ở trạng thái ngừng dùng.', 'general');
            }
            if ($q->source === QuestionSource::Ai && $q->ai_first_approved_at === null) {
                throw new DomainRejection('Câu AI chưa từng duyệt không dùng lại được.', 'general');
            }
            $q->forceFill(['status' => QuestionStatus::DangDung, 'updated_by_id' => $actor->id])->save();

            return $q;
        });
    }

    /**
     * BR-ND-88, BR-QZ-01: một đáp án 2–6 lựa chọn, đúng 1; nhiều đáp án 2–6, ≥ 1 đúng; đúng/sai hai lựa chọn cố định.
     *
     * @param  list<array{content: string, is_correct: bool}>  $options
     * @return list<array{content: string, is_correct: bool}>
     */
    public static function validateOptions(QuestionType $type, array $options): array
    {
        $options = array_map(fn ($o) => ['content' => trim((string) $o['content']), 'is_correct' => (bool) $o['is_correct']], $options);
        $correct = count(array_filter($options, fn ($o) => $o['is_correct']));
        if (in_array('', array_column($options, 'content'), true)) {
            throw new DomainRejection('Lựa chọn không được để trống.', 'options');
        }
        switch ($type) {
            case QuestionType::MotDapAn:
                if (count($options) < 2 || count($options) > 6) {
                    throw new DomainRejection('Câu một đáp án cần từ 2 đến 6 lựa chọn.', 'options');
                }
                if ($correct !== 1) {
                    throw new DomainRejection('Câu một đáp án cần đúng 1 lựa chọn đúng.', 'options');
                }
                break;
            case QuestionType::NhieuDapAn:
                if (count($options) < 2 || count($options) > 6) {
                    throw new DomainRejection('Câu nhiều đáp án cần từ 2 đến 6 lựa chọn.', 'options');
                }
                if ($correct < 1) {
                    throw new DomainRejection('Câu nhiều đáp án cần ít nhất 1 lựa chọn đúng.', 'options');
                }
                break;
            case QuestionType::DungSai:
                if ($correct !== 1) {
                    throw new DomainRejection('Câu đúng/sai cần chọn một đáp án đúng.', 'options');
                }
                $isTrue = $options[array_key_first(array_filter($options, fn ($o) => $o['is_correct']))]['content'] === 'Đúng';
                $options = [['content' => 'Đúng', 'is_correct' => $isTrue], ['content' => 'Sai', 'is_correct' => ! $isTrue]];
                break;
            case QuestionType::DienTu:
                // M04 (N): mỗi lựa chọn là một đáp án chấp nhận; so khớp theo QuizScore::normalize (CR-69).
                if (count($options) < 1 || count($options) > 10) {
                    throw new DomainRejection('Câu điền từ cần từ 1 đến 10 đáp án chấp nhận.', 'options');
                }
                $seen = [];
                foreach ($options as $o) {
                    $key = QuizScore::normalize($o['content']);
                    if (isset($seen[$key])) {
                        throw new DomainRejection('Hai đáp án chấp nhận trùng nhau sau khi chuẩn hóa.', 'options');
                    }
                    $seen[$key] = true;
                }
                $options = array_map(fn ($o) => ['content' => $o['content'], 'is_correct' => true], $options);
                break;
            case QuestionType::SapXep:
                // M04 (N): thứ tự nhập là thứ tự đúng; học viên thấy thứ tự xáo trộn.
                if (count($options) < 2 || count($options) > 8) {
                    throw new DomainRejection('Câu sắp xếp cần từ 2 đến 8 mục.', 'options');
                }
                $options = array_map(fn ($o) => ['content' => $o['content'], 'is_correct' => true], $options);
                break;
            default:
                throw new DomainRejection('Loại câu này chưa được hỗ trợ.', 'type');
        }

        return $options;
    }

    private static function points(float|string|null $points): string
    {
        if ($points === null || $points === '') {
            return '1.00';
        }
        if (! is_numeric($points) || (float) $points <= 0 || (float) $points > 9999.99) {
            throw new DomainRejection('Điểm phải lớn hơn 0.', 'points');
        }

        return number_format((float) $points, 2, '.', '');
    }

    private static function name(string $name): string
    {
        $name = trim((string) preg_replace('/\s+/u', ' ', $name));
        if ($name === '') {
            throw new DomainRejection('Hãy nhập tên ngân hàng.', 'name');
        }

        return $name;
    }

    /**
     * @template T
     *
     * @param  \Closure(): T  $work
     * @return T
     */
    private function bankWrite(\Closure $work): mixed
    {
        try {
            return Tx::run($work);
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw new DomainRejection('Khóa đã có ngân hàng cùng tên.', 'name');
            }
            throw $e;
        }
    }
}
