<?php

namespace App\Console\Commands;

use App\Actions\M18\ExamTaking;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionType;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Models\M01\Role;
use App\Models\M03\Course;
use App\Models\M04\QuizQuestion;
use App\Models\M18\ExamEvent;
use App\Models\User;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;
use Database\Seeders\DemoSeeder;
use Database\Seeders\ExamDemoSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Đặc tả §14 đoạn 5: tạo một kỳ thi thử đang mở có 30 bài đã nộp (29 học viên demo và một bài của giáo viên, gắn
 * "Không xếp hạng"), đóng sau `--close-in` phút. Tác vụ nền TV-12 (`schedule:work`) tự đóng và công bố, xếp hạng.
 * Chỉ chạy ở môi trường local hoặc khi bật chế độ demo.
 */
final class DemoExam extends Command
{
    protected $signature = 'edunest:demo-exam {--close-in=2 : Số phút tới lúc đóng kỳ thi (1–60)}';

    protected $description = 'Demo: tạo kỳ thi thử có 30 bài làm, tự đóng và công bố sau vài phút';

    public function handle(ExamTaking $taking): int
    {
        if (! app()->environment(['local', 'testing']) && ! config()->boolean('app.demo')) {
            $this->error('Lệnh chỉ dùng cho môi trường demo.');

            return self::FAILURE;
        }
        $minutes = (int) $this->option('close-in');
        if ($minutes < 1 || $minutes > 60) {
            $this->error('--close-in phải từ 1 đến 60 phút.');

            return self::FAILURE;
        }
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->whereNotNull('current_version_id')->oldest('id')->first();
        $teacher = User::query()->where('email', 'hoa.tran@edunest.test')->first();
        if ($course === null || $teacher === null) {
            $this->error('Thiếu dữ liệu demo: chạy php artisan db:seed trước.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now()->floorMinute();
        $stamp = CarbonImmutable::now('Asia/Ho_Chi_Minh')->format('H:i d/m');
        $paper = (new ExamDemoSeeder)->paper($course, $teacher, 'Đề demo trực tiếp '.$stamp);
        $event = ExamEvent::query()->create([
            'quiz_id' => $paper->id, 'title' => 'Thi thử trực tiếp '.$stamp, 'created_by_id' => $teacher->id,
            'status' => ExamStatus::DangMo, 'opens_at' => $now->subMinute(), 'closes_at' => $now->addMinutes($minutes + 1),
            'duration_minutes' => 45, 'audience' => ExamAudience::CongKhai, 'requires_registration' => false,
            'scheduled_at' => $now->subDay(), 'opened_at' => $now->subMinute(),
        ]);

        $items = QuizQuestion::query()->where('quiz_id', $paper->id)->orderBy('position')->get();
        $people = [...$this->students(29), $teacher];
        foreach ($people as $i => $person) {
            if ($i % 3 === 0) {
                $taking->consent($person, $event, true);
            }
            $attempt = $taking->start($person, $event->fresh() ?? $event);
            // Mỗi người đúng một số câu khác nhau để bảng xếp hạng có điểm trải đều và có hạng đồng.
            $correct = ($i * 7) % ($items->count() + 1);
            foreach ($items->values() as $k => $item) {
                $taking->save($person, $attempt, $item, $k < $correct ? $this->right($item) : null);
            }
            $taking->submit($person, $attempt);
        }

        $this->info("Đã tạo kỳ \"{$event->title}\" (id {$event->id}) với ".count($people).' bài nộp, đóng lúc '
            .$event->closes_at->setTimezone('Asia/Ho_Chi_Minh')->format('H:i').' giờ VN. Chạy php artisan schedule:work để tự đóng và công bố.');

        return self::SUCCESS;
    }

    /** @return list<User> */
    private function students(int $count): array
    {
        $roleId = Role::idFor(RoleCode::HocVien);
        $out = [];
        for ($n = 1; $n <= $count; $n++) {
            $email = sprintf('thi-demo-%02d@edunest.test', $n);
            $user = User::query()->where('email', $email)->first();
            if ($user === null) {
                $user = new User(['name' => sprintf('Học viên demo %02d', $n), 'email' => $email, 'password' => Hash::make(DemoSeeder::PASSWORD)]);
                $user->status = AccountStatus::HoatDong;
                $user->email_verified_at = now();
                $user->save();
                $user->roles()->syncWithoutDetaching([$roleId => ['assigned_at' => now()]]);
            }
            $out[] = $user;
        }

        return $out;
    }

    private function right(QuizQuestion $item): mixed
    {
        $options = QuizScore::options($item);

        return match ($item->snapshot['type'] ?? null) {
            QuestionType::DienTu->value => $options[0]['content'] ?? null,
            QuestionType::SapXep->value => array_column(collect($options)->sortBy('position')->all(), 'id'),
            default => array_column(array_filter($options, fn ($o) => $o['is_correct']), 'id'),
        };
    }
}
