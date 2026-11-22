<?php

namespace App\Actions\M24;

use App\Actions\M07\ExpireOrder;
use App\Actions\M07\PlaceOrder;
use App\Enums\M03\SaleStatus;
use App\Enums\M07\OrderItemKind;
use App\Enums\M07\OrderItemStatus;
use App\Enums\M07\OrderSource;
use App\Enums\M07\OrderStatus;
use App\Models\M03\Course;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M11\Setting;
use App\Models\M24\Bundle;
use App\Models\User;
use App\Queries\M07\OpenOrders;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * QT-26a: Giáo vụ tạo/sửa combo và cờ nhận đơn (bước 1–2, A1, CR-103/162); Học viên đặt combo (bước 3–5, E10, E14, X3, X4).
 * Đặt combo dùng chung gác `users` của học viên với Mua ngay, giỏ và đổi mã, rồi khóa combo, rồi `courses` theo id tăng dần.
 */
final class Bundles
{
    /** @param array{title: string, description?: string|null, price: int, deduct_owned: bool, course_ids: list<int>} $data */
    public function save(User $actor, array $data, ?Bundle $bundle = null): Bundle
    {
        return Tx::run(function () use ($actor, $data, $bundle) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->first());
            $ids = array_values(array_unique(array_map('intval', $data['course_ids'])));
            if (count($ids) < 2) {
                throw new DomainRejection('Combo cần ít nhất 2 khóa khác nhau.', 'course_ids');
            }
            $courses = Course::query()->whereKey($ids)->whereNotNull('current_version_id')->get()->keyBy('id');
            if ($courses->count() !== count($ids)) {
                throw new DomainRejection('Chỉ chọn khóa đã công bố.', 'course_ids');
            }
            $retail = (int) $courses->sum('price');
            if ($data['price'] < 1 || $data['price'] > $retail) {
                throw new DomainRejection('Giá combo phải từ 1đ đến tổng giá niêm yết các khóa ('.Commerce::vnd($retail).').', 'price');
            }
            $values = ['title' => trim($data['title']), 'description' => $data['description'] ?? null, 'price' => $data['price'],
                'deduct_owned' => $data['deduct_owned'], 'updated_by_id' => $actor->id];
            if ($bundle === null) {
                $bundle = Bundle::query()->create([...$values, 'slug' => self::slug($values['title']), 'created_by_id' => $actor->id, 'accepts_orders' => true]);
            } else {
                $bundle = Bundle::query()->whereKey($bundle->id)->lockForUpdate()->firstOrFail();
                $bundle->forceFill($values)->save();
            }
            $bundle->courses()->sync(collect($ids)->mapWithKeys(fn (int $id, int $i) => [$id => ['position' => $i]])->all());
            Audit::log('bundle.save', $actor, $bundle, null, [...$values, 'course_ids' => $ids]);

            return $bundle;
        });
    }

    /** A1 (CR-162): đổi cờ nhận đơn mới sau xác nhận, dưới khóa combo dùng chung với tạo đơn. */
    public function setAccepting(User $actor, Bundle $bundle, bool $accepts, bool $confirmed): Bundle
    {
        if (! $confirmed) {
            throw new DomainRejection('Hãy xác nhận thay đổi.', 'confirmed');
        }

        return Tx::run(function () use ($actor, $bundle, $accepts) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->first());
            $locked = Bundle::query()->whereKey($bundle->id)->lockForUpdate()->firstOrFail();
            $old = $locked->accepts_orders;
            $locked->forceFill(['accepts_orders' => $accepts, 'updated_by_id' => $actor->id])->save();
            Audit::log('bundle.accepts_orders', $actor, $locked, ['accepts_orders' => $old], ['accepts_orders' => $accepts]);

            return $locked;
        });
    }

    /**
     * Bước 3–5. Trả đơn mới, hoặc danh sách đơn mở trùng (E10), hoặc `owned_all` khi đã sở hữu toàn bộ.
     *
     * @return array{order: ?Order, blocked: list<Order>, owned_all: bool}
     */
    public function place(User $actor, Bundle $bundle, ?string $ref = null): array
    {
        for ($attempt = 1; ; $attempt++) {
            try {
                $result = Tx::run(fn () => $this->placeLocked($actor, $bundle->id, $ref));
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062 || ! str_contains($e->getMessage(), 'orders_public_code_unique')) {
                    throw $e;
                }

                continue;
            }
            if (is_int($result)) {
                app(ExpireOrder::class)->handle($result);

                continue;
            }

            return $result;
        }
    }

    /** @return array{order: ?Order, blocked: list<Order>, owned_all: bool}|int */
    private function placeLocked(User $actor, int $bundleId, ?string $ref): array|int
    {
        $student = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
        OrderLocks::student($student);
        if (! $student->hasVerifiedEmail()) {
            throw DomainRejection::forbidden('Hãy xác minh email trước khi mua combo.');
        }
        $bundle = Bundle::query()->whereKey($bundleId)->sharedLock()->firstOrFail();
        $courseIds = array_values(array_map('intval', $bundle->courses()->pluck('courses.id')->all()));
        $now = now();

        $blocked = [];
        foreach (OpenOrders::forCourses($student->id, $courseIds, true)->orderBy('id')->get() as $open) {
            if ($open->status === OrderStatus::ChoThanhToan && $now->gt($open->expires_at)) {
                return $open->id;
            }
            $blocked[] = $open;
        }
        if ($blocked !== []) {
            return ['order' => null, 'blocked' => $blocked, 'owned_all' => false];
        }
        if (! $bundle->accepts_orders) {
            throw new DomainRejection('Combo này đang tạm ngừng nhận đơn mới.', 'bundle');
        }

        $sorted = $courseIds;
        sort($sorted);
        $courses = Course::query()->whereKey($sorted)->orderBy('id')->sharedLock()->get()->keyBy('id');
        $owned = array_map('intval', Enrollment::query()->where('user_id', $student->id)->whereIn('course_id', $courseIds)->pluck('course_id')->all());
        if (count($owned) === count($courseIds)) {
            return ['order' => null, 'blocked' => [], 'owned_all' => true];
        }
        if ($owned !== [] && ! $bundle->deduct_owned) {
            throw new DomainRejection('Bạn đã sở hữu một số khóa của combo. Hãy mua lẻ các khóa còn lại.', 'bundle');
        }
        $remaining = array_values(array_filter($courseIds, fn (int $id) => ! in_array($id, $owned, true)));
        $stopped = array_values(array_filter($remaining, function (int $id) use ($courses): bool {
            $course = $courses->get($id);

            return $course === null || $course->sale_status !== SaleStatus::DangBan || $course->current_version_id === null;
        }));
        if ($stopped !== []) {
            $titles = implode(', ', array_map(fn (int $id) => (string) $courses->get($id)?->title, $stopped));
            throw new DomainRejection('Combo có khóa đã ngừng bán: '.$titles.'. Hãy mua lẻ các khóa còn bán.', 'bundle');
        }

        // CR-96/236: trừ giá lẻ lúc đặt của khóa đã sở hữu; còn dòng mà tổng ≤ 0đ thì chặn (X4, CR-163).
        $prices = [];
        foreach ($courseIds as $id) {
            $course = $courses->get($id);
            $prices[$id] = $course === null ? 0 : Commerce::effectivePrice($course, $now)[0];
        }
        $total = $bundle->price - array_sum(array_map(fn (int $id) => $prices[$id], $owned));
        if ($total <= 0) {
            throw new DomainRejection('Giá combo sau khi trừ khóa đã sở hữu không còn dương. Hãy mua lẻ các khóa còn lại.', 'bundle');
        }
        $bases = [];
        foreach ($remaining as $id) {
            $bases[$id] = $prices[$id];
        }
        $subtotal = array_sum($bases);
        $total = min($total, $subtotal);
        $alloc = Commerce::allocate($total, $bases);

        $order = new Order;
        $order->forceFill([
            'public_code' => PlaceOrder::publicCode(), 'user_id' => $student->id, 'status' => OrderStatus::ChoThanhToan,
            'source' => OrderSource::Combo, 'subtotal' => $subtotal, 'discount_total' => $subtotal - $total, 'total' => $total,
            'affiliate_id' => Affiliates::sourceFor($ref, $student->id),
            'expires_at' => $now->addMinutes(Setting::number('order.payment_timeout_minutes')), 'created_at' => $now,
        ])->save();
        foreach ($remaining as $position => $id) {
            $course = $courses->get($id);
            $order->items()->create([
                'course_id' => $id, 'bundle_id' => $bundle->id, 'kind' => OrderItemKind::MuaKhoa, 'status' => OrderItemStatus::ChuaCap,
                'course_title' => (string) $course?->title, 'list_price' => (int) $course?->price, 'effective_price' => $prices[$id],
                'allocated_discount' => $prices[$id] - $alloc[$id], 'final_amount' => $alloc[$id], 'access_months' => $course?->access_months, 'sort_order' => $position,
            ]);
        }

        return ['order' => $order, 'blocked' => [], 'owned_all' => false];
    }

    private static function slug(string $title): string
    {
        $base = Str::slug($title) ?: 'combo';
        $slug = $base;
        for ($i = 2; Bundle::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
