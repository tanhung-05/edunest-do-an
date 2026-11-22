<?php

namespace Database\Seeders;

use App\Actions\M18\ExamClock;
use App\Actions\M18\ExamEvents;
use App\Actions\M18\ExamTaking;
use App\Enums\M04\QuestionType;
use App\Enums\M04\QuizKind;
use App\Enums\M05\LessonType;
use App\Enums\M17\DiscussionStatus;
use App\Enums\M18\ExamAudience;
use App\Enums\M18\ExamStatus;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\M17\Discussion;
use App\Models\M17\DiscussionReply;
use App\Models\M17\LessonNote;
use App\Models\M18\ExamEvent;
use App\Models\User;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Dữ liệu mẫu P6 (QT-18/19): ngân hàng bảo mật, một đề thi thử, ba kỳ (đã công bố có xếp hạng và vinh danh, đang mở,
 * sắp mở bắt buộc đăng ký), hỏi đáp và ghi chú ở khóa "Hóa 12 nền tảng". Ghi thẳng bản ghi phía người soạn vì tài khoản
 * nhân sự mẫu chưa bật 2FA; lượt thi đi qua Action thật. Chạy lại không tạo trùng.
 */
final class ExamDemoSeeder extends Seeder
{
    public function run(): void
    {
        $course = Course::query()->where('title', 'Hóa 12 nền tảng')->whereNotNull('current_version_id')->oldest('id')->first();
        $author = User::query()->where('email', 'hoa.tran@edunest.test')->first();
        if ($course === null || $author === null || QuestionBank::query()->where('course_id', $course->id)->where('is_confidential', true)->exists()) {
            return;
        }
        $this->community($course, $author);
        $paper = $this->paper($course, $author);
        $students = User::query()->whereIn('email', ['dang-hoc.p4@edunest.test', 'cho-tao.p4@edunest.test', 'pdf-loi.p4@edunest.test', 'chi.le@edunest.test', 'an.nguyen@edunest.test'])
            ->orderBy('id')->get()->values();

        // Kỳ 1: đã công bố tuần trước, có xếp hạng; hai người đồng ý công khai tên → vinh danh trang chủ.
        $start = CarbonImmutable::now('Asia/Ho_Chi_Minh')->subDays(7)->setTime(19, 0)->utc();
        $past = $this->event($paper, $author, 'Thi thử Hóa 12 – tuần 1', $start, $start->addHours(2), 45, ExamStatus::DangMo);
        $taking = app(ExamTaking::class);
        foreach ($students as $i => $student) {
            Carbon::setTestNow($start->subMinutes(30));
            if ($i < 2) {
                $taking->consent($student, $past, true);
            }
            Carbon::setTestNow($start->addMinutes(1 + $i));
            $attempt = $taking->start($student, $past->fresh() ?? $past);
            $items = QuizQuestion::query()->where('quiz_id', $paper->id)->orderBy('position')->get();
            foreach ($items->take(max(1, $items->count() - $i)) as $item) {
                $right = QuizScore::options($item);
                $answer = match ($item->snapshot['type'] ?? null) {
                    QuestionType::DienTu->value => $right[0]['content'] ?? null,
                    QuestionType::SapXep->value => array_column(collect($right)->sortBy('position')->all(), 'id'),
                    default => array_column(array_filter($right, fn ($o) => $o['is_correct']), 'id'),
                };
                $taking->save($student, $attempt, $item, $answer);
            }
            Carbon::setTestNow($start->addMinutes(20 + 3 * $i));
            $taking->submit($student, $attempt);
        }
        Carbon::setTestNow($start->addHours(2));
        app(ExamClock::class)->close($past->id, CarbonImmutable::now());
        app(ExamClock::class)->publish($past->id, CarbonImmutable::now());
        Carbon::setTestNow();

        // Kỳ 2: đang mở (công khai, không bắt buộc đăng ký) — để thử phòng thi. Kỳ 3: sắp mở, bắt buộc đăng ký.
        $now = CarbonImmutable::now()->floorMinute();
        $paper2 = $this->paper($course, $author, 'Đề luyện tập Hóa 12 – số 2');
        $this->event($paper2, $author, 'Thi thử Hóa 12 – tuần 2', $now->subMinutes(30), $now->addDays(3), 45, ExamStatus::DangMo);
        $paper3 = $this->paper($course, $author, 'Đề luyện tập Hóa 12 – số 3');
        $this->event($paper3, $author, 'Thi thử Hóa 12 – cuối kỳ', $now->addDays(2), $now->addDays(2)->addHours(3), 60, ExamStatus::SapMo, true, ExamAudience::Khoa, [$course->id]);
    }

    public function paper(Course $course, User $author, string $title = 'Đề luyện tập Hóa 12 – số 1'): Quiz
    {
        $bank = QuestionBank::query()->firstOrCreate(['course_id' => $course->id, 'name' => 'Đề thi (bảo mật)'],
            ['is_confidential' => true, 'created_by_id' => $author->id]);
        if (! $bank->questions()->exists()) {
            $rows = [
                [QuestionType::MotDapAn, 'Số hiệu nguyên tử của cacbon là?', [['5', false], ['6', true], ['12', false], ['14', false]], 'Cacbon có 6 proton.'],
                [QuestionType::MotDapAn, 'Chất nào là ancol?', [['CH3COOH', false], ['C2H5OH', true], ['CH3CHO', false], ['HCOOCH3', false]], 'Nhóm -OH gắn cacbon no.'],
                [QuestionType::NhieuDapAn, 'Chọn các kim loại kiềm.', [['Na', true], ['K', true], ['Mg', false], ['Al', false]], 'Nhóm IA.'],
                [QuestionType::DungSai, 'Nước cất dẫn điện tốt.', [['Đúng', false], ['Sai', true]], 'Nước cất gần như không dẫn điện.'],
                [QuestionType::DienTu, 'Công thức hóa học của nước là ___', [['H2O', true], ['H₂O', true]], null],
                [QuestionType::SapXep, 'Sắp xếp theo số proton tăng dần.', [['H', true], ['He', true], ['Li', true], ['Be', true]], 'H(1) < He(2) < Li(3) < Be(4).'],
            ];
            foreach ($rows as [$type, $stem, $options, $explanation]) {
                $q = Question::query()->create(['question_bank_id' => $bank->id, 'type' => $type, 'difficulty' => 'TRUNG_BINH', 'stem' => $stem,
                    'points' => 1, 'explanation' => $explanation, 'status' => 'DANG_DUNG', 'created_by_id' => $author->id, 'updated_by_id' => $author->id]);
                foreach ($options as $i => [$content, $correct]) {
                    $q->options()->create(['content' => $content, 'is_correct' => $correct, 'position' => $i + 1]);
                }
            }
        }
        $quiz = Quiz::query()->create(['kind' => QuizKind::ThiThu, 'title' => $title, 'question_bank_id' => $bank->id,
            'owner_teacher_id' => $author->id, 'locked_at' => now()]);
        foreach ($bank->questions()->with('options')->orderBy('id')->get()->values() as $i => $q) {
            QuizQuestion::query()->create(['quiz_id' => $quiz->id, 'question_id' => $q->id, 'position' => $i + 1, 'points' => $q->points,
                'snapshot' => ExamEvents::snapshot($q), 'snapshotted_at' => now()]);
        }

        return $quiz;
    }

    /** @param list<int> $courseIds */
    private function event(Quiz $quiz, User $author, string $title, CarbonImmutable $opens, CarbonImmutable $closes, int $minutes, ExamStatus $status,
        bool $register = false, ExamAudience $audience = ExamAudience::CongKhai, array $courseIds = []): ExamEvent
    {
        $event = ExamEvent::query()->create([
            'quiz_id' => $quiz->id, 'title' => $title, 'created_by_id' => $author->id, 'status' => $status, 'opens_at' => $opens,
            'closes_at' => $closes, 'duration_minutes' => $minutes, 'audience' => $audience, 'requires_registration' => $register,
            'scheduled_at' => $opens->subDays(3), 'opened_at' => $status === ExamStatus::DangMo ? $opens : null,
        ]);
        foreach ($courseIds as $id) {
            $event->courseLinks()->create(['course_id' => $id]);
        }

        return $event;
    }

    private function community(Course $course, User $author): void
    {
        $video = Lesson::query()->where('course_version_id', $course->current_version_id)->where('type', LessonType::Video)->whereNull('removed_at')->orderBy('position')->first();
        if ($video === null) {
            return;
        }
        $askers = Enrollment::query()->where('course_id', $course->id)->where('course_version_id', $course->current_version_id)->with('user')->limit(2)->get();
        foreach ($askers->values() as $i => $enrollment) {
            $d = Discussion::query()->create([
                'course_id' => $course->id, 'lesson_key' => $video->lesson_key, 'lesson_id' => $video->id, 'user_id' => $enrollment->user_id,
                'body' => $i === 0 ? 'Thầy ơi, đoạn này vì sao cân bằng phương trình lại nhân 2 ở vế trái ạ?' : 'Bài này có tài liệu tóm tắt công thức không ạ?',
                'video_second' => $i === 0 ? 95 : null, 'status' => DiscussionStatus::ChuaTraLoi,
            ]);
            if ($i === 0) {
                $reply = DiscussionReply::query()->create(['discussion_id' => $d->id, 'user_id' => $author->id,
                    'body' => 'Vì số nguyên tử O ở hai vế phải bằng nhau: vế phải có 2 phân tử H2O nên vế trái cần 2 H2.']);
                $d->update(['status' => DiscussionStatus::DaChapNhan, 'accepted_reply_id' => $reply->id]);
                LessonNote::query()->create(['user_id' => $enrollment->user_id, 'course_id' => $course->id, 'lesson_key' => $video->lesson_key,
                    'lesson_id' => $video->id, 'video_second' => 60, 'body' => 'Nhớ: bảo toàn nguyên tố trước, rồi mới cân bằng hệ số.']);
            }
        }
    }
}
