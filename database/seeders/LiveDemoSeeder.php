<?php

namespace Database\Seeders;

use App\Models\M03\CourseAuthor;
use App\Models\M08\Enrollment;
use App\Models\M26\LiveSession;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;

/**
 * Dữ liệu mẫu P8 M26 (QT-28): một buổi sắp tới (ngày mai 19:00 giờ VN) và một buổi đã qua cho khóa đầu tiên `an.nguyen`
 * đang học, do giáo viên đang được giao khóa lên lịch. Chạy lại không tạo trùng.
 */
final class LiveDemoSeeder extends Seeder
{
    public function run(): void
    {
        $an = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        if ($an === null || LiveSession::query()->exists()) {
            return;
        }
        foreach (Enrollment::query()->where('user_id', $an->id)->orderBy('id')->get() as $enrollment) {
            $author = CourseAuthor::query()->where('course_id', $enrollment->course_id)->whereNotNull('current_key')->value('user_id');
            if ($author === null) {
                continue;
            }
            $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->startOfDay();
            foreach ([[$today->addDay()->setTime(19, 0), 'Chữa đề và hỏi đáp trực tiếp'], [$today->subDays(3)->setTime(19, 0), 'Ôn tập chương 1']] as [$at, $title]) {
                LiveSession::query()->create([
                    'course_id' => $enrollment->course_id, 'title' => $title, 'description' => 'Chuẩn bị câu hỏi trước buổi học.',
                    'starts_at' => $at->utc(), 'duration_minutes' => 60, 'meeting_url' => 'https://meet.google.com/edu-nest-demo',
                    'created_by_id' => $author, 'updated_by_id' => $author,
                ]);
            }

            return;
        }
    }
}
