<?php

namespace App\Models\M23;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * guardian_student — liên kết phụ huynh–học viên; một dòng mỗi cặp, gỡ thì đặt `removed_at`, liên kết lại dùng cùng dòng (BR-MR-03/07).
 *
 * @property int $id
 * @property int $guardian_id
 * @property int $student_id
 * @property int $guardian_invite_id
 * @property CarbonImmutable $linked_at
 * @property CarbonImmutable|null $removed_at
 * @property int|null $removed_by_id
 */
class GuardianLink extends Model
{
    protected $table = 'guardian_student';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'guardian_id' => 'integer',
            'student_id' => 'integer',
            'guardian_invite_id' => 'integer',
            'linked_at' => 'immutable_datetime',
            'removed_at' => 'immutable_datetime',
            'removed_by_id' => 'integer',
        ];
    }

    /** @param Builder<self> $query */
    public function scopeActive(Builder $query): void
    {
        $query->whereNull('removed_at');
    }

    /** @return BelongsTo<User, $this> */
    public function guardian(): BelongsTo
    {
        return $this->belongsTo(User::class, 'guardian_id');
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
