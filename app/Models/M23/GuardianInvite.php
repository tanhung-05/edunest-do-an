<?php

namespace App\Models\M23;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * guardian_invites — mã mời phụ huynh: một lần, hạn 7 ngày tính tới đúng mốc cấp (BR-MR-01, CR-99).
 *
 * @property int $id
 * @property int $student_id
 * @property string $code
 * @property CarbonImmutable $issued_at
 * @property CarbonImmutable $expires_at
 * @property CarbonImmutable|null $used_at
 * @property int|null $used_by_id
 */
class GuardianInvite extends Model
{
    protected $table = 'guardian_invites';

    protected $guarded = ['id'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'student_id' => 'integer',
            'issued_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'used_at' => 'immutable_datetime',
            'used_by_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function student(): BelongsTo
    {
        return $this->belongsTo(User::class, 'student_id');
    }
}
