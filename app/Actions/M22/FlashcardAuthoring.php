<?php

namespace App\Actions\M22;

use App\Models\M03\Course;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardSet;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M03\AuthoringScope;
use App\Support\M03\CourseLocks;
use App\Support\Tx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/** QT-24 bước 1–2, A1/A2: Giáo viên được giao soạn bộ thẻ và thẻ (BR-XP-53/63, CR-90/92/161). */
final class FlashcardAuthoring
{
    public const MAX_CARDS = 500;

    /** @param array{title: string, description: ?string, course_section_id: ?int} $data */
    public function saveSet(User $actor, Course $course, array $data, ?FlashcardSet $set = null): FlashcardSet
    {
        return Tx::run(function () use ($actor, $course, $data, $set) {
            $course = CourseLocks::course($course->id);
            AuthoringScope::ensure($actor, $course);
            if ($data['course_section_id'] !== null && ! DB::table('course_sections')->join('course_versions', 'course_versions.id', '=', 'course_sections.course_version_id')
                ->where('course_sections.id', $data['course_section_id'])->where('course_versions.course_id', $course->id)->exists()) {
                throw new DomainRejection('Chương không thuộc khóa này.', 'course_section_id');
            }
            $fields = $data + ['updated_by_id' => $actor->id];
            if ($set === null) {
                $set = FlashcardSet::query()->create($fields + ['course_id' => $course->id, 'created_by_id' => $actor->id]);
                Audit::log('FLASHCARD_SET_CREATE', $actor, $set, null, $data);
            } else {
                $set = FlashcardSet::query()->whereKey($set->id)->where('course_id', $course->id)->lockForUpdate()->firstOrFail();
                $old = $set->only(array_keys($data));
                $set->fill($fields)->save();
                Audit::log('FLASHCARD_SET_UPDATE', $actor, $set, $old, $data);
            }

            return $set;
        });
    }

    public function archiveSet(User $actor, FlashcardSet $set, bool $archived): FlashcardSet
    {
        return Tx::run(function () use ($actor, $set, $archived) {
            AuthoringScope::ensure($actor, CourseLocks::course($set->course_id));
            $set = FlashcardSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();
            $set->forceFill(['archived_at' => $archived ? now() : null, 'updated_by_id' => $actor->id])->save();
            Audit::log($archived ? 'FLASHCARD_SET_ARCHIVE' : 'FLASHCARD_SET_RESTORE', $actor, $set);

            return $set;
        });
    }

    /**
     * Tạo hoặc sửa thẻ. Sửa mặt sau tăng `back_version` và đặt lại lịch ôn của mọi học viên (A1, BR-XP-63);
     * sửa mặt trước, ảnh, phát âm giữ lịch (A2).
     *
     * @param  array{front: string, back: string, speech_lang: ?string, remove_image?: bool}  $data
     */
    public function saveCard(User $actor, FlashcardSet $set, array $data, ?UploadedFile $image = null, ?Flashcard $card = null): Flashcard
    {
        $stored = $image?->store('flashcards', 'public');
        $newPath = is_string($stored) ? $stored : null;
        $oldPath = null;
        try {
            $card = Tx::run(function () use ($actor, $set, $data, $newPath, $card, &$oldPath) {
                AuthoringScope::ensure($actor, CourseLocks::course($set->course_id));
                $set = FlashcardSet::query()->whereKey($set->id)->lockForUpdate()->firstOrFail();
                $fields = ['front' => $data['front'], 'back' => $data['back'], 'speech_lang' => $data['speech_lang']];
                if ($card === null) {
                    $count = Flashcard::query()->where('flashcard_set_id', $set->id)->whereNull('archived_at')->count();
                    if ($count >= self::MAX_CARDS) {
                        throw new DomainRejection('Mỗi bộ tối đa '.self::MAX_CARDS.' thẻ.', 'front');
                    }
                    $card = Flashcard::query()->create($fields + ['flashcard_set_id' => $set->id, 'image_path' => $newPath,
                        'position' => (int) Flashcard::query()->where('flashcard_set_id', $set->id)->max('position') + 1]);
                    Audit::log('FLASHCARD_CREATE', $actor, $card, null, $fields);

                    return $card;
                }
                $card = Flashcard::query()->whereKey($card->id)->where('flashcard_set_id', $set->id)->lockForUpdate()->firstOrFail();
                $old = $card->only(['front', 'back', 'speech_lang', 'image_path', 'back_version']);
                if ($newPath !== null || ($data['remove_image'] ?? false)) {
                    $oldPath = $card->image_path;
                    $fields['image_path'] = $newPath;
                }
                $backChanged = $card->back !== $data['back'];
                if ($backChanged) {
                    $fields['back_version'] = $card->back_version + 1;
                }
                $card->fill($fields)->save();
                if ($backChanged) {
                    DB::table('flashcard_reviews')->where('flashcard_id', $card->id)->update(['ease' => FlashcardReviews::START_EASE, 'interval_days' => null,
                        'reps' => 0, 'due_on' => null, 'back_version' => $card->back_version, 'updated_at' => now()]);
                }
                Audit::log('FLASHCARD_UPDATE', $actor, $card, $old, $fields);

                return $card;
            });
        } catch (\Throwable $e) {
            if ($newPath !== null) {
                Storage::disk('public')->delete($newPath);
            }
            throw $e;
        }
        if ($oldPath !== null) {
            Storage::disk('public')->delete($oldPath);
        }

        return $card;
    }

    public function archiveCard(User $actor, Flashcard $card): void
    {
        Tx::run(function () use ($actor, $card): void {
            $setCourse = (int) DB::table('flashcard_sets')->where('id', $card->flashcard_set_id)->value('course_id');
            AuthoringScope::ensure($actor, CourseLocks::course($setCourse));
            $card = Flashcard::query()->whereKey($card->id)->lockForUpdate()->firstOrFail();
            $card->forceFill(['archived_at' => now()])->save();
            Audit::log('FLASHCARD_ARCHIVE', $actor, $card);
        });
    }
}
