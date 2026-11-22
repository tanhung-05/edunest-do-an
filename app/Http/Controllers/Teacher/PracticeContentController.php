<?php

namespace App\Http\Controllers\Teacher;

use App\Actions\M20\ActivityAuthoring;
use App\Actions\M22\FlashcardAuthoring;
use App\Enums\M20\DrawMode;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M04\QuestionBank;
use App\Models\M20\PracticeActivity;
use App\Models\M22\Flashcard;
use App\Models\M22\FlashcardSet;
use App\Models\User;
use App\Support\M03\AuthoringScope;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GV-15/16: hoạt động luyện tập và bộ thẻ ghi nhớ của khóa được giao (QT-22 A1, QT-24 bước 1–2). */
final class PracticeContentController extends Controller
{
    public function activities(Request $request): Response
    {
        $user = $this->teacher($request);
        $courses = $this->courses($user);
        $activities = PracticeActivity::query()->whereIn('course_id', $courses->keys())->with('banks:id,name')->orderByDesc('id')->get();

        return Inertia::render('teacher/activities/index', [
            'activities' => $activities->map(fn (PracticeActivity $a) => [
                'id' => $a->id, 'title' => $a->title, 'course' => $courses[$a->course_id] ?? '', 'question_count' => $a->question_count,
                'draw_mode' => $a->draw_mode->label(), 'seconds_per_question' => $a->seconds_per_question, 'archived' => $a->archived_at !== null,
                'banks' => $a->banks->pluck('name')->all(), 'edit_url' => route('teacher.activities.edit', $a), 'archive_url' => route('teacher.activities.archive', $a),
                'sessions' => DB::table('activity_sessions')->where('activity_id', $a->id)->count(),
            ])->values(),
            'create_url' => route('teacher.activities.create'),
        ]);
    }

    public function createActivity(Request $request): Response
    {
        return $this->activityForm($this->teacher($request), null, $request->integer('course') ?: null);
    }

    public function editActivity(Request $request, PracticeActivity $activity): Response
    {
        $user = $this->teacher($request);
        abort_unless(AuthoringScope::isCurrentAuthor($user, $activity->course_id), 403);

        return $this->activityForm($user, $activity, $activity->course_id);
    }

    public function storeActivity(Request $request, ActivityAuthoring $authoring): RedirectResponse
    {
        $data = $this->activityData($request);
        $authoring->save($this->teacher($request), Course::query()->whereKey($data['course_id'])->firstOrFail(), $data);

        return to_route('teacher.activities.index')->with('status', 'Đã tạo hoạt động luyện tập.');
    }

    public function updateActivity(Request $request, PracticeActivity $activity, ActivityAuthoring $authoring): RedirectResponse
    {
        $data = $this->activityData($request);
        $authoring->save($this->teacher($request), Course::query()->findOrFail($activity->course_id), $data, $activity);

        return to_route('teacher.activities.index')->with('status', 'Đã lưu hoạt động.');
    }

    public function archiveActivity(Request $request, PracticeActivity $activity, ActivityAuthoring $authoring): RedirectResponse
    {
        $archived = $request->boolean('archived', true);
        $authoring->archive($this->teacher($request), $activity, $archived);

        return back()->with('status', $archived ? 'Đã ngừng hoạt động.' : 'Đã mở lại hoạt động.');
    }

    public function sets(Request $request): Response
    {
        $user = $this->teacher($request);
        $courses = $this->courses($user);

        return Inertia::render('teacher/flashcards/index', [
            'sets' => FlashcardSet::query()->whereIn('course_id', $courses->keys())->orderByDesc('id')->get()->map(fn (FlashcardSet $s) => [
                'id' => $s->id, 'title' => $s->title, 'course' => $courses[$s->course_id] ?? '', 'archived' => $s->archived_at !== null,
                'cards' => Flashcard::query()->where('flashcard_set_id', $s->id)->whereNull('archived_at')->count(),
                'url' => route('teacher.flashcard-sets.show', $s), 'archive_url' => route('teacher.flashcard-sets.archive', $s),
            ])->values(),
            'courses' => $this->courseOptions($courses),
            'store_url' => route('teacher.flashcard-sets.store'),
        ]);
    }

    public function storeSet(Request $request, FlashcardAuthoring $authoring): RedirectResponse
    {
        $data = $request->validate(['course_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:1000'], 'course_section_id' => ['nullable', 'integer']]);
        $set = $authoring->saveSet($this->teacher($request), Course::query()->whereKey($data['course_id'])->firstOrFail(),
            ['title' => $data['title'], 'description' => $data['description'] ?? null, 'course_section_id' => $data['course_section_id'] ?? null]);

        return to_route('teacher.flashcard-sets.show', $set)->with('status', 'Đã tạo bộ thẻ.');
    }

    public function showSet(Request $request, FlashcardSet $set): Response
    {
        $user = $this->teacher($request);
        abort_unless(AuthoringScope::isCurrentAuthor($user, $set->course_id), 403);

        return Inertia::render('teacher/flashcards/show', [
            'set' => ['id' => $set->id, 'title' => $set->title, 'description' => $set->description, 'course_section_id' => $set->course_section_id,
                'course' => (string) Course::query()->whereKey($set->course_id)->value('title'), 'archived' => $set->archived_at !== null,
                'update_url' => route('teacher.flashcard-sets.update', $set), 'card_store_url' => route('teacher.flashcard-sets.cards.store', $set),
                'archive_url' => route('teacher.flashcard-sets.archive', $set)],
            'sections' => $this->sections($set->course_id),
            'cards' => Flashcard::query()->where('flashcard_set_id', $set->id)->whereNull('archived_at')->orderBy('position')->orderBy('id')->get()
                ->map(fn (Flashcard $c) => ['id' => $c->id, 'front' => $c->front, 'back' => $c->back, 'speech_lang' => $c->speech_lang,
                    'image_url' => $c->image_path === null ? null : Storage::disk('public')->url($c->image_path), 'back_version' => $c->back_version,
                    'update_url' => route('teacher.flashcards.update', $c), 'destroy_url' => route('teacher.flashcards.destroy', $c)])->values(),
            'max_cards' => FlashcardAuthoring::MAX_CARDS,
        ]);
    }

    public function updateSet(Request $request, FlashcardSet $set, FlashcardAuthoring $authoring): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:1000'],
            'course_section_id' => ['nullable', 'integer']]);
        $authoring->saveSet($this->teacher($request), Course::query()->findOrFail($set->course_id),
            ['title' => $data['title'], 'description' => $data['description'] ?? null, 'course_section_id' => $data['course_section_id'] ?? null], $set);

        return back()->with('status', 'Đã lưu bộ thẻ.');
    }

    public function archiveSet(Request $request, FlashcardSet $set, FlashcardAuthoring $authoring): RedirectResponse
    {
        $archived = $request->boolean('archived', true);
        $authoring->archiveSet($this->teacher($request), $set, $archived);

        return back()->with('status', $archived ? 'Đã lưu trữ bộ thẻ.' : 'Đã mở lại bộ thẻ.');
    }

    public function storeCard(Request $request, FlashcardSet $set, FlashcardAuthoring $authoring): RedirectResponse
    {
        $data = $this->cardData($request);
        $authoring->saveCard($this->teacher($request), $set, $data, $request->file('image'));

        return back()->with('status', 'Đã thêm thẻ.');
    }

    public function updateCard(Request $request, Flashcard $card, FlashcardAuthoring $authoring): RedirectResponse
    {
        $data = $this->cardData($request);
        $set = FlashcardSet::query()->findOrFail($card->flashcard_set_id);
        $backChanged = $card->back !== $data['back'];
        $authoring->saveCard($this->teacher($request), $set, $data, $request->file('image'), $card);

        return back()->with('status', $backChanged ? 'Đã lưu thẻ. Mặt sau đổi nên lịch ôn của học viên được đặt lại.' : 'Đã lưu thẻ.');
    }

    public function destroyCard(Request $request, Flashcard $card, FlashcardAuthoring $authoring): RedirectResponse
    {
        $authoring->archiveCard($this->teacher($request), $card);

        return back()->with('status', 'Đã gỡ thẻ.');
    }

    private function activityForm(User $user, ?PracticeActivity $activity, ?int $courseId): Response
    {
        $courses = $this->courses($user);
        $banks = QuestionBank::query()->whereIn('course_id', $courses->keys())->where('is_confidential', false)->orderBy('name')
            ->withCount(['questions' => fn ($q) => $q->where('status', 'DANG_DUNG')])->get();

        return Inertia::render('teacher/activities/form', [
            'activity' => $activity === null ? null : ['id' => $activity->id, 'course_id' => $activity->course_id, 'title' => $activity->title,
                'question_count' => $activity->question_count, 'draw_mode' => $activity->draw_mode->value, 'seconds_per_question' => $activity->seconds_per_question,
                'bank_ids' => $activity->banks()->pluck('question_banks.id')->all(), 'update_url' => route('teacher.activities.update', $activity)],
            'default_course_id' => $courseId,
            'courses' => $this->courseOptions($courses),
            'banks' => $banks->map(fn (QuestionBank $b) => ['id' => $b->id, 'name' => $b->name, 'course_id' => $b->course_id, 'questions' => $b->questions_count])->values(),
            'draw_modes' => collect(DrawMode::cases())->map(fn (DrawMode $m) => ['value' => $m->value, 'label' => $m->label()])->values(),
            'store_url' => route('teacher.activities.store'),
        ]);
    }

    /** @return array{course_id: int, title: string, question_count: int, draw_mode: string, seconds_per_question: int, bank_ids: list<int>} */
    private function activityData(Request $request): array
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer'], 'title' => ['required', 'string', 'max:200'], 'question_count' => ['required', 'integer', 'min:1', 'max:50'],
            'draw_mode' => ['required', Rule::enum(DrawMode::class)], 'seconds_per_question' => ['required', 'integer', 'min:10', 'max:300'],
            'bank_ids' => ['required', 'array', 'min:1', 'max:20'], 'bank_ids.*' => ['integer', 'distinct'],
        ]);

        return ['course_id' => (int) $data['course_id'], 'title' => $data['title'], 'question_count' => (int) $data['question_count'], 'draw_mode' => $data['draw_mode'],
            'seconds_per_question' => (int) $data['seconds_per_question'], 'bank_ids' => array_values(array_map('intval', $data['bank_ids']))];
    }

    /** @return array{front: string, back: string, speech_lang: ?string, remove_image: bool} */
    private function cardData(Request $request): array
    {
        $data = $request->validate(['front' => ['required', 'string', 'max:500'], 'back' => ['required', 'string', 'max:1000'],
            'speech_lang' => ['nullable', Rule::in(['vi-VN', 'en-US', 'en-GB', 'fr-FR', 'ja-JP', 'zh-CN', 'ko-KR'])],
            'image' => ['nullable', 'image', 'max:2048'], 'remove_image' => ['boolean']]);

        return ['front' => $data['front'], 'back' => $data['back'], 'speech_lang' => $data['speech_lang'] ?? null, 'remove_image' => (bool) ($data['remove_image'] ?? false)];
    }

    /** @return Collection<int, string> */
    private function courses(User $user): Collection
    {
        $ids = CourseAuthor::query()->where('user_id', $user->id)->whereNotNull('current_key')->pluck('course_id');

        return Course::query()->whereIn('id', $ids)->orderBy('title')->pluck('title', 'id');
    }

    /**
     * @param  Collection<int, string>  $courses
     * @return list<array{id: int, title: string}>
     */
    private function courseOptions(Collection $courses): array
    {
        return array_values($courses->map(fn (string $title, int $id) => ['id' => $id, 'title' => $title])->all());
    }

    /** @return list<array{id: int, title: string}> */
    private function sections(int $courseId): array
    {
        $version = Course::query()->whereKey($courseId)->value('current_version_id');

        return array_values(DB::table('course_sections')->where('course_version_id', $version)->whereNull('removed_at')->orderBy('position')->get(['id', 'title'])
            ->map(fn ($s) => ['id' => (int) $s->id, 'title' => (string) $s->title])->all());
    }

    private function teacher(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
