<?php

namespace App\Actions\M05;

use App\Enums\M04\QuestionStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Models\M03\Course;
use App\Models\M04\Question;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseSection;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;

/**
 * PB-2 (QT-05 b1–3): sao chép bản `DA_CONG_BO` thành bản nháp mới. Chỉ chép cây còn sống, giữ khóa bài;
 * câu nguồn phải còn `DANG_DUNG` ở ngân hàng thường (CR-107). Trả các câu bị bỏ để báo người soạn.
 */
final class CreateDraftVersion
{
    /**
     * @return array{version: CourseVersion, dropped: list<array{lesson: string, question_id: int, stem: string}>}
     */
    public function handle(User $actor, Course $course): array
    {
        return Tx::run(function () use ($actor, $course) {
            $course = CourseLocks::course($course->id);
            AuthoringScope::ensure($actor, $course);
            $versions = CourseVersion::query()->where('course_id', $course->id)->orderBy('id')->lockForUpdate()->get();
            if ($versions->contains(fn (CourseVersion $v) => $v->getAttribute('open_draft_key') !== null)) {
                throw new DomainRejection('Khóa đã có một bản nháp đang mở.', 'general');
            }
            /** @var CourseVersion|null $source */
            $source = $versions->first(fn (CourseVersion $v) => $v->status === CourseVersionStatus::DaCongBo);
            if ($source === null) {
                throw new DomainRejection('Khóa chưa có bản đã công bố để sao chép.', 'general');
            }

            $draft = CourseVersion::query()->create([
                'course_id' => $course->id,
                'version_no' => (int) $versions->max('version_no') + 1,
                'status' => CourseVersionStatus::Nhap,
                'source_version_id' => $source->id,
                ...$source->only(['summary', 'description', 'objectives', 'seo_title', 'seo_description']),
            ]);
            DB::table('course_version_editors')->insertOrIgnore(['course_version_id' => $draft->id, 'user_id' => $actor->id, 'first_edited_at' => now()]);

            $dropped = [];
            $quizItems = QuizQuestion::query()->whereIn('quiz_id', Quiz::query()->where('course_version_id', $source->id)->select('id'))
                ->whereNull('removed_at')->get()->groupBy('quiz_id');
            $qIds = $quizItems->flatten()->pluck('question_id')->unique()->sort()->values()->all();
            $sourceQuestions = Question::query()->with('bank')->whereIn('id', $qIds)->orderBy('id')->sharedLock()->get()->keyBy('id');

            foreach (CourseSection::query()->where('course_version_id', $source->id)->whereNull('removed_at')->orderBy('position')->get() as $section) {
                $newSection = CourseSection::query()->create(['course_version_id' => $draft->id, 'title' => $section->title, 'position' => $section->position]);
                foreach (Lesson::query()->where('course_section_id', $section->id)->whereNull('removed_at')->orderBy('position')->get() as $lesson) {
                    $copy = $lesson->replicate(['alive_key', 'final_key']);
                    $copy->forceFill(['course_section_id' => $newSection->id, 'course_version_id' => $draft->id])->save();
                    foreach (LessonResource::query()->where('lesson_id', $lesson->id)->whereNull('removed_at')->get() as $res) {
                        $res->replicate()->forceFill(['lesson_id' => $copy->id])->save();
                    }
                    /** @var Quiz|null $quiz */
                    $quiz = Quiz::query()->where('lesson_id', $lesson->id)->first();
                    if ($quiz === null) {
                        continue;
                    }
                    $newQuiz = $quiz->replicate()->forceFill(['lesson_id' => $copy->id, 'course_version_id' => $draft->id]);
                    $newQuiz->save();
                    $pos = 0;
                    foreach ($quizItems->get($quiz->id, collect())->sortBy('position') as $item) {
                        /** @var Question|null $q */
                        $q = $sourceQuestions->get($item->question_id);
                        if ($q === null || $q->status !== QuestionStatus::DangDung || $q->bank->is_confidential) {
                            $dropped[] = ['lesson' => $lesson->title, 'question_id' => $item->question_id, 'stem' => (string) ($item->snapshot['stem'] ?? ($q !== null ? $q->stem : ''))];

                            continue;
                        }
                        QuizQuestion::query()->create(['quiz_id' => $newQuiz->id, 'question_id' => $q->id, 'position' => ++$pos]);
                    }
                }
            }
            Audit::log('VERSION_CREATE_DRAFT', $actor, $draft, null, ['source_version_id' => $source->id, 'version_no' => $draft->version_no]);

            return ['version' => $draft, 'dropped' => $dropped];
        });
    }
}
