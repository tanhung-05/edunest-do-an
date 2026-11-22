<?php

namespace App\Models\M11;

use App\Enums\M11\AuditActorType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * L35 audit_logs — nhật ký chỉ thêm (ADR-011).
 *
 * @property int $id
 * @property string $action
 * @property AuditActorType $actor_type
 * @property ?int $actor_id
 * @property string $subject_type
 * @property int $subject_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property ?string $reason
 * @property ?string $ip_address
 * @property CarbonImmutable $created_at
 */
class AuditLog extends Model
{
    public const UPDATED_AT = null;

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['actor_type' => AuditActorType::class, 'old_values' => 'array', 'new_values' => 'array'];
    }
}
