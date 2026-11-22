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
        $v1->forceFill([
            'summary' => 'Hóa học 12 từ nền tảng: este, lipit, carbohydrate – học chắc lý thuyết, làm được bài tập cơ bản của đề thi tốt nghiệp.',
            'objectives' => "Gọi tên và viết công thức este, chất béo.\nViết phương trình thủy phân trong môi trường acid và base.\nGiải bài tập xà phòng hóa bằng bảo toàn khối lượng.",
            'description' => "Khóa học do thầy Trần Minh Hóa biên soạn, gồm bài đọc ngắn có ví dụ, video minh họa và bài kiểm tra cuối chương. Phù hợp học sinh lớp 12 muốn lấy lại gốc môn Hóa.\n\nHoàn thành các bài bắt buộc và đạt bài kiểm tra cuối chương để nhận chứng nhận hoàn thành khóa học.",
        ])->save();
        $s = $edit->addSection($hoa, $v1->id, 'Chương 1. Este – Lipit');
        $k1Content = [
            'Khái niệm este' => '<p>Khi thay nhóm OH trong nhóm carboxyl của carboxylic acid bằng nhóm OR′ ta được <strong>este</strong>. Công thức chung của este no, đơn chức, mạch hở: C<sub>n</sub>H<sub>2n</sub>O<sub>2</sub> (n ≥ 2).</p><h3>Danh pháp</h3><p>Tên este = tên gốc R′ + tên gốc acid (đuôi "at"). Ví dụ: CH<sub>3</sub>COOC<sub>2</sub>H<sub>5</sub> là ethyl acetate; HCOOCH<sub>3</sub> là methyl formate.</p><h3>Tính chất vật lí</h3><p>Este thường là chất lỏng, nhẹ hơn nước, ít tan trong nước và có mùi thơm đặc trưng: isoamyl acetate có mùi chuối chín, ethyl butyrate có mùi dứa.</p>',
            'Tính chất hóa học của este' => '<p><strong>Thủy phân trong môi trường acid</strong> là phản ứng thuận nghịch: RCOOR′ + H<sub>2</sub>O ⇌ RCOOH + R′OH (xúc tác H<sub>2</sub>SO<sub>4</sub>, t°).</p><p><strong>Thủy phân trong môi trường base</strong> (phản ứng xà phòng hóa) xảy ra một chiều: RCOOR′ + NaOH → RCOONa + R′OH.</p><h3>Ví dụ</h3><p>Xà phòng hóa 8,8 g ethyl acetate (0,1 mol) bằng NaOH vừa đủ thu được 0,1 mol CH<sub>3</sub>COONa, tức 8,2 g muối.</p>',
            'Lipit và chất béo' => '<p><strong>Chất béo</strong> là trieste của glycerol với acid béo (acid đơn chức, mạch dài, không phân nhánh, số C chẵn). Ví dụ tristearin (C<sub>17</sub>H<sub>35</sub>COO)<sub>3</sub>C<sub>3</sub>H<sub>5</sub>.</p><ul><li>Chất béo no (mỡ động vật) thường ở thể rắn.</li><li>Chất béo không no (dầu thực vật) thường ở thể lỏng.</li></ul><p>Hydrogen hóa dầu lỏng tạo bơ thực vật rắn. Xà phòng hóa chất béo tạo xà phòng và glycerol – cơ sở của ngành sản xuất xà phòng.</p>',
        ];
        foreach (['Khái niệm este', 'Tính chất hóa học của este', 'Lipit và chất béo'] as $title) {
            $l = $edit->addLesson($hoa, $s->id, $title, LessonType::BaiDoc);
            $edit->saveLesson($hoa, $l->id, ['content' => $k1Content[$title], 'is_required' => true, 'is_preview' => $title === 'Khái niệm este']);
        }
        $bank = app(QuestionBankActions::class)->createBank($hoa, $k1, 'Chương 1');
        $ids = [];
        foreach ([['Công thức phân tử chung của este no, đơn chức, mạch hở là', ['CₙH₂ₙO (n ≥ 1)', 'CₙH₂ₙO₂ (n ≥ 2)', 'CₙH₂ₙ₊₂O (n ≥ 1)', 'CₙH₂ₙ₋₂O₂ (n ≥ 3)'], 1], ['Isoamyl acetate có mùi chuối chín.', ['Đúng', 'Sai'], 0], ['Chất béo là trieste của glycerol với', ['Acid béo', 'Ethanol', 'Aldehyde', 'Ketone'], 0], ['Phản ứng thủy phân este trong môi trường base còn gọi là', ['Phản ứng este hóa', 'Phản ứng xà phòng hóa', 'Phản ứng hydrogen hóa', 'Phản ứng trùng hợp'], 1], ['Tên của CH₃COOC₂H₅ là', ['Methyl acetate', 'Ethyl acetate', 'Ethyl formate', 'Propyl acetate'], 1]] as [$stem, $opts, $correct]) {
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
        $v2->forceFill([
            'summary' => 'Dao động cơ lớp 11: dao động điều hòa, con lắc lò xo, con lắc đơn và năng lượng dao động.',
            'objectives' => "Viết phương trình dao động điều hòa từ điều kiện ban đầu.\nTính chu kì, tần số của con lắc lò xo và con lắc đơn.\nVận dụng bảo toàn cơ năng trong dao động.",
            'description' => 'Khóa học do thầy Đỗ Văn Bình biên soạn, có hình động minh họa vòng tròn lượng giác giúp hiểu bản chất pha dao động.',
        ])->save();
        $s2 = $edit->addSection($binh, $v2->id, 'Chương 1. Dao động điều hòa');
        $k2Content = [
            'Phương trình dao động' => '<p>Dao động điều hòa có li độ biến thiên theo hàm cos của thời gian: x = Acos(ωt + φ), trong đó A là biên độ, ω là tần số góc, φ là pha ban đầu.</p><p>Vận tốc v = −ωAsin(ωt + φ) sớm pha π/2 so với li độ; gia tốc a = −ω<sup>2</sup>x luôn hướng về vị trí cân bằng.</p>',
            'Con lắc lò xo' => '<p>Con lắc lò xo gồm vật khối lượng m gắn vào lò xo độ cứng k. Chu kì T = 2π√(m/k) không phụ thuộc biên độ.</p><p>Cơ năng W = ½kA<sup>2</sup> được bảo toàn khi bỏ qua ma sát. Ví dụ: m = 100 g, k = 40 N/m thì T ≈ 0,314 s.</p>',
        ];
        foreach (['Phương trình dao động', 'Con lắc lò xo'] as $title) {
            $l = $edit->addLesson($binh, $s2->id, $title, LessonType::BaiDoc);
            $edit->saveLesson($binh, $l->id, ['content' => $k2Content[$title], 'is_required' => true]);
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
