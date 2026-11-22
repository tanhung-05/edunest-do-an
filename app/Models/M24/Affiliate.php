<?php

namespace App\Models\M24;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * affiliates — hồ sơ cộng tác viên gắn tài khoản Học viên, đường dẫn riêng, tỷ lệ phần vạn (BR-TT-71/72, CR-94/95).
 *
 * @property int $id
 * @property int $user_id
 * @property string $code
 * @property int $rate_bp
 * @property bool $is_active
 * @property int $created_by_id
 * @property int $updated_by_id
 * @property CarbonImmutable|null $created_at
 */
class Affiliate extends Model
{
    protected $table = 'affiliates';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'rate_bp' => 'integer',
            'is_active' => 'boolean',
            'created_by_id' => 'integer',
            'updated_by_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
