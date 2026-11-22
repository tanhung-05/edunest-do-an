<?php

namespace App\Actions\M12;

use App\Enums\M12\CouponScope;
use App\Enums\M12\DiscountType;
use App\Models\M12\Coupon;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\M12\Commerce;
use App\Support\Tx;
use Illuminate\Database\QueryException;

/** QT-13 b3–4, A11, A12 (NH-10). Sửa không đổi đơn đã áp (BR-TT-06); không xóa (CR-12). */
final class SaveCoupon
{
    /**
     * @param  array{code: string, discount_type: string, value: int, max_discount: ?int, starts_on: string, ends_on: string,
     *     total_limit: int, per_user_limit: int, scope_type: string, scope_category_id: ?int, scope_course_id: ?int, min_order: int, note: ?string}  $data
     */
    public function handle(User $actor, array $data, ?Coupon $coupon = null): Coupon
    {
        $type = DiscountType::from($data['discount_type']);
        $scope = CouponScope::from($data['scope_type']);
        $code = Commerce::normalizeCode($data['code']);
        if ($code === '' || preg_match('/^[A-Z0-9_-]{3,40}$/', $code) !== 1) {
            throw new DomainRejection('Mã gồm 3–40 ký tự chữ, số, gạch ngang hoặc gạch dưới.', 'code');
        }
        if ($type === DiscountType::PhanTram && ($data['value'] < 1 || $data['value'] > 100)) {
            throw new DomainRejection('Phần trăm giảm phải từ 1 đến 100.', 'value');
        }
        if ($data['value'] < 1) {
            throw new DomainRejection('Giá trị giảm phải dương.', 'value');
        }
        if ($type === DiscountType::PhanTram && ($data['max_discount'] ?? 0) < 1) {
            throw new DomainRejection('Mã phần trăm cần trần giảm dương.', 'max_discount');
        }
        if ($data['total_limit'] < 1 || $data['per_user_limit'] < 1) {
            throw new DomainRejection('Số lượt phải dương.', 'total_limit');
        }
        if ($data['ends_on'] < $data['starts_on']) {
            throw new DomainRejection('Ngày kết thúc không được trước ngày bắt đầu.', 'ends_on');
        }
        if ($scope === CouponScope::DanhMuc && $data['scope_category_id'] === null) {
            throw new DomainRejection('Chọn danh mục áp dụng.', 'scope_category_id');
        }
        if ($scope === CouponScope::Khoa && $data['scope_course_id'] === null) {
            throw new DomainRejection('Chọn khóa áp dụng.', 'scope_course_id');
        }

        try {
            return Tx::run(function () use ($actor, $data, $coupon, $type, $scope, $code) {
                $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                OrderLocks::academic($locked);
                $old = $coupon?->only(['code', 'value', 'starts_on', 'ends_on', 'total_limit', 'per_user_limit', 'min_order']);
                $coupon = $coupon === null ? new Coupon(['created_by_id' => $actor->id])
                    : Coupon::query()->whereKey($coupon->id)->lockForUpdate()->firstOrFail();
                $coupon->fill([
                    'code' => $code, 'discount_type' => $type, 'value' => $data['value'],
                    'max_discount' => $type === DiscountType::PhanTram ? $data['max_discount'] : null,
                    'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'],
                    'starts_at' => Commerce::vnDayStart($data['starts_on']), 'ends_at' => Commerce::vnDayEndExclusive($data['ends_on']),
                    'total_limit' => $data['total_limit'], 'per_user_limit' => $data['per_user_limit'], 'scope_type' => $scope,
                    'scope_category_id' => $scope === CouponScope::DanhMuc ? $data['scope_category_id'] : null,
                    'scope_course_id' => $scope === CouponScope::Khoa ? $data['scope_course_id'] : null,
                    'min_order' => $data['min_order'], 'note' => $data['note'], 'updated_by_id' => $actor->id,
                ])->save();
                Audit::log($old === null ? 'COUPON_CREATE' : 'COUPON_UPDATE', $actor, $coupon,
                    $old === null ? null : array_map(fn ($v) => (string) $v, $old), ['code' => $code, 'ends_on' => $data['ends_on']]);

                return $coupon;
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw new DomainRejection('Mã '.$code.' đã tồn tại.', 'code');
            }
            throw $e;
        }
    }
}
