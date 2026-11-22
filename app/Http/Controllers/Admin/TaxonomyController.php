<?php

namespace App\Http\Controllers\Admin;

use App\Actions\M02\CreateTaxonomy;
use App\Actions\M02\ForceDeleteTaxonomy;
use App\Actions\M02\RenameTaxonomy;
use App\Actions\M02\RestoreTaxonomy;
use App\Actions\M02\SoftDeleteTaxonomy;
use App\Enums\M02\TaxonomyKind;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

/** MH-QT-03 — danh mục nền (QT-02 b1–3, A1–A6, A9; NH-60). */
class TaxonomyController extends Controller
{
    public function index(): Response
    {
        Gate::authorize('manage-taxonomies');

        $categories = Category::query()->withTrashed()->orderBy('parent_key')->orderBy('position')->orderBy('name')->get();
        $roots = $categories->whereNull('parent_id')->values();

        return Inertia::render('admin/taxonomies', [
            'categories' => $roots->map(fn (Category $c) => [
                ...self::row($c),
                'children' => $categories->where('parent_id', $c->id)->values()->map(fn (Category $k) => self::row($k)),
            ]),
            'levels' => Level::query()->withTrashed()->orderBy('position')->orderBy('name')->get()->map(fn (Level $l) => self::row($l)),
            'tags' => Tag::query()->withTrashed()->orderBy('name')->get()->map(fn (Tag $t) => self::row($t)),
        ]);
    }

    public function store(Request $request, string $kind, CreateTaxonomy $action): RedirectResponse
    {
        Gate::authorize('manage-taxonomies');
        $k = self::kind($kind);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:'.($k === TaxonomyKind::Tag ? 60 : 100)],
            'parent_id' => ['nullable', 'integer'],
        ]);
        $action->handle($k, $data['name'], $k === TaxonomyKind::Category && isset($data['parent_id']) ? (int) $data['parent_id'] : null);

        return back()->with('status', 'Đã thêm '.mb_strtolower($k->label()).'.');
    }

    public function update(Request $request, string $kind, int $taxonomy, RenameTaxonomy $action): RedirectResponse
    {
        Gate::authorize('manage-taxonomies');
        $k = self::kind($kind);
        $data = $request->validate(['name' => ['required', 'string', 'max:'.($k === TaxonomyKind::Tag ? 60 : 100)]]);
        $action->handle($k, $taxonomy, $data['name']);

        return back()->with('status', 'Đã đổi tên.');
    }

    public function destroy(string $kind, int $taxonomy, SoftDeleteTaxonomy $action): RedirectResponse
    {
        Gate::authorize('manage-taxonomies');
        $action->handle(self::kind($kind), $taxonomy);

        return back()->with('status', 'Đã xóa. Có thể khôi phục.');
    }

    public function restore(string $kind, int $taxonomy, RestoreTaxonomy $action): RedirectResponse
    {
        Gate::authorize('manage-taxonomies');
        $action->handle(self::kind($kind), $taxonomy);

        return back()->with('status', 'Đã khôi phục.');
    }

    public function forceDestroy(string $kind, int $taxonomy, ForceDeleteTaxonomy $action): RedirectResponse
    {
        Gate::authorize('manage-taxonomies');
        $action->handle(self::kind($kind), $taxonomy);

        return back()->with('status', 'Đã xóa vĩnh viễn.');
    }

    private static function kind(string $kind): TaxonomyKind
    {
        return TaxonomyKind::tryFrom($kind) ?? abort(404);
    }

    /** @return array{id: int, name: string, slug: ?string, deleted: bool} */
    private static function row(Category|Level|Tag $m): array
    {
        return [
            'id' => (int) $m->getKey(),
            'name' => (string) $m->getAttribute('name'),
            'slug' => $m->getAttribute('slug'),
            'deleted' => $m->getAttribute('deleted_at') !== null,
        ];
    }
}
