<?php

namespace App\Actions\M05;

use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuizKind;
use App\Enums\M05\LessonType;
use App\Jobs\M05\ReadVideoDuration;
use App\Models\M04\Question;
use App\Models\M04\Quiz;
use App\Models\M04\QuizQuestion;
use App\Models\M05\CourseSection;
use App\Models\M05\Lesson;
use App\Models\M05\LessonResource;
use App\Models\User;
use App\Support\DomainRejection;
use App\Support\M05\DraftGuard;
use App\Support\M05\HtmlSanitizer;
use App\Support\Tx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * EditDraftContent (03 §3, 03 §14): mọi điểm sửa bản nháp đi qua DraftGuard
 * (khóa courses → course_versions, người soạn hiện tại, `NHAP`, ghi người sửa).
 */
final class EditDraftContent
{
    public function addSection(User $actor, int $versionId, string $title): CourseSection
    {
        return Tx::run(function () use ($actor, $versionId, $title) {
            $version = DraftGuard::open($actor, $versionId);
            $pos = (int) CourseSection::query()->where('course_version_id', $version->id)->whereNull('removed_at')->max('position');

            return CourseSection::query()->create(['course_version_id' => $version->id, 'title' => trim($title), 'position' => $pos + 1]);
        });
    }

    public function renameSection(User $actor, int $sectionId, string $title): CourseSection
    {
        return Tx::run(function () use ($actor, $sectionId, $title) {
            $section = $this->aliveSection($actor, $sectionId);
            $section->forceFill(['title' => trim($title)])->save();

            return $section;
        });
    }

    public function addLesson(User $actor, int $sectionId, string $title, LessonType $type): Lesson
    {
        return Tx::run(function () use ($actor, $sectionId, $title, $type) {
            $section = $this->aliveSection($actor, $sectionId);
            $pos = (int) Lesson::query()->where('course_section_id', $section->id)->whereNull('removed_at')->max('position');
            $lesson = Lesson::query()->create([
                'course_section_id' => $section->id,
                'course_version_id' => $section->course_version_id,
                'lesson_key' => $this->newLessonKey((int) $section->version->course_id),
                'title' => trim($title),
                'type' => $type,
                'position' => $pos + 1,
            ]);
            if ($type === LessonType::Quiz) {
                Quiz::query()->create(['course_version_id' => $section->course_version_id, 'lesson_id' => $lesson->id, 'kind' => QuizKind::BaiHoc]);
            }

            return $lesson;
        });
    }

    /**
     * @param  array{title?: string, objectives?: string|null, content?: string|null, is_required?: bool, is_preview?: bool, is_final_test?: bool}  $data
     */
    public function saveLesson(User $actor, int $lessonId, array $data): Lesson
    {
        return Tx::run(function () use ($actor, $lessonId, $data) {
            $lesson = $this->aliveLesson($actor, $lessonId);
            $required = $data['is_required'] ?? $lesson->is_required;
            $final = $data['is_final_test'] ?? $lesson->is_final_test;
            if ($lesson->is_final_test && ! $required && ($data['is_final_test'] ?? true)) {
                // A13: bỏ cờ bắt buộc khi còn cờ bài kiểm tra cuối.
                throw new DomainRejection('Hãy bỏ cờ bài kiểm tra cuối trước khi bỏ cờ bắt buộc.', 'is_required');
            }
            if ($final && ! $lesson->is_final_test) {
                if ($lesson->type !== LessonType::Quiz || ! $required) {
                    throw new DomainRejection('Chỉ Quiz bắt buộc mới đặt được cờ bài kiểm tra cuối.', 'is_final_test');
                }
                $other = Lesson::query()->where('course_version_id', $lesson->course_version_id)
                    ->whereNull('removed_at')->where('is_final_test', true)->whereKeyNot($lesson->id)->exists();
                if ($other) {
                    throw new DomainRejection('Mỗi phiên bản chỉ có một bài kiểm tra cuối.', 'is_final_test');
                }
            }
            $fill = [];
            foreach (['title', 'objectives'] as $k) {
                if (array_key_exists($k, $data)) {
                    $fill[$k] = $data[$k] === null ? null : trim((string) $data[$k]);
                }
            }
            if (array_key_exists('content', $data)) {
                $fill['content'] = HtmlSanitizer::clean($data['content']);
            }
            $fill['is_required'] = (bool) $required;
            $fill['is_preview'] = (bool) ($data['is_preview'] ?? $lesson->is_preview);
            $fill['is_final_test'] = (bool) $final;
            $lesson->forceFill($fill)->save();

            return $lesson;
        });
    }

    /** A4: lên/xuống trong cùng cha; đầu không lên, cuối không xuống (không lỗi). */
    public function move(User $actor, string $kind, int $id, string $direction): void
    {
        Tx::run(function () use ($actor, $kind, $id, $direction) {
            if ($kind === 'section') {
                $item = $this->aliveSection($actor, $id);
                $siblings = CourseSection::query()->where('course_version_id', $item->course_version_id)->whereNull('removed_at');
            } else {
                $item = $this->aliveLesson($actor, $id);
                $siblings = Lesson::query()->where('course_section_id', $item->course_section_id)->whereNull('removed_at');
            }
            $ordered = $siblings->orderBy('position')->orderBy('id')->get()->values();
            $index = $ordered->search(fn ($m) => $m->id === $item->id);
            $target = $direction === 'up' ? $index - 1 : $index + 1;
            if ($index === false || $target < 0 || $target >= $ordered->count()) {
                return;
            }
            $list = $ordered->all();
            [$list[$index], $list[$target]] = [$list[$target], $list[$index]];
            foreach (array_values($list) as $pos => $model) {
                if ($model->position !== $pos + 1) {
                    $model->forceFill(['position' => $pos + 1])->save();
                }
            }
        });
    }

    /** A6: gỡ khỏi bản nháp (đặt removed_at cho cả cây con), không xóa dòng (02 §0). */
    public function remove(User $actor, string $kind, int $id): void
    {
        Tx::run(function () use ($actor, $kind, $id) {
            $now = now();
            if ($kind === 'resource') {
                $resource = LessonResource::query()->whereKey($id)->whereNull('removed_at')->firstOrFail();
                $this->aliveLesson($actor, $resource->lesson_id);
                $resource->forceFill(['removed_at' => $now])->save();

                return;
            }
            if ($kind === 'section') {
                $section = $this->aliveSection($actor, $id);
                $lessonIds = Lesson::query()->where('course_section_id', $section->id)->whereNull('removed_at')->pluck('id')->all();
                $section->forceFill(['removed_at' => $now])->save();
            } else {
                $lesson = $this->aliveLesson($actor, $id);
                $lessonIds = [$lesson->id];
            }
            Lesson::query()->whereIn('id', $lessonIds)->update(['removed_at' => $now, 'is_final_test' => false]);
            LessonResource::query()->whereIn('lesson_id', $lessonIds)->whereNull('removed_at')->update(['removed_at' => $now]);
            QuizQuestion::query()->whereIn('quiz_id', Quiz::query()->whereIn('lesson_id', $lessonIds)->select('id'))
                ->whereNull('removed_at')->update(['removed_at' => $now]);
        });
    }

    /** Tải MP4: đường dẫn bất biến mỗi lần tải; xóa thời lượng cùng bước; chèn Job TV-07 trên cùng kết nối. */
    public function uploadVideo(User $actor, int $lessonId, UploadedFile $file): Lesson
    {
        $path = $file->storeAs('lessons/video', Str::ulid().'.mp4', 'local');

        return Tx::run(function () use ($actor, $lessonId, $path) {
            $lesson = $this->aliveLesson($actor, $lessonId);
            if ($lesson->type !== LessonType::Video) {
                throw new DomainRejection('Bài này không phải bài Video.', 'video');
            }
            $lesson->forceFill(['video_path' => $path, 'video_duration_ms' => null])->save();
            ReadVideoDuration::dispatch($lesson->id, (string) $path);

            return $lesson;
        });
    }

    public function uploadPdf(User $actor, int $lessonId, UploadedFile $file): Lesson
    {
        $path = $file->storeAs('lessons/pdf', Str::ulid().'.pdf', 'local');

        return Tx::run(function () use ($actor, $lessonId, $path) {
            $lesson = $this->aliveLesson($actor, $lessonId);
            if ($lesson->type !== LessonType::Pdf) {
                throw new DomainRejection('Bài này không phải bài PDF.', 'pdf');
            }
            $lesson->forceFill(['pdf_path' => $path])->save();

            return $lesson;
        });
    }

    public function addResource(User $actor, int $lessonId, UploadedFile $file, ?string $title = null): LessonResource
    {
        $path = $file->storeAs('lessons/resources', Str::ulid().'.'.$file->getClientOriginalExtension(), 'local');

        return Tx::run(function () use ($actor, $lessonId, $file, $path, $title) {
            $lesson = $this->aliveLesson($actor, $lessonId);
            $pos = (int) LessonResource::query()->where('lesson_id', $lesson->id)->whereNull('removed_at')->max('position');

            return LessonResource::query()->create([
                'lesson_id' => $lesson->id,
                'title' => trim($title ?? '') !== '' ? trim((string) $title) : $file->getClientOriginalName(),
                'file_path' => $path,
                'original_name' => $file->getClientOriginalName(),
                'mime_type' => (string) $file->getMimeType(),
                'size_bytes' => (int) $file->getSize(),
                'position' => $pos + 1,
            ]);
        });
    }

    /** SaveLessonQuizConfiguration: bản nháp lưu được cấu hình chưa đủ (02 L23); miền kiểm ở FormRequest (CR-19). */
    public function saveQuiz(User $actor, int $lessonId, ?int $passPercent, ?int $durationMinutes, ?int $maxAttempts): Quiz
    {
        return Tx::run(function () use ($actor, $lessonId, $passPercent, $durationMinutes, $maxAttempts) {
            $quiz = $this->quizOf($this->aliveLesson($actor, $lessonId));
            $quiz->forceFill(['pass_percent' => $passPercent, 'duration_minutes' => $durationMinutes, 'max_attempts' => $maxAttempts])->save();

            return $quiz;
        });
    }

    /**
     * Đặt tập câu của quiz (BR-ND-59, BR-ND-85): chỉ câu `DANG_DUNG` của ngân hàng không bảo mật cùng khóa.
     * Khóa câu nguồn `FOR SHARE` theo id sau course_versions (05 §1).
     *
     * @param  list<int>  $questionIds
     */
    public function setQuizQuestions(User $actor, int $lessonId, array $questionIds): Quiz
    {
        return Tx::run(function () use ($actor, $lessonId, $questionIds) {
            $lesson = $this->aliveLesson($actor, $lessonId);
            $quiz = $this->quizOf($lesson);
            $courseId = (int) $lesson->version->course_id;
            $wanted = array_values(array_unique(array_map('intval', $questionIds)));
            $current = QuizQuestion::query()->where('quiz_id', $quiz->id)->whereNull('removed_at')->get()->keyBy('question_id');
            $added = array_values(array_diff($wanted, $current->keys()->map(fn ($k) => (int) $k)->all()));
            sort($added);
            if ($added !== []) {
                $rows = Question::query()->with('bank')->whereIn('id', $added)->orderBy('id')->sharedLock()->get()->keyBy('id');
                foreach ($added as $qid) {
                    /** @var Question|null $q */
                    $q = $rows->get($qid);
                    if ($q === null || $q->bank->course_id !== $courseId) {
                        throw new DomainRejection('Chỉ chọn được câu trong ngân hàng của khóa này.', 'question_ids');
                    }
                    if ($q->bank->is_confidential) {
                        throw new DomainRejection('Không chọn câu của ngân hàng bảo mật.', 'question_ids');
                    }
                    if ($q->status !== QuestionStatus::DangDung) {
                        throw new DomainRejection('Câu đã ngừng dùng không chọn được.', 'question_ids');
                    }
                }
            }
            $now = now();
            foreach ($current as $qid => $item) {
                if (! in_array((int) $qid, $wanted, true)) {
                    $item->forceFill(['removed_at' => $now])->save();
                }
            }
            foreach ($wanted as $pos => $qid) {
                $item = $current->get($qid);
                if ($item === null) {
                    QuizQuestion::query()->create(['quiz_id' => $quiz->id, 'question_id' => $qid, 'position' => $pos + 1]);
                } elseif ($item->position !== $pos + 1) {
                    $item->forceFill(['position' => $pos + 1])->save();
                }
            }

            return $quiz;
        });
    }

    private function aliveSection(User $actor, int $sectionId): CourseSection
    {
        $versionId = CourseSection::query()->whereKey($sectionId)->value('course_version_id');
        if (! is_numeric($versionId)) {
            throw new DomainRejection('Không tìm thấy chương.', 'general', 404);
        }
        $version = DraftGuard::open($actor, (int) $versionId);
        $section = CourseSection::query()->whereKey($sectionId)->whereNull('removed_at')->first();
        if ($section === null) {
            throw new DomainRejection('Chương đã bị gỡ khỏi bản nháp.', 'general', 404);
        }
        $section->setRelation('version', $version);

        return $section;
    }

    private function aliveLesson(User $actor, int $lessonId): Lesson
    {
        $versionId = Lesson::query()->whereKey($lessonId)->value('course_version_id');
        if (! is_numeric($versionId)) {
            throw new DomainRejection('Không tìm thấy bài.', 'general', 404);
        }
        $version = DraftGuard::open($actor, (int) $versionId);
        $lesson = Lesson::query()->whereKey($lessonId)->whereNull('removed_at')->lockForUpdate()->first();
        if ($lesson === null) {
            throw new DomainRejection('Bài đã bị gỡ khỏi bản nháp.', 'general', 404);
        }
        $lesson->setRelation('version', $version);

        return $lesson;
    }

    private function quizOf(Lesson $lesson): Quiz
    {
        if ($lesson->type !== LessonType::Quiz) {
            throw new DomainRejection('Bài này không phải bài Quiz.', 'general');
        }

        return Quiz::query()->firstOrCreate(
            ['lesson_id' => $lesson->id],
            ['course_version_id' => $lesson->course_version_id, 'kind' => QuizKind::BaiHoc],
        );
    }

    /** 02 L15: khóa bài ULID, kiểm toàn lịch sử của khóa dưới khóa courses; trùng thì sinh lại (ADR-004). */
    private function newLessonKey(int $courseId): string
    {
        for ($i = 0; $i < 3; $i++) {
            $key = (string) Str::ulid();
            $taken = DB::table('lessons')->join('course_versions', 'course_versions.id', '=', 'lessons.course_version_id')
                ->where('course_versions.course_id', $courseId)->where('lessons.lesson_key', $key)->exists();
            if (! $taken) {
                return $key;
            }
        }
        throw new DomainRejection('Không cấp được khóa bài, hãy thử lại.', 'general');
    }
}
