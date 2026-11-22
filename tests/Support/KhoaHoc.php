<?php

namespace Tests\Support;

use App\Actions\M03\AssignCourseAuthoring;
use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M05\VersionTransitions;
use App\Enums\M03\LearningMode;
use App\Enums\M04\QuestionType;
use App\Enums\M04\QuizKind;
use App\Enums\M05\LessonType;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseSection;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\User;
use Illuminate\Support\Str;

/** Fixture khóa học P1 (07 §3): dựng qua Action khi là bước nghiệp vụ, ghi thẳng khi chỉ là dữ liệu nền. */
final class KhoaHoc
{
    public static function danhMuc(): Category
    {
        $root = Category::query()->firstOrCreate(['parent_id' => null, 'name' => 'Khoa học tự nhiên'], ['slug' => 'khoa-hoc-tu-nhien', 'position' => 1]);

        return Category::query()->firstOrCreate(['parent_id' => $root->id, 'name' => 'Hóa học'], ['slug' => 'hoa-hoc', 'position' => 1]);
    }

    public static function capDo(): Level
    {
        return Level::query()->firstOrCreate(['name' => 'Cơ bản'], ['position' => 1]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    public static function duLieu(User $author, array $overrides = []): array
    {
        return array_merge([
            'title' => 'Hóa 12 nền tảng',
            'category_id' => self::danhMuc()->id,
            'level_id' => self::capDo()->id,
            'learning_mode' => LearningMode::TuanTu->value,
            'price' => 499000,
            'original_price' => null,
            'access_months' => 12,
            'extension_price' => null,
            'author_id' => $author->id,
            'due_date' => '2026-11-30',
        ], $overrides);
    }

    /** Giáo vụ tạo khóa và giao soạn qua Action (PB-1). */
    public static function giao(User $giaoVu, User $author, array $overrides = []): Course
    {
        $d = self::duLieu($author, $overrides);
        $d['learning_mode'] = LearningMode::from($d['learning_mode']);

        return app(AssignCourseAuthoring::class)->handle($giaoVu, $d);
    }

    public static function banMo(Course $course): CourseVersion
    {
        return CourseVersion::query()->where('course_id', $course->id)->whereNotNull('open_draft_key')->firstOrFail();
    }

    public static function chuong(CourseVersion $v, string $title = 'Chương 1'): CourseSection
    {
        $pos = (int) CourseSection::query()->where('course_version_id', $v->id)->max('position');

        return CourseSection::query()->create(['course_version_id' => $v->id, 'title' => $title, 'position' => $pos + 1]);
    }

    /**
     * Ghi thẳng một bài (dữ liệu nền). Mặc định: bài viết bắt buộc có nội dung.
     *
     * @param  array<string, mixed>  $attrs
     */
    public static function bai(CourseSection $s, string $title, LessonType $type = LessonType::BaiDoc, array $attrs = []): Lesson
    {
        $pos = (int) Lesson::query()->where('course_section_id', $s->id)->max('position');
        $defaults = ['is_required' => true];
        if ($type === LessonType::BaiDoc) {
            $defaults['content'] = '<p>Nội dung '.$title.'</p>';
        }
        $lesson = Lesson::query()->create(array_merge($defaults, [
            'course_section_id' => $s->id,
            'course_version_id' => $s->course_version_id,
            'lesson_key' => (string) Str::ulid(),
            'title' => $title,
            'type' => $type,
            'position' => $pos + 1,
        ], $attrs));
        if ($type === LessonType::Quiz) {
            Quiz::query()->create(['course_version_id' => $s->course_version_id, 'lesson_id' => $lesson->id, 'kind' => QuizKind::BaiHoc]);
        }

        return $lesson;
    }

    /** Bản có n bài viết bắt buộc đủ nội dung (đạt danh sách kiểm). */
    public static function duDieuKien(Course $course, int $n = 3): CourseVersion
    {
        $v = self::banMo($course);
        $s = self::chuong($v);
        for ($i = 1; $i <= $n; $i++) {
            self::bai($s, "Bài {$i}");
        }

        return $v;
    }

    /** Ngân hàng có n câu một đáp án (B đúng). */
    public static function nganHang(Course $course, User $author, string $name = 'Chương 1', int $n = 10): QuestionBank
    {
        $bank = app(QuestionBankActions::class)->createBank($author, $course, $name);
        for ($i = 1; $i <= $n; $i++) {
            self::cau($bank, $author, "Câu {$i}?");
        }

        return $bank;
    }

    public static function cau(QuestionBank $bank, User $author, string $stem, string $correct = 'B'): Question
    {
        return app(QuestionBankActions::class)->createQuestion($author, $bank, [
            'type' => QuestionType::MotDapAn,
            'stem' => $stem,
            'options' => array_map(fn ($l) => ['content' => $l, 'is_correct' => $l === $correct], ['A', 'B', 'C', 'D']),
        ]);
    }

    /** Gắn câu vào quiz (ghi thẳng, bản nháp) và đặt cấu hình đủ. */
    public static function quiz(Lesson $lesson, array $questionIds, ?int $pass = 70, ?int $duration = 10, ?int $attempts = 3): Quiz
    {
        $quiz = Quiz::query()->where('lesson_id', $lesson->id)->firstOrFail();
        $quiz->forceFill(['pass_percent' => $pass, 'duration_minutes' => $duration, 'max_attempts' => $attempts])->save();
        foreach (array_values($questionIds) as $i => $qid) {
            QuizQuestion::query()->create(['quiz_id' => $quiz->id, 'question_id' => $qid, 'position' => $i + 1]);
        }

        return $quiz;
    }

    public static function guiDuyet(CourseVersion $v, User $author): CourseVersion
    {
        return app(VersionTransitions::class)->submit($author, $v);
    }

    public static function duyet(CourseVersion $v, User $giaoVu): CourseVersion
    {
        return app(VersionTransitions::class)->approve($giaoVu, $v->fresh() ?? $v);
    }

    public static function congBo(CourseVersion $v, User $giaoVu): CourseVersion
    {
        return app(VersionTransitions::class)->publish($giaoVu, $v->fresh() ?? $v);
    }

    /** Khóa đã công bố bản 1 (và bật bán nếu cần). */
    public static function daCongBo(User $giaoVu, User $author, User $duyetBoi, bool $banNgay = false, array $overrides = []): Course
    {
        $course = self::giao($giaoVu, $author, $overrides);
        $v = self::duDieuKien($course);
        self::guiDuyet($v, $author);
        self::duyet($v, $duyetBoi);
        self::congBo($v, $giaoVu);
        if ($banNgay) {
            app(ChangeSaleStatus::class)->start($giaoVu, $course->fresh() ?? $course);
        }

        return $course->fresh() ?? $course;
    }
}
