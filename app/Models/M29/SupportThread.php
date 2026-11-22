<?php

namespace App\Models\M29;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * P10 M29 support_threads — cuộc trò chuyện hỗ trợ của một tài khoản với nhân viên (Giáo vụ).
 *
 * @property int $id
 * @property ?int $user_id
 * @property ?string $guest_key
 * @property ?string $guest_name
 * @property ?string $guest_phone
 * @property string $status
 * @property ?int $assigned_to_id
 * @property int $staff_unread
 * @property int $user_unread
 * @property ?CarbonImmutable $last_message_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 * @property-read ?User $user
 * @property-read ?User $assignedTo
 */
class SupportThread extends Model
{
    public const CHO = 'CHO';

    public const DA_TRA_LOI = 'DA_TRA_LOI';

    public const DONG = 'DONG';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['last_message_at' => 'immutable_datetime', 'staff_unread' => 'integer', 'user_unread' => 'integer'];
    }

    /** Tên hiện cho nhân viên: tài khoản hoặc tên Khách tự nhập. */
    public function displayName(): string
    {
        return $this->user->name ?? ($this->guest_name ?? 'Khách');
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<User, $this> */
    public function assignedTo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_id');
    }

    /** @return HasMany<SupportMessage, $this> */
    public function messages(): HasMany
    {
        return $this->hasMany(SupportMessage::class)->orderBy('id');
    }
}
