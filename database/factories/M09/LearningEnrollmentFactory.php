<?php

namespace Database\Factories\M09;

use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M04\QuestionType;
use App\Enums\M04\QuizKind;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M03\Course;
use App\Models\M04\QuestionBank;
use App\Models\M04\Quiz;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M08\Enrollment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Enrollment> */
final class LearningEnrollmentFactory extends Factory
{
    protected $model = Enrollment::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->role(RoleCode::HocVien),
            'course_version_id' => fn () => self::version()->id,
            'course_id' => fn (array $attributes) => CourseVersion::query()->whereKey($attributes['course_version_id'])->firstOrFail()->course_id,
            'source' => EnrollmentSource::MaKichHoat,
            'learning_status' => LearningStatus::DangHoc,
            'granted_at' => now()->subDay(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn () => ['learning_status' => LearningStatus::HoanThanh, 'completed_at' => now()]);
    }

    public function expired(): static
    {
        return $this->state(fn () => ['het_han_luc' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['revoked_at' => now(), 'revoked_reason' => 'Ghi danh kiểm thử.']);
    }

    public static function lesson(int $versionId, LessonType $type): Lesson
    {
        $version = CourseVersion::query()->findOrFail($versionId);
        $section = $version->sections()->firstOrCreate(['position' => 1], ['title' => 'Chương kiểm thử']);
        $existing = $section->lessons()->where('type', $type)->first();

        return $existing ?? $section->lessons()->create([
            'course_version_id' => $version->id, 'lesson_key' => (string) Str::ulid(),
            'title' => $type->label(), 'type' => $type, 'position' => (int) $section->lessons()->max('position') + 1,
            'is_required' => true,
            ...($type === LessonType::Video ? ['video_duration_ms' => 600_000] : []),
        ]);
    }

    public static function quiz(Enrollment $enrollment): Quiz
    {
        $lesson = self::lesson($enrollment->course_version_id, LessonType::Quiz);
        if ($lesson->quiz !== null) {
            return $lesson->quiz;
        }
        $quiz = $lesson->quiz()->create([
            'course_version_id' => $enrollment->course_version_id, 'kind' => QuizKind::BaiHoc,
            'duration_minutes' => 10, 'pass_percent' => 70, 'max_attempts' => 3,
        ]);
        $course = $enrollment->course()->firstOrFail();
        $bank = QuestionBank::query()->create([
            'course_id' => $course->id, 'name' => 'Câu hỏi kiểm thử', 'created_by_id' => $course->created_by_id,
        ]);
        $question = $bank->questions()->create([
            'stem' => 'Chọn đáp án đúng', 'type' => QuestionType::MotDapAn,
            'created_by_id' => $course->created_by_id, 'updated_by_id' => $course->created_by_id,
        ]);
        $quiz->items()->create([
            'question_id' => $question->id, 'position' => 1, 'points' => '1.00', 'snapshotted_at' => now(),
            'snapshot' => [
                'stem' => $question->stem, 'type' => QuestionType::MotDapAn->value,
                'options' => [
                    ['id' => 1, 'content' => 'A', 'is_correct' => true],
                    ['id' => 2, 'content' => 'B', 'is_correct' => false],
                ],
            ],
        ]);

        return $quiz;
    }

    private static function version(): CourseVersion
    {
        $owner = User::factory()->create();
        $slug = 'khoa-p4-'.Str::lower((string) Str::ulid());
        $category = Category::query()->create(['name' => 'Danh mục '.$slug, 'slug' => $slug]);
        $level = Level::query()->create(['name' => 'Cấp độ '.$slug]);
        $course = Course::query()->create([
            'slug' => $slug, 'title' => 'Khóa học P4', 'category_id' => $category->id, 'level_id' => $level->id,
            'learning_mode' => LearningMode::TuDo, 'price' => 499000, 'created_by_id' => $owner->id,
        ]);
        $version = $course->versions()->create([
            'version_no' => 1, 'status' => CourseVersionStatus::DaCongBo,
            'published_at' => now(), 'published_by_id' => $owner->id,
        ]);
        $course->update(['current_version_id' => $version->id]);

        return $version;
    }
}
