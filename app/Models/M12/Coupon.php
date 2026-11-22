<?php

namespace App\Models\M12;

use App\Enums\M12\CouponScope;
use App\Enums\M12\DiscountType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * N01 coupons — mã giảm giá (02b §1).
 *
 * @property int $id
 * @property string $code
 * @property DiscountType $discount_type
 * @property int $value
 * @property ?int $max_discount
 * @property CarbonImmutable $starts_on
 * @property CarbonImmutable $ends_on
 * @property CarbonImmutable $starts_at
 * @property CarbonImmutable $ends_at
 * @property int $total_limit
 * @property int $per_user_limit
 * @property CouponScope $scope_type
 * @property ?int $scope_category_id
 * @property ?int $scope_course_id
 * @property int $min_order
 * @property ?string $note
 * @property ?int $created_by_id
 * @property ?int $updated_by_id
 */
class Coupon extends Model
{
    protected $table = 'coupons';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'discount_type' => DiscountType::class,
            'scope_type' => CouponScope::class,
            'value' => 'integer',
            'max_discount' => 'integer',
            'total_limit' => 'integer',
            'per_user_limit' => 'integer',
            'min_order' => 'integer',
            'scope_category_id' => 'integer',
            'scope_course_id' => 'integer',
            'starts_on' => 'immutable_date',
            'ends_on' => 'immutable_date',
            'starts_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
        ];
    }

    /** @return HasMany<CouponRedemption, $this> */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }
}
