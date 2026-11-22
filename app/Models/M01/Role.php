<?php

namespace App\Models\M01;

use App\Enums\M01\RoleCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * L02 roles. Dòng QUAN_TRI là khóa gác cho bất biến "còn Quản trị hoạt động" (05 §1).
 *
 * @property int $id
 * @property RoleCode $code
 * @property string $name
 */
class Role extends Model
{
    protected $fillable = ['code', 'name'];

    protected function casts(): array
    {
        return ['code' => RoleCode::class];
    }

    /** @return BelongsToMany<User, $this> */
    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class)->withPivot(['assigned_by_id', 'assigned_at'])->withTimestamps();
    }

    public static function idFor(RoleCode $code): int
    {
        /** @var array<string, int> $map */
        static $map = [];

        return $map[$code->value] ??= (int) static::query()->where('code', $code->value)->value('id');
    }

    /** Khóa gác `roles(QUAN_TRI)` — luôn lấy trước mọi dòng `users` (03 TK-3, 05 §1). */
    public static function lockAdminGuard(): void
    {
        static::query()->where('code', RoleCode::QuanTri->value)->lockForUpdate()->first();
    }
}
