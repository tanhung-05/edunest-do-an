<?php

namespace App\Http\Controllers\Academic;

use App\Actions\M24\AffiliateCommissions;
use App\Actions\M24\Affiliates;
use App\Actions\M24\Bundles;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\M24\Affiliate;
use App\Models\M24\AffiliateCommission;
use App\Models\M24\Bundle;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/** MH-GVU-xx: Giáo vụ quản lý combo, cộng tác viên và duyệt chi hoa hồng mô phỏng (QT-26 bước 1–2, 11–12, 17–18). */
final class BundleAffiliateController extends Controller
{
    public function bundles(): Response
    {
        return Inertia::render('academic/bundles/index', [
            'bundles' => Bundle::query()->with('courses:id,title')->latest('id')->get()->map(fn (Bundle $b) => [
                'id' => $b->id, 'title' => $b->title, 'slug' => $b->slug, 'description' => $b->description, 'price' => $b->price,
                'deduct_owned' => $b->deduct_owned, 'accepts_orders' => $b->accepts_orders,
                'course_ids' => $b->courses->modelKeys(), 'courses' => $b->courses->pluck('title')->all(),
            ])->values(),
            'courses' => Course::query()->whereNotNull('current_version_id')->orderBy('title')->get(['id', 'title', 'price'])
                ->map(fn (Course $c) => ['id' => $c->id, 'title' => $c->title, 'price' => $c->price])->values(),
        ]);
    }

    public function saveBundle(Request $request, Bundles $bundles, ?Bundle $bundle = null): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:200'], 'description' => ['nullable', 'string', 'max:2000'],
            'price' => ['required', 'integer', 'min:1'], 'deduct_owned' => ['boolean'],
            'course_ids' => ['required', 'array', 'min:2', 'max:20'], 'course_ids.*' => ['integer', 'distinct']]);
        $bundles->save($this->user($request), [
            'title' => (string) $data['title'], 'description' => isset($data['description']) ? (string) $data['description'] : null,
            'price' => (int) $data['price'], 'deduct_owned' => (bool) ($data['deduct_owned'] ?? false),
            'course_ids' => array_values(array_map('intval', (array) $data['course_ids'])),
        ], $bundle);

        return back()->with('status', 'Đã lưu combo.');
    }

    public function toggleBundle(Request $request, Bundle $bundle, Bundles $bundles): RedirectResponse
    {
        $data = $request->validate(['accepts_orders' => ['required', 'boolean'], 'confirmed' => ['accepted']]);
        $bundles->setAccepting($this->user($request), $bundle, (bool) $data['accepts_orders'], true);

        return back()->with('status', $data['accepts_orders'] ? 'Combo nhận đơn mới.' : 'Combo đã ngừng nhận đơn mới.');
    }

    public function affiliates(): Response
    {
        return Inertia::render('academic/affiliates/index', [
            'affiliates' => Affiliate::query()->with('user:id,name,email')->latest('id')->get()->map(fn (Affiliate $a) => [
                'id' => $a->id, 'name' => $a->user?->name, 'email' => $a->user?->email, 'code' => $a->code, 'rate_bp' => $a->rate_bp,
                'is_active' => $a->is_active, 'link' => '?ref='.$a->code,
            ])->values(),
            'commissions' => AffiliateCommission::query()->with(['order:id,public_code,total', 'affiliate.user:id,name'])->latest('id')->limit(100)->get()
                ->map(fn (AffiliateCommission $c) => ['id' => $c->id, 'order' => $c->order?->public_code, 'affiliate' => $c->affiliate?->user?->name,
                    'base_amount' => $c->base_amount, 'rate_bp' => $c->rate_bp, 'amount' => $c->amount, 'status' => $c->status->value,
                    'status_label' => $c->status->label(), 'recorded_at' => $c->recorded_at->toISOString(), 'cancel_reason' => $c->cancel_reason])->values(),
        ]);
    }

    public function saveAffiliate(Request $request, Affiliates $affiliates, ?Affiliate $affiliate = null): RedirectResponse
    {
        $data = $request->validate(['email' => [$affiliate === null ? 'required' : 'nullable', 'email'], 'code' => ['required', 'string', 'max:32'],
            'rate_percent' => ['required', 'numeric', 'min:0', 'max:50'], 'is_active' => ['boolean']]);
        $affiliates->save($this->user($request), ['email' => $data['email'] ?? null, 'code' => $data['code'],
            'rate_bp' => (int) round(((float) $data['rate_percent']) * 100), 'is_active' => (bool) ($data['is_active'] ?? false)], $affiliate);

        return back()->with('status', 'Đã lưu hồ sơ cộng tác viên.');
    }

    public function payCommission(Request $request, AffiliateCommission $commission, AffiliateCommissions $commissions): RedirectResponse
    {
        $commissions->pay($this->user($request), $commission->id);

        return back()->with('status', 'Đã ghi chi trả mô phỏng.');
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
