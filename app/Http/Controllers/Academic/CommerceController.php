<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M07\StopGrantAndRefund;
use App\Actions\M08\RevokeEnrollment;
use App\Actions\M12\SaveCoupon;
use App\Actions\M12\SetCoursePromotion;
use App\Actions\M13\ActivationCodes;
use App\Actions\M14\CourseRatings;
use App\Enums\M03\SaleStatus;
use App\Enums\M12\CouponScope;
use App\Enums\M12\DiscountType;
use App\Enums\M14\RatingStatus;
use App\Http\Controllers\Controller;
use App\Models\M02\Category;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M07\Payment;
use App\Models\M08\Enrollment;
use App\Models\M12\Coupon;
use App\Models\M12\CoursePromotion;
use App\Models\M13\ActivationCode;
use App\Models\M13\ActivationCodeBatch;
use App\Models\M14\CourseRating;
use App\Models\M16\SecurityAlert;
use App\Models\User;
use App\Support\M12\Commerce;
use App\Support\M12\CouponSlots;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** MH-GVU-12..15, thu hồi/khôi phục (MH-GVU-09), chọn hoàn (MH-GVU-08). Tuyến `role:GIAO_VU`. */
final class CommerceController extends Controller
{
    public function promotions(): Response
    {
        $courses = Course::query()->whereIn('sale_status', [SaleStatus::DangBan, SaleStatus::NgungBan])->orderBy('title')->get(['id', 'title', 'price']);

        return Inertia::render('academic/promotions/index', [
            'courses' => $courses->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'price' => $c->price])->values(),
            'promotions' => CoursePromotion::query()->with('course:id,title,price')->latest('starts_at')->limit(200)->get()
                ->map(fn (CoursePromotion $p) => [
                    'id' => $p->id, 'course_id' => $p->course_id, 'course' => $p->course->title, 'list_price' => $p->course->price,
                    'price' => $p->price, 'starts_on' => $p->starts_on->format('Y-m-d'), 'ends_on' => $p->ends_on->format('Y-m-d'),
                    'state' => now()->lt($p->starts_at) ? 'Sắp chạy' : (now()->lt($p->ends_at) ? 'Đang chạy' : 'Đã kết thúc'),
                ])->values(),
        ]);
    }

    public function savePromotion(Request $request, SetCoursePromotion $set, ?CoursePromotion $promotion = null): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'price' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d'],
        ]);
        $course = Course::query()->findOrFail((int) $data['course_id']);
        $set->handle($this->actor($request), $course, [
            'price' => (int) $data['price'], 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
        ], $promotion);

        return back()->with('status', 'Đã lưu giá khuyến mãi.');
    }

    public function coupons(): Response
    {
        return Inertia::render('academic/coupons/index', [
            'coupons' => Coupon::query()->latest('id')->limit(200)->get()->map(fn (Coupon $c) => [
                'id' => $c->id, 'code' => $c->code, 'discount_type' => $c->discount_type->value, 'value' => $c->value,
                'max_discount' => $c->max_discount, 'starts_on' => $c->starts_on->format('Y-m-d'), 'ends_on' => $c->ends_on->format('Y-m-d'),
                'total_limit' => $c->total_limit, 'per_user_limit' => $c->per_user_limit, 'scope_type' => $c->scope_type->value,
                'scope_category_id' => $c->scope_category_id, 'scope_course_id' => $c->scope_course_id, 'min_order' => $c->min_order,
                'note' => $c->note, 'used' => CouponSlots::occupied($c)->where('status', 'DA_DUNG')->count(),
                'held' => CouponSlots::occupied($c)->where('status', 'GIU_CHO')->count(),
                'active' => now()->gte($c->starts_at) && now()->lt($c->ends_at),
            ])->values(),
            'categories' => Category::query()->orderBy('name')->get(['id', 'name', 'parent_id'])->map(fn (Category $c) => [
                'id' => $c->id, 'name' => $c->name, 'parent_id' => $c->parent_id])->values(),
            'courses' => Course::query()->orderBy('title')->get(['id', 'title'])->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title])->values(),
        ]);
    }

    public function saveCoupon(Request $request, SaveCoupon $save, ?Coupon $coupon = null): RedirectResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40'],
            'discount_type' => ['required', Rule::enum(DiscountType::class)],
            'value' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'max_discount' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'starts_on' => ['required', 'date_format:Y-m-d'],
            'ends_on' => ['required', 'date_format:Y-m-d'],
            'total_limit' => ['required', 'integer', 'min:0', 'max:1000000'],
            'per_user_limit' => ['required', 'integer', 'min:0', 'max:1000'],
            'scope_type' => ['required', Rule::enum(CouponScope::class)],
            'scope_category_id' => ['nullable', 'integer', 'exists:categories,id'],
            'scope_course_id' => ['nullable', 'integer', 'exists:courses,id'],
            'min_order' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $save->handle($this->actor($request), [
            'code' => $data['code'], 'discount_type' => $data['discount_type'], 'value' => (int) $data['value'],
            'max_discount' => isset($data['max_discount']) ? (int) $data['max_discount'] : null,
            'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
            'total_limit' => (int) $data['total_limit'], 'per_user_limit' => (int) $data['per_user_limit'],
            'scope_type' => $data['scope_type'],
            'scope_category_id' => isset($data['scope_category_id']) ? (int) $data['scope_category_id'] : null,
            'scope_course_id' => isset($data['scope_course_id']) ? (int) $data['scope_course_id'] : null,
            'min_order' => (int) ($data['min_order'] ?? 0), 'note' => $data['note'] ?? null,
        ], $coupon);

        return back()->with('status', 'Đã lưu mã giảm giá.');
    }

    public function batches(): Response
    {
        return Inertia::render('academic/activation/index', [
            'batches' => ActivationCodeBatch::query()->with('course:id,title')->latest('id')->limit(100)->get()
                ->map(fn (ActivationCodeBatch $b) => [
                    'id' => $b->id, 'course' => $b->course->title, 'quantity' => $b->quantity, 'note' => $b->note,
                    'access_months' => $b->access_months, 'redeem_until' => $b->redeem_until->toIso8601String(),
                    'created_at' => $b->created_at?->toIso8601String(), 'stats' => ActivationCodes::stats($b),
                ])->values(),
            'courses' => Course::query()->whereNotNull('current_version_id')->orderBy('title')->get(['id', 'title', 'access_months'])
                ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'access_months' => $c->access_months])->values(),
        ]);
    }

    public function storeBatch(Request $request, ActivationCodes $codes): RedirectResponse
    {
        $data = $request->validate([
            'course_id' => ['required', 'integer', 'exists:courses,id'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'redeem_until' => ['required', 'date'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $until = CarbonImmutable::parse($data['redeem_until'], Commerce::TZ)->utc();
        $batch = $codes->createBatch($this->actor($request), Course::query()->findOrFail((int) $data['course_id']), (int) $data['quantity'], $until, $data['note'] ?? null);

        return to_route('academic.activation-batches.show', $batch)->with('status', 'Đã tạo lô '.$batch->quantity.' mã.');
    }

    public function showBatch(ActivationCodeBatch $batch): Response
    {
        $batch->load('course:id,title');

        return Inertia::render('academic/activation/show', [
            'batch' => [
                'id' => $batch->id, 'course' => $batch->course->title, 'quantity' => $batch->quantity, 'note' => $batch->note,
                'access_months' => $batch->access_months, 'redeem_until' => $batch->redeem_until->toIso8601String(),
                'stats' => ActivationCodes::stats($batch),
            ],
            'codes' => $batch->codes()->orderBy('id')->paginate(50)->through(fn (ActivationCode $c) => [
                'id' => $c->id, 'code' => $c->code,
                'status' => $c->status->value === 'CHUA_DUNG' && now()->gt($batch->redeem_until) ? 'Hết hạn' : $c->status->label(),
                'voidable' => $c->status->value === 'CHUA_DUNG' && now()->lte($batch->redeem_until),
                'redeemed_at' => $c->redeemed_at?->toIso8601String(), 'void_reason' => $c->void_reason,
            ]),
        ]);
    }

    public function exportBatch(ActivationCodeBatch $batch): StreamedResponse
    {
        return response()->streamDownload(function () use ($batch) {
            $out = fopen('php://output', 'w');
            if ($out === false) {
                return;
            }
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, ['ma', 'trang_thai', 'han_doi'], ',', '"', '');
            $batch->codes()->orderBy('id')->each(fn (ActivationCode $c) => fputcsv($out, [
                $c->code, $c->status->value, $batch->redeem_until->setTimezone(Commerce::TZ)->format('Y-m-d H:i'),
            ], ',', '"', ''));
            fclose($out);
        }, 'lo-ma-'.$batch->id.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function voidCodes(Request $request, ActivationCodeBatch $batch, ActivationCodes $codes): RedirectResponse
    {
        $data = $request->validate(['code_id' => ['nullable', 'integer'], 'reason' => ['required', 'string', 'max:500'], 'confirmed' => ['accepted']]);
        $code = isset($data['code_id']) ? $batch->codes()->whereKey((int) $data['code_id'])->firstOrFail() : null;
        $n = $codes->void($this->actor($request), $batch, $code, $data['reason'], true);

        return back()->with('status', 'Đã vô hiệu '.$n.' mã.');
    }

    public function moderation(): Response
    {
        return Inertia::render('academic/moderation/index', [
            'ratings' => CourseRating::query()->with(['user:id,name', 'course:id,title'])->latest('id')->limit(100)->get()
                ->map(fn (CourseRating $r) => [
                    'id' => $r->id, 'student' => $r->user->name, 'course' => $r->course->title, 'stars' => $r->stars,
                    'comment' => $r->comment, 'hidden' => $r->status === RatingStatus::DaAn, 'hidden_reason' => $r->hidden_reason,
                    'reply' => $r->reply, 'created_at' => $r->created_at?->toIso8601String(),
                ])->values(),
            'alerts' => SecurityAlert::query()->with('user:id,name,email')->latest('detected_at')->limit(50)->get()
                ->map(fn (SecurityAlert $a) => [
                    'id' => $a->id, 'user' => $a->user->name, 'email' => $a->user->email, 'ips' => $a->ip_list,
                    'detected_at' => $a->detected_at->toIso8601String(),
                ])->values(),
        ]);
    }

    public function hideRating(Request $request, CourseRating $rating, CourseRatings $ratings): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $ratings->hide($this->actor($request), $rating, $data['reason']);

        return back()->with('status', 'Đã ẩn đánh giá.');
    }

    public function unhideRating(Request $request, CourseRating $rating, CourseRatings $ratings): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);
        $ratings->unhide($this->actor($request), $rating, $data['reason']);

        return back()->with('status', 'Đã hiện lại đánh giá.');
    }

    public function revoke(Request $request, Enrollment $enrollment, RevokeEnrollment $revoke): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'confirmed' => ['accepted']]);
        $revoke->revoke($this->actor($request), $enrollment, $data['reason'], true);

        return back()->with('status', 'Đã thu hồi quyền học.');
    }

    public function restore(Request $request, Enrollment $enrollment, RevokeEnrollment $revoke): RedirectResponse
    {
        $revoke->restore($this->actor($request), $enrollment);

        return back()->with('status', 'Đã khôi phục quyền học.');
    }

    public function stopGrantRefund(Request $request, Order $order, Payment $payment, StopGrantAndRefund $stop): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:500'], 'confirmed' => ['accepted']]);
        $stop->handle($this->actor($request), $order, $payment, $data['reason'], true);

        return back()->with('status', 'Đã dừng cấp; khoản chuyển sang cần hoàn.');
    }

    private function actor(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
