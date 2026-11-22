<?php

namespace Tests\Support;

use App\Actions\M10\SaveAttemptAnswer;
use App\Actions\M10\StartQuizAttempt;
use App\Actions\M10\SubmitQuizAttempt;
use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M05\LessonType;
use App\Enums\M08\EnrollmentSource;
use App\Models\M03\Course;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\M08\Enrollment;
use App\Models\M09\LessonProgress;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use App\Support\M10\QuizScore;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Pest\Support\HigherOrderTapProxy;
use Tests\TestCase;

final class HocTap
{
    public User $a;

    public User $g;

    public User $t;

    public Course $course;

    public CourseVersion $version;

    public Enrollment $enrollment;

    public Lesson $read;

    public Lesson $video;

    public Lesson $quizLesson;

    public Lesson $optional;

    public Quiz $quiz;

    public LessonResource $resource;

    public function __construct(LearningMode $mode = LearningMode::TuDo, int $limit = 3, int $questionCount = 3)
    {
        $this->a = self::student('an');
        $this->g = taiKhoan('giaovu-p4@edunest.test', RoleCode::GiaoVu);
        $reviewer = taiKhoan('duyet-p4@edunest.test', RoleCode::GiaoVu);
        $this->t = taiKhoan('giaovien-p4@edunest.test', RoleCode::GiaoVien);
        $this->course = KhoaHoc::giao($this->g, $this->t, ['learning_mode' => $mode->value]);
        $this->version = KhoaHoc::banMo($this->course);
        $section = KhoaHoc::chuong($this->version);
        $this->read = KhoaHoc::bai($section, 'Bài đọc');
        $this->video = KhoaHoc::bai($section, 'Video 10 phút', LessonType::Video, [
            'video_path' => 'lessons/video/p4.mp4', 'video_duration_ms' => 600_000,
        ]);
        Storage::disk('local')->put('lessons/video/p4.mp4', str_repeat('v', 2048));
        Storage::disk('local')->put('lessons/resources/p4.pdf', '%PDF-1.4');
        $this->resource = $this->video->resources()->create([
            'title' => 'Tài liệu', 'file_path' => 'lessons/resources/p4.pdf', 'original_name' => 'p4.pdf',
            'mime_type' => 'application/pdf', 'size_bytes' => 8, 'position' => 1,
        ]);
        $this->quizLesson = KhoaHoc::bai($section, 'Bài kiểm tra', LessonType::Quiz, ['is_final_test' => true]);
        $this->optional = KhoaHoc::bai($section, 'Đọc thêm', attrs: ['is_required' => false]);
        $bank = KhoaHoc::nganHang($this->course, $this->t, n: $questionCount);
        $this->quiz = KhoaHoc::quiz($this->quizLesson, $bank->questions()->pluck('id')->all(), attempts: $limit);
        KhoaHoc::guiDuyet($this->version, $this->t);
        KhoaHoc::duyet($this->version, $reviewer);
        $this->version = KhoaHoc::congBo($this->version, $this->g);
        $this->course->refresh();
        $this->enrollment = $this->enroll($this->a);
    }

    public static function student(string $name): User
    {
        return taiKhoan($name.'-p4@edunest.test', RoleCode::HocVien);
    }

    public static function at(string $time): CarbonImmutable
    {
        return CarbonImmutable::parse('2026-11-18 '.$time, 'Asia/Ho_Chi_Minh')->utc();
    }

    public function enroll(User $user, ?CourseVersion $version = null): Enrollment
    {
        return Enrollment::query()->create([
            'user_id' => $user->id, 'course_id' => $this->course->id,
            'course_version_id' => ($version ?? $this->version)->id,
            'source' => EnrollmentSource::MaKichHoat, 'granted_at' => now(),
        ])->refresh();
    }

    public function mark(Lesson $lesson, ?Enrollment $enrollment = null): LessonProgress
    {
        return LessonProgress::query()->updateOrCreate([
            'enrollment_id' => ($enrollment ?? $this->enrollment)->id, 'lesson_id' => $lesson->id,
        ], ['started_at' => now(), 'completed_at' => now()]);
    }

    public function start(?User $user = null, ?Enrollment $enrollment = null): QuizAttempt
    {
        return app(StartQuizAttempt::class)->handle($user ?? $this->a, $enrollment ?? $this->enrollment, $this->quiz);
    }

    public function fill(QuizAttempt $attempt, int $correct, ?User $user = null): void
    {
        foreach ($this->quiz->items()->get()->take($correct) as $question) {
            $options = QuizScore::options($question);
            $answer = array_values(array_column(array_filter($options, fn (array $choice) => $choice['is_correct']), 'id'));
            app(SaveAttemptAnswer::class)->handle($user ?? $this->a, $attempt, $question, $answer);
        }
    }

    public function submit(int $correct = 0): QuizAttempt
    {
        $attempt = $this->start();
        $this->fill($attempt, $correct);

        return app(SubmitQuizAttempt::class)->handle($this->a, $attempt);
    }

    public function lessonUrl(string $action = 'show', ?Lesson $lesson = null): string
    {
        return route('student.lessons.'.$action, ['course' => $this->course->id, 'lesson' => ($lesson ?? $this->video)->id]);
    }

    public function quizUrl(string $action = 'show'): string
    {
        return route('student.quizzes.'.$action, ['course' => $this->course->id, 'quiz' => $this->quiz->id]);
    }

    public function signIn(TestCase|HigherOrderTapProxy $test, ?User $user = null, ?string $cookie = null): TestCase
    {
        return $test->signIn($user ?? $this->a)->withCredentials()
            ->withCookie(config('session.cookie'), session()->getId())
            ->withCookie('edunest_device', $cookie ?? Str::repeat('x', 64));
    }
}
