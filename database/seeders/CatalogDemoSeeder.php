<?php

namespace Database\Seeders;

use App\Actions\M03\AssignCourseAuthoring;
use App\Actions\M03\ChangeSaleStatus;
use App\Actions\M05\EditDraftContent;
use App\Actions\M05\VersionTransitions;
use App\Enums\M03\LearningMode;
use App\Enums\M05\LessonType;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Seed demo P2 (09 P2.4): thêm khóa đang bán để thử phân trang (>12), lọc nhiều tổ hợp, một khóa ngừng bán,
 * bài xem thử dạng bài viết và PDF. Chạy lặp an toàn: bỏ qua khóa đã có cùng tên.
 */
class CatalogDemoSeeder extends Seeder
{
    public function run(): void
    {
        $lan = User::query()->where('email', 'lan.pham@edunest.test')->firstOrFail();
        $minh = User::query()->where('email', 'minh@edunest.test')->firstOrFail();
        $hoa = User::query()->where('email', 'hoa.tran@edunest.test')->firstOrFail();
        $binh = User::query()->where('email', 'binh.gv@edunest.test')->firstOrFail();
        $pdf = 'lessons/pdf/demo-de-cuong.pdf';
        Storage::disk('local')->put($pdf, self::pdf());

        $list = [
            ['Hóa 10 cơ bản', 'Hóa học', 'Cơ bản', $hoa, 299000, null],
            ['Hóa 11 vô cơ', 'Hóa học', 'Nâng cao', $hoa, 449000, 599000],
            ['Luyện đề Hóa THPT', 'Hóa học', 'Luyện thi', $hoa, 699000, 899000],
            ['Vật lý 10 cơ học', 'Vật lý', 'Cơ bản', $binh, 349000, null],
            ['Vật lý 12 điện xoay chiều', 'Vật lý', 'Nâng cao', $binh, 549000, 649000],
            ['Luyện đề Vật lý THPT', 'Vật lý', 'Luyện thi', $binh, 699000, null],
            ['Sinh học 12 di truyền', 'Sinh học', 'Cơ bản', $hoa, 399000, null],
            ['Lịch sử Việt Nam hiện đại', 'Lịch sử', 'Cơ bản', $binh, 249000, null],
            ['Địa lý 12 tự nhiên', 'Địa lý', 'Cơ bản', $binh, 249000, 299000],
            ['Tiếng Anh ngữ pháp nền tảng', 'Tiếng Anh', 'Cơ bản', $hoa, 399000, null],
            ['Tiếng Anh luyện đề THPT', 'Tiếng Anh', 'Luyện thi', $binh, 599000, 799000],
            ['Hóa học vui cho học sinh', 'Hóa học', 'Cơ bản', $hoa, 199000, null],
            ['Sinh học 11 trao đổi chất', 'Sinh học', 'Nâng cao', $binh, 449000, null],
            ['Tiếng Anh giao tiếp cơ bản', 'Tiếng Anh', 'Cơ bản', $hoa, 349000, 449000],
        ];
        foreach ($list as $i => [$title, $cat, $level, $author, $price, $orig]) {
            if (Course::query()->where('title', $title)->exists()) {
                continue;
            }
            $course = $this->publish($lan, $minh, $author, $title, $cat, $level, $price, $orig, $i % 3 === 0 ? $pdf : null);
            app(ChangeSaleStatus::class)->start($lan, $course);
            if ($title === 'Hóa học vui cho học sinh') {
                app(ChangeSaleStatus::class)->stop($lan, $course->fresh() ?? $course);
            }
        }
    }

    private function publish(User $lan, User $minh, User $author, string $title, string $cat, string $level, int $price, ?int $orig, ?string $pdf): Course
    {
        $course = app(AssignCourseAuthoring::class)->handle($lan, [
            'title' => $title,
            'category_id' => (int) Category::query()->where('name', $cat)->whereNotNull('parent_id')->value('id'),
            'level_id' => (int) Level::query()->where('name', $level)->value('id'),
            'learning_mode' => LearningMode::TuanTu,
            'price' => $price,
            'original_price' => $orig,
            'access_months' => $price >= 600000 ? null : 12,
            'author_id' => $author->id,
        ]);
        $v = CourseVersion::query()->where('course_id', $course->id)->whereNotNull('open_draft_key')->firstOrFail();
        $v->forceFill([
            'summary' => 'Khóa '.$title.' bám sát chương trình, học theo lộ trình rõ ràng.',
            'objectives' => "Nắm chắc kiến thức trọng tâm.\nLàm được dạng bài thường gặp.",
            'description' => '<p>Khóa gồm bài giảng ngắn, bài đọc và bài kiểm tra cuối chương.</p>',
        ])->save();
        $edit = app(EditDraftContent::class);
        $s = $edit->addSection($author, $v->id, 'Chương 1. Mở đầu');
        $l = $edit->addLesson($author, $s->id, 'Giới thiệu khóa học', LessonType::BaiDoc);
        $edit->saveLesson($author, $l->id, ['content' => '<p>Chào mừng bạn đến với '.e($title).'.</p>', 'is_required' => true, 'is_preview' => true]);
        if ($pdf !== null) {
            $p = $edit->addLesson($author, $s->id, 'Đề cương khóa (PDF)', LessonType::Pdf);
            Lesson::query()->whereKey($p->id)->update(['pdf_path' => $pdf, 'is_preview' => true]);
        }
        foreach (['Kiến thức trọng tâm', 'Bài tập vận dụng'] as $t) {
            $x = $edit->addLesson($author, $s->id, $t, LessonType::BaiDoc);
            $edit->saveLesson($author, $x->id, ['content' => '<p>'.$t.'.</p>', 'is_required' => true]);
        }
        $flow = app(VersionTransitions::class);
        $flow->submit($author, $v);
        $flow->approve($minh, $v->fresh() ?? $v);
        $flow->publish($lan, $v->fresh() ?? $v);

        return $course->fresh() ?? $course;
    }

    /** PDF một trang hợp lệ tối thiểu để thử xem thử. */
    private static function pdf(): string
    {
        $objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            null,
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];
        $stream = 'BT /F1 24 Tf 72 760 Td (EduNest - De cuong khoa hoc) Tj ET';
        $objs[3] = '<< /Length '.strlen($stream)." >>\nstream\n".$stream."\nendstream";
        $out = "%PDF-1.4\n";
        $offsets = [];
        foreach ($objs as $i => $o) {
            $offsets[] = strlen($out);
            $out .= ($i + 1)." 0 obj\n".$o."\nendobj\n";
        }
        $xref = strlen($out);
        $out .= "xref\n0 ".(count($objs) + 1)."\n0000000000 65535 f \n";
        foreach ($offsets as $off) {
            $out .= sprintf("%010d 00000 n \n", $off);
        }

        return $out.'trailer << /Size '.(count($objs) + 1)." /Root 1 0 R >>\nstartxref\n".$xref."\n%%EOF";
    }
}
