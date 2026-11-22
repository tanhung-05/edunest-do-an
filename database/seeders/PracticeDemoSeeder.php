<?php

namespace Database\Seeders;

use App\Actions\M20\ActivityAuthoring;
use App\Actions\M20\PracticeSessions;
use App\Actions\M22\FlashcardAuthoring;
use App\Actions\M22\FlashcardReviews;
use App\Enums\M04\QuestionType;
use App\Enums\M20\PracticeMode;
use App\Enums\M20\SessionStatus;
use App\Enums\M22\ReviewGrade;
use App\Models\M03\Course;
use App\Models\M04\QuestionBank;
use App\Models\M20\PracticeActivity;
use App\Models\M20\PracticeQuestion;
use App\Models\M22\Flashcard;
use App\Models\User;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dữ liệu mẫu P7 (QT-22..24) ở khóa "Hóa 12 nền tảng": một hoạt động luyện tập, một bộ thẻ ghi nhớ; học viên
 * `dang-hoc.p4` có chuỗi 3 ngày (phiên Learning ba ngày liên tiếp tới hôm nay) và vài thẻ đã chấm; `an.nguyen` có một phiên
 * để bảng xếp hạng tuần có hai người. Mọi bước đi qua Action thật. Chạy lại không tạo trùng.
 */
final class PracticeDemoSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->whereNotNull('current_version_id')->oldest('id')->first();
        $author = User::query()->where('email', 'hoa.tran@edunest.test')->first();
        $bank = $course === null ? null : QuestionBank::query()->where('course_id', $course->id)->where('is_confidential', false)->oldest('id')->first();
        if ($course === null || $author === null || $bank === null || PracticeActivity::query()->where('course_id', $course->id)->exists()) {
            return;
        }

        $activity = app(ActivityAuthoring::class)->save($author, $course, ['title' => 'Ôn nhanh chương 1', 'question_count' => 5,
            'draw_mode' => 'NGAU_NHIEN', 'seconds_per_question' => 30, 'bank_ids' => [$bank->id]]);
        app(ActivityAuthoring::class)->save($author, $course, ['title' => 'Luyện điểm yếu chương 1', 'question_count' => 5,
            'draw_mode' => 'DIEM_YEU', 'seconds_per_question' => 45, 'bank_ids' => [$bank->id]]);

        $cards = app(FlashcardAuthoring::class);
        $set = $cards->saveSet($author, $course, ['title' => 'Thuật ngữ Hóa 12', 'description' => 'Khái niệm cơ bản của chương este và lipit.', 'course_section_id' => null]);
        foreach ([
            ['Este', 'Hợp chất tạo thành khi thay nhóm OH của axit cacboxylic bằng nhóm OR'],
            ['Phản ứng xà phòng hóa', 'Thủy phân este trong môi trường kiềm'],
            ['Lipit', 'Hợp chất hữu cơ có trong tế bào sống, không tan trong nước'],
            ['Chất béo', 'Trieste của glixerol với axit béo'],
            ['Glixerol', 'Ancol ba chức C3H5(OH)3'],
            ['Axit béo', 'Axit đơn chức có mạch cacbon dài, không phân nhánh'],
            ['Ester', 'Este (tiếng Anh)'],
            ['Hydrolysis', 'Phản ứng thủy phân (tiếng Anh)'],
        ] as $i => [$front, $back]) {
            $cards->saveCard($author, $set, ['front' => $front, 'back' => $back, 'speech_lang' => $i >= 6 ? 'en-US' : 'vi-VN']);
        }

        $student = User::query()->where('email', 'dang-hoc.p4@edunest.test')->first();
        if ($student !== null) {
            $today = CarbonImmutable::now('Asia/Ho_Chi_Minh')->setTime(20, 0);
            foreach ([2, 1, 0] as $daysAgo) {
                $this->session($student, $activity, $today->subDays($daysAgo)->utc(), $daysAgo === 0 ? 1 : 0);
            }
            Carbon::setTestNow();
            $reviews = app(FlashcardReviews::class);
            $grades = [ReviewGrade::De, ReviewGrade::Nho, ReviewGrade::Kho, ReviewGrade::Quen];
            foreach (Flashcard::query()->where('flashcard_set_id', $set->id)->orderBy('position')->take(4)->get() as $i => $card) {
                $reviews->grade($student, $card, $grades[$i], $card->back_version, 'seed-p7-'.$card->id, CarbonImmutable::now()->subDay());
            }
        }

        $other = User::query()->where('email', 'an.nguyen@edunest.test')->first();
        if ($other !== null) {
            $this->session($other, $activity, CarbonImmutable::now()->subHours(3), 2);
            Carbon::setTestNow();
        }
    }

    /** Một phiên Learning làm hết câu: đúng lần 1, trừ `$wrong` câu cuối trả lời sai. */
    public function session(User $student, PracticeActivity $activity, CarbonImmutable $at, int $wrong): void
    {
        $practice = app(PracticeSessions::class);
        Carbon::setTestNow($at);
        $session = $practice->start($student, $activity, PracticeMode::Learning, $at);
        $step = 0;
        while ($session->status === SessionStatus::DangLam) {
            $at = $at->addSeconds(40);
            Carbon::setTestNow($at);
            $q = PracticeQuestion::query()->where('activity_session_id', $session->id)->where('position', $session->current_position)->firstOrFail();
            $right = $q->position <= $session->question_count - $wrong;
            $session = $practice->answer($student, $session, $q->position, $this->answer($q, $right), 'seed-p7-'.$session->id.'-'.(++$step), $at);
        }
    }

    private function answer(PracticeQuestion $q, bool $right): mixed
    {
        $options = QuizScore::options(PracticeSessions::grader($q));
        if (! $right) {
            return null;
        }

        return match ($q->snapshot['type'] ?? null) {
            QuestionType::DienTu->value => $options[0]['content'] ?? null,
            QuestionType::SapXep->value => array_column(collect($options)->sortBy('position')->all(), 'id'),
            default => array_column(array_filter($options, fn ($o) => $o['is_correct']), 'id'),
        };
    }
}
