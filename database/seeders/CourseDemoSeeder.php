<?php

namespace Database\Seeders;

use App\Actions\M03\AssignCourseAuthoring;
use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M04\QuestionBankActions;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Enums\M03\LearningMode;
use App\Enums\M04\QuestionType;
use App\Enums\M05\LessonType;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Seed demo P1: K1 đang bán (Hóa), K2 chờ duyệt (Bình), K3 nháp quá hạn (Hóa). Chạy lặp an toàn:
 * bỏ qua khi đã có khóa. Mọi bước đi qua Action như thao tác thật.
 */
class CourseDemoSeeder extends Seeder
{
    public function run(): void
    {
        if (Course::query()->exists()) {
            return;
        }
        $lan = User::query()->where('email', 'lan.pham@edunest.test')->firstOrFail();
        $minh = User::query()->where('email', 'minh@edunest.test')->firstOrFail();
        $hoa = User::query()->where('email', 'hoa.tran@edunest.test')->firstOrFail();
        $binh = User::query()->where('email', 'binh.gv@edunest.test')->firstOrFail();
        $edit = app(EditDraftContent::class);
        $flow = app(VersionTransitions::class);

        $k1 = $this->course($lan, $hoa, 'Hóa 12 nền tảng', 'Hóa học', 'Cơ bản', 499000, '2026-12-15');
        $v1 = $this->draft($k1);
        $s = $edit->addSection($hoa, $v1->id, 'Chương 1. Este – Lipit');
        foreach (['Khái niệm este', 'Tính chất hóa học của este', 'Lipit và chất béo'] as $title) {
            $l = $edit->addLesson($hoa, $s->id, $title, LessonType::BaiDoc);
            $edit->saveLesson($hoa, $l->id, ['content' => '<p>Nội dung bài "'.$title.'".</p>', 'is_required' => true, 'is_preview' => $title === 'Khái niệm este']);
        }
        $bank = app(QuestionBankActions::class)->createBank($hoa, $k1, 'Chương 1');
        $ids = [];
        foreach ([['Công thức của etanol?', ['CH3OH', 'C2H5OH', 'C3H7OH', 'CH3COOH'], 1], ['Este có mùi thơm của hoa quả?', ['Đúng', 'Sai'], 0], ['Chất béo là trieste của glixerol với?', ['Axit béo', 'Ancol', 'Anđehit', 'Xeton'], 0]] as [$stem, $opts, $correct]) {
            $ids[] = app(QuestionBankActions::class)->createQuestion($hoa, $bank, [
                'type' => count($opts) === 2 ? QuestionType::DungSai : QuestionType::MotDapAn,
                'stem' => $stem,
                'options' => array_map(fn ($o, $i) => ['content' => $o, 'is_correct' => $i === $correct], $opts, array_keys($opts)),
            ])->id;
        }
        $quiz = $edit->addLesson($hoa, $s->id, 'Kiểm tra chương 1', LessonType::Quiz);
        $edit->saveQuiz($hoa, $quiz->id, 70, 10, 3);
        $edit->setQuizQuestions($hoa, $quiz->id, $ids);
        $edit->saveLesson($hoa, $quiz->id, ['is_required' => true, 'is_final_test' => true]);
        $flow->submit($hoa, $v1);
        $flow->approve($minh, $v1->fresh() ?? $v1);
        $flow->publish($lan, $v1->fresh() ?? $v1);
        app(ChangeSaleStatus::class)->start($lan, $k1->fresh() ?? $k1);

        $k2 = $this->course($lan, $binh, 'Vật lý 11 – Dao động cơ', 'Vật lý', 'Nâng cao', 599000, '2026-12-20');
        $v2 = $this->draft($k2);
        $s2 = $edit->addSection($binh, $v2->id, 'Chương 1. Dao động điều hòa');
        foreach (['Phương trình dao động', 'Con lắc lò xo'] as $title) {
            $l = $edit->addLesson($binh, $s2->id, $title, LessonType::BaiDoc);
            $edit->saveLesson($binh, $l->id, ['content' => '<p>'.$title.'.</p>', 'is_required' => true]);
        }
        $flow->submit($binh, $v2);

        $this->course($lan, $hoa, 'Hóa 11 hữu cơ', 'Hóa học', 'Luyện thi', 549000, '2026-11-15');
    }

    private function course(User $giaoVu, User $author, string $title, string $category, string $level, int $price, string $due): Course
    {
        return app(AssignCourseAuthoring::class)->handle($giaoVu, [
            'title' => $title,
            'category_id' => Category::query()->where('name', $category)->whereNotNull('parent_id')->value('id'),
            'level_id' => Level::query()->where('name', $level)->value('id'),
            'learning_mode' => LearningMode::TuanTu,
            'price' => $price,
            'access_months' => 12,
            'author_id' => $author->id,
            'due_date' => $due,
        ]);
    }

    private function draft(Course $course): CourseVersion
    {
        return CourseVersion::query()->where('course_id', $course->id)->whereNotNull('open_draft_key')->firstOrFail();
    }
}
