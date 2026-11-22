<?php

namespace App\Support;

use App\Enums\M11\AuditActorType;
use App\Models\M11\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Request;

/**
 * Ghi audit_logs cùng giao dịch và cùng kết nối với thao tác (ADR-011, L35).
 * Gọi bên trong Tx::run sau khi thao tác ghi thành công.
 */
final class Audit
{
    /**
     * @param  array<string, mixed>|null  $old
     * @param  array<string, mixed>|null  $new
     */
    public static function log(string $action, ?User $actor, Model $subject, ?array $old = null, ?array $new = null, ?string $reason = null): AuditLog
    {
        return AuditLog::query()->create([
            'action' => $action,
            'actor_type' => $actor !== null ? AuditActorType::TaiKhoan : (app()->runningInConsole() && ! app()->runningUnitTests() ? AuditActorType::LenhMayChu : AuditActorType::HeThong),
            'actor_id' => $actor?->id,
            'subject_type' => self::alias($subject),
            'subject_id' => (int) $subject->getKey(),
            'old_values' => $old,
            'new_values' => $new,
            'reason' => $reason !== null ? mb_substr($reason, 0, 1000) : null,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'created_at' => now(),
        ]);
    }

    /** Morph alias cố định: tên lớp ngắn viết thường kiểu snake (course_version, course…). */
    public static function alias(Model $subject): string
    {
        return $subject->getTable() === 'course_versions' ? 'course_version' : rtrim($subject->getTable(), 's');
    }
}
