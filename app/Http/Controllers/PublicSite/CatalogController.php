<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\M03\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\User;
use App\Queries\M06\PublicCatalog;
use App\Queries\M18\ExamBoard;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Inertia\Inertia;
use Inertia\Response;

/** MH-KH-01/02: trang chủ, danh mục, gợi ý tìm kiếm, sitemap (QT-06 b1–5). */
class CatalogController extends Controller
{
    public function home(): Response
    {
        return Inertia::render('public/home', [...PublicCatalog::home(), 'hall_of_fame' => ExamBoard::hallOfFame()]);
    }

    public function index(Request $request): Response
    {
        $sorts = ['moi', 'ban_chay', 'gia_tang', 'gia_giam'];
        $sort = (string) $request->query('sort', 'moi');
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'category' => self::int($request->query('category')),
            'level' => self::int($request->query('level')),
            'teacher' => self::int($request->query('teacher')),
            'price_min' => self::int($request->query('price_min')),
            'price_max' => self::int($request->query('price_max')),
            'sort' => in_array($sort, $sorts, true) ? $sort : 'moi',
        ];
        // UAT 22/11: khoảng giá nhập ngược (từ > đến) thì tự đảo hai đầu và báo cho người dùng.
        $swapped = $filters['price_min'] !== null && $filters['price_max'] !== null && $filters['price_min'] > $filters['price_max'];
        if ($swapped) {
            [$filters['price_min'], $filters['price_max']] = [$filters['price_max'], $filters['price_min']];
        }

        return Inertia::render('public/courses/index', [
            'courses' => PublicCatalog::list($filters),
            'filters' => $filters,
            'priceSwapped' => $swapped,
            'options' => [
                'categories' => Category::query()->orderBy('position')->orderBy('name')->get(['id', 'name', 'parent_id']),
                'levels' => Level::query()->orderBy('position')->orderBy('name')->get(['id', 'name']),
                'teachers' => User::query()->whereHas('courseAuthorships', fn ($a) => $a->whereNotNull('current_key')
                    ->whereHas('course', fn ($c) => $c->where('sale_status', SaleStatus::DangBan->value)))
                    ->orderBy('name')->get(['id', 'name']),
                'sorts' => [
                    ['value' => 'moi', 'label' => 'Mới nhất'],
                    ['value' => 'ban_chay', 'label' => 'Bán chạy'],
                    ['value' => 'gia_tang', 'label' => 'Giá tăng dần'],
                    ['value' => 'gia_giam', 'label' => 'Giá giảm dần'],
                ],
            ],
        ]);
    }

    public function suggestions(Request $request): JsonResponse
    {
        return response()->json(['term' => (string) $request->query('q', ''), 'items' => PublicCatalog::suggest((string) $request->query('q', ''))]);
    }

    /** BR-ND-78: chỉ khóa `DANG_BAN`. */
    public function sitemap(): HttpResponse
    {
        $urls = [route('home'), route('public.courses.index')];
        foreach (PublicCatalog::selling()->orderBy('id')->get(['slug', 'updated_at']) as $c) {
            $urls[] = route('public.courses.show', $c->slug);
        }
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as $u) {
            $xml .= '<url><loc>'.e($u).'</loc></url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    private static function int(mixed $v): ?int
    {
        return is_numeric($v) && (int) $v >= 0 ? (int) $v : null;
    }
}
