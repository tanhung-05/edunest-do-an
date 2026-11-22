<?php

namespace App\Queries\M06;

use App\Enums\M03\SaleStatus;
use App\Models\M02\Category;
use App\Models\M03\Course;
use App\Models\M08\Enrollment;
use App\Models\User;
use App\Support\M03\CourseCoverUrl;
use App\Support\M12\Commerce;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * QT-06: truy vấn công khai chỉ đọc khóa `DANG_BAN` có bản công bố (BR-ND-72/73/75, E7).
 * Bán chạy đếm ghi danh phát sinh trong 30 ngày (CR-204).
 */
final class PublicCatalog
{
    public const PER_PAGE = 12;

    public const SUGGEST_LIMIT = 8;

    /** @return Builder<Course> */
    public static function selling(): Builder
    {
        return Course::query()->where('sale_status', SaleStatus::DangBan->value)->whereNotNull('current_version_id');
    }

    /**
     * @param  array{q?: string, category?: int|null, level?: int|null, teacher?: int|null, price_min?: int|null, price_max?: int|null, sort?: string}  $f
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public static function list(array $f): LengthAwarePaginator
    {
        $q = self::selling()->with(['currentAuthor.user:id,name', 'category:id,name', 'level:id,name']);
        if (($f['q'] ?? '') !== '') {
            self::matchText($q, (string) $f['q']);
        }
        if (($f['category'] ?? null) !== null) {
            // Danh mục cấp 1 gồm cả danh mục con (AC-06-37).
            $ids = Category::query()->withTrashed()->where('parent_id', $f['category'])->pluck('id')->push($f['category'])->all();
            $q->whereIn('category_id', $ids);
        }
        if (($f['level'] ?? null) !== null) {
            $q->where('level_id', $f['level']);
        }
        if (($f['teacher'] ?? null) !== null) {
            $q->whereHas('currentAuthor', fn ($a) => $a->where('user_id', $f['teacher']));
        }
        if (($f['price_min'] ?? null) !== null) {
            $q->where('price', '>=', $f['price_min']);
        }
        if (($f['price_max'] ?? null) !== null) {
            $q->where('price', '<=', $f['price_max']);
        }
        match ($f['sort'] ?? 'moi') {
            'ban_chay' => $q->orderByDesc(Enrollment::query()->selectRaw('COUNT(*)')->whereColumn('course_id', 'courses.id')
                ->whereBetween('granted_at', [now()->subDays(30), now()]))
                ->orderByDesc('last_published_at')->orderByDesc('id'),
            'gia_tang' => $q->orderBy('price')->orderBy('id'),
            'gia_giam' => $q->orderByDesc('price')->orderBy('id'),
            default => $q->orderByDesc('last_published_at')->orderByDesc('id'),
        };

        $page = $q->paginate(self::PER_PAGE)->withQueryString();
        Commerce::primePromotions($page->items());

        return $page->through(fn (Course $c) => self::card($c));
    }

    /**
     * Gợi ý từ 2 ký tự, tối đa 8, khớp tên khóa hoặc tên giáo viên đang soạn (BR-ND-75).
     *
     * @return list<array{id: int, title: string, slug: string, teacher: string|null}>
     */
    public static function suggest(string $term): array
    {
        $term = trim($term);
        if (mb_strlen($term) < 2) {
            return [];
        }
        $q = self::selling()->with('currentAuthor.user:id,name');
        self::matchText($q, $term);

        return array_values($q->orderBy('title')->orderBy('id')->limit(self::SUGGEST_LIMIT)->get()
            ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug, 'teacher' => $c->currentAuthor?->user?->name])
            ->all());
    }

    /**
     * @param  Builder<Course>  $q
     */
    private static function matchText(Builder $q, string $term): void
    {
        $like = '%'.addcslashes($term, '%_\\').'%';
        $q->where(fn ($w) => $w->where('title', 'like', $like)
            ->orWhereHas('currentAuthor.user', fn ($u) => $u->where('name', 'like', $like)));
    }

    /** @return array<string, mixed> */
    public static function card(Course $c): array
    {
        [$effective, $promotion] = Commerce::effectivePrice($c);

        return [
            'id' => $c->id,
            'title' => $c->title,
            'slug' => $c->slug,
            'cover' => CourseCoverUrl::for($c, $c->category?->name),
            // BR-TT-62 (P5): thẻ khóa hiện giá sau khuyến mãi, giá niêm yết gạch ngang khi đang có đợt.
            'price' => $effective,
            'original_price' => $promotion !== null ? $c->price
                : ($c->original_price !== null && $c->original_price > $c->price ? $c->original_price : null),
            'teacher' => $c->currentAuthor?->user?->name,
            'teacher_id' => $c->currentAuthor?->user_id,
            'category' => $c->category?->name,
            'level' => $c->level?->name,
        ];
    }

    /**
     * @param  EloquentCollection<int, Course>  $courses
     * @return Collection<int, array<string, mixed>>
     */
    private static function cards(EloquentCollection $courses): Collection
    {
        Commerce::primePromotions($courses);

        return $courses->map(fn (Course $c) => self::card($c))->values();
    }

    /**
     * P10 (CR-257): thẻ khóa đang bán theo đúng thứ tự id cho trước (hộp chat tư vấn), kèm đường dẫn trang khóa.
     *
     * @param  list<int>  $ids
     * @return list<array<string, mixed>>
     */
    public static function cardsFor(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $courses = self::selling()->with(['currentAuthor.user:id,name', 'category:id,name', 'level:id,name'])
            ->whereIn('courses.id', $ids)->get();
        $order = array_flip($ids);

        return array_values(self::cards($courses)
            ->map(fn (array $card) => [...$card, 'url' => route('public.courses.show', $card['slug'])])
            ->sortBy(fn (array $card) => $order[$card['id']] ?? PHP_INT_MAX)->all());
    }

    /**
     * P10: gợi ý khóa cho Học viên trên trang bắt đầu. Bỏ khóa đã ghi danh; ưu tiên cùng môn với khóa đang học,
     * rồi khóa nhiều người học, rồi khóa mới công bố.
     *
     * @return list<array<string, mixed>>
     */
    public static function recommendFor(User $user, int $limit = 4): array
    {
        $owned = Enrollment::query()->where('user_id', $user->id)->pluck('course_id')->all();
        $categories = Course::query()->whereIn('id', $owned)->whereNotNull('category_id')->distinct()->pluck('category_id')->all();
        $base = fn () => self::selling()->with(['currentAuthor.user:id,name', 'category:id,name', 'level:id,name'])
            ->whereNotIn('courses.id', $owned)
            ->orderByDesc(Enrollment::query()->selectRaw('COUNT(*)')->whereColumn('course_id', 'courses.id'))
            ->orderByDesc('last_published_at')->orderByDesc('id');
        $picked = $categories === [] ? new EloquentCollection : $base()->whereIn('category_id', $categories)->limit($limit)->get();
        if ($picked->count() < $limit) {
            $picked = $picked->concat($base()->whereNotIn('courses.id', $picked->modelKeys())->limit($limit - $picked->count())->get());
        }

        return array_values(self::cards(new EloquentCollection($picked->all()))->all());
    }

    /** @return array<string, mixed> */
    public static function home(): array
    {
        $with = ['currentAuthor.user:id,name', 'category:id,name', 'level:id,name'];

        return [
            'featured' => self::cards(self::selling()->with($with)->orderByDesc('last_published_at')->orderByDesc('id')->limit(6)->get()),
            'newest' => self::cards(self::selling()->with($with)->orderByDesc('created_at')->orderByDesc('id')->limit(6)->get()),
            'teachers' => User::query()->whereHas('teacherProfile')->with('teacherProfile')->orderBy('name')->limit(8)->get()
                ->map(fn (User $u) => [
                    'id' => $u->id,
                    'name' => $u->name,
                    'photo' => $u->teacherProfile?->photo_path !== null ? Storage::disk('public')->url($u->teacherProfile->photo_path) : null,
                ])->values(),
            'subjects' => self::subjects(),
            'stats' => [
                'courses' => self::selling()->count(),
                'students' => PublicStats::students(),
            ],
        ];
    }

    /**
     * Môn (danh mục cấp 2) đang có khóa bán, kèm số khóa và ảnh minh họa theo môn (trang chủ v5).
     *
     * @return list<array{id: int, name: string, count: int, photo: string}>
     */
    private static function subjects(): array
    {
        $counts = self::selling()->selectRaw('category_id, COUNT(*) as total')->groupBy('category_id')->pluck('total', 'category_id');

        return array_values(Category::query()->whereNotNull('parent_id')->whereIn('id', $counts->keys())->orderBy('position')->orderBy('id')->get(['id', 'name'])
            ->map(fn (Category $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'count' => (int) $counts[$c->id],
                'photo' => CourseCoverUrl::forSubject($c->name, $c->id),
            ])
            ->sortByDesc('count')->take(6)->values()->all());
    }
}
