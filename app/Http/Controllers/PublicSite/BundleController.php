<?php

namespace App\Http\Controllers\PublicSite;

use App\Actions\M24\Bundles;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M24\Bundle;
use App\Models\User;
use App\Support\M03\CourseCoverUrl;
use App\Support\M12\Commerce;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-KH-xx combo công khai và đặt combo (QT-26 bước 3–5). */
final class BundleController extends Controller
{
    public function index(): Response
    {
        $bundles = Bundle::query()->where('accepts_orders', true)->with('courses.category:id,name')->latest('id')->get();
        Commerce::primePromotions($bundles->flatMap(fn (Bundle $b) => $b->courses));

        return Inertia::render('public/bundles/index', ['bundles' => $bundles->map(fn (Bundle $b) => self::card($b))->values()]);
    }

    public function show(Request $request, string $slug): Response
    {
        $bundle = Bundle::query()->where('slug', $slug)->with('courses.category:id,name')->firstOrFail();
        Commerce::primePromotions($bundle->courses);
        $user = $request->user();
        $owned = $user instanceof User
            ? array_map('intval', Enrollment::query()->where('user_id', $user->id)->whereIn('course_id', $bundle->courses->modelKeys())->pluck('course_id')->all())
            : [];
        $ref = is_string($request->query('ref')) && preg_match('/^[a-z0-9-]{3,32}$/i', $request->query('ref')) === 1 ? $request->query('ref') : null;

        return Inertia::render('public/bundles/show', [
            'bundle' => [...self::card($bundle), 'description' => $bundle->description, 'deduct_owned' => $bundle->deduct_owned,
                'courses' => $bundle->courses->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'slug' => $c->slug,
                    'price' => Commerce::effectivePrice($c)[0], 'owned' => in_array($c->id, $owned, true), 'cover' => CourseCoverUrl::for($c)])->values()],
            'owned_count' => count($owned),
            'ref' => $ref,
            'order_url' => $user instanceof User ? route('student.bundles.orders.store', $bundle) : null,
            'login_url' => $user === null ? route('login', ['redirect' => route('public.bundles.show', array_filter(['slug' => $bundle->slug, 'ref' => $ref]), false)]) : null,
        ]);
    }

    public function order(Request $request, Bundle $bundle, Bundles $bundles): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);
        $ref = $request->input('ref');
        $result = $bundles->place($user, $bundle, is_string($ref) ? mb_substr($ref, 0, 32) : null);
        if ($result['order'] instanceof Order) {
            return to_route('student.orders.show', $result['order']);
        }
        if ($result['owned_all']) {
            return to_route('student.courses.index')->with('status', 'Bạn đã sở hữu mọi khóa của combo này.');
        }
        $codes = implode(', ', array_map(fn (Order $o) => $o->public_code, $result['blocked']));

        return back()->withErrors(['bundle' => 'Bạn đang có đơn mở trùng khóa của combo: '.$codes.'. Hãy thanh toán hoặc hủy đơn đó trước.']);
    }

    /** @return array<string, mixed> */
    private static function card(Bundle $bundle): array
    {
        $retail = (int) $bundle->courses->sum(fn (Course $c) => Commerce::effectivePrice($c)[0]);

        return ['id' => $bundle->id, 'slug' => $bundle->slug, 'title' => $bundle->title, 'price' => $bundle->price, 'retail' => $retail,
            'accepts_orders' => $bundle->accepts_orders, 'course_count' => $bundle->courses->count(),
            'cover' => $bundle->courses->first() === null ? null : CourseCoverUrl::for($bundle->courses->first())];
    }
}
