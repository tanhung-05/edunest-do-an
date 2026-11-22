<?php

namespace App\Models\M13;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * N07 activation_code_batches — lô mã kích hoạt.
 *
 * @property int $id
 * @property int $course_id
 * @property int $quantity
 * @property CarbonImmutable $redeem_until
 * @property ?int $access_months
 * @property ?string $note
 * @property int $created_by_id
 * @property ?CarbonImmutable $created_at
 */
class ActivationCodeBatch extends Model
{
    protected $table = 'activation_code_batches';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'course_id' => 'integer',
            'quantity' => 'integer',
            'access_months' => 'integer',
            'redeem_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return HasMany<ActivationCode, $this> */
    public function codes(): HasMany
    {
        return $this->hasMany(ActivationCode::class, 'batch_id');
    }
}
