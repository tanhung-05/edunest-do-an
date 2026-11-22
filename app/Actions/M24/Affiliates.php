<?php

namespace App\Actions\M24;

use App\Enums\M01\RoleCode;
use App\Models\M24\Affiliate;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Illuminate\Support\Str;

/**
 * QT-26 bước 11–15a, A7 (BR-TT-71/72, CR-94/95/164): Giáo vụ quản lý hồ sơ cộng tác viên của Học viên;
 * đơn chụp tối đa một nguồn hợp lệ lúc tạo, bỏ qua nguồn sai hoặc tự giới thiệu.
 */
final class Affiliates
{
    public const MAX_RATE_BP = 5000;

    /** @param array{email?: string, code: string, rate_bp: int, is_active: bool} $data */
    public function save(User $actor, array $data, ?Affiliate $affiliate = null): Affiliate
    {
        return Tx::run(function () use ($actor, $data, $affiliate) {
            OrderLocks::academic(User::query()->whereKey($actor->id)->lockForUpdate()->first());
            $code = Str::lower(trim($data['code']));
            if (preg_match('/^[a-z0-9-]{3,32}$/', $code) !== 1) {
                throw new DomainRejection('Mã đường dẫn gồm 3–32 ký tự chữ thường, số hoặc gạch ngang.', 'code');
            }
            if ($data['rate_bp'] < 0 || $data['rate_bp'] > self::MAX_RATE_BP) {
                throw new DomainRejection('Tỷ lệ hoa hồng từ 0% đến 50%.', 'rate_bp');
            }
            if (Affiliate::query()->where('code', $code)->when($affiliate, fn ($q) => $q->whereKeyNot($affiliate->id))->exists()) {
                throw new DomainRejection('Mã đường dẫn đã được dùng.', 'code');
            }
            if ($affiliate === null) {
                $user = User::query()->where('email', User::normalizeEmail((string) ($data['email'] ?? '')))->first();
                if ($user === null || ! $user->hasEffectiveRole(RoleCode::HocVien)) {
                    throw new DomainRejection('Không tìm thấy tài khoản Học viên với email này.', 'email');
                }
                if (Affiliate::query()->where('user_id', $user->id)->exists()) {
                    throw new DomainRejection('Học viên này đã có hồ sơ cộng tác viên.', 'email');
                }
                $affiliate = Affiliate::query()->create(['user_id' => $user->id, 'code' => $code, 'rate_bp' => $data['rate_bp'],
                    'is_active' => $data['is_active'], 'created_by_id' => $actor->id, 'updated_by_id' => $actor->id]);
                Audit::log('affiliate.create', $actor, $affiliate, null, ['code' => $code, 'rate_bp' => $data['rate_bp']]);

                return $affiliate;
            }
            $locked = Affiliate::query()->whereKey($affiliate->id)->lockForUpdate()->firstOrFail();
            $old = ['code' => $locked->code, 'rate_bp' => $locked->rate_bp, 'is_active' => $locked->is_active];
            $locked->forceFill(['code' => $code, 'rate_bp' => $data['rate_bp'], 'is_active' => $data['is_active'], 'updated_by_id' => $actor->id])->save();
            Audit::log('affiliate.update', $actor, $locked, $old, ['code' => $code, 'rate_bp' => $data['rate_bp'], 'is_active' => $data['is_active']]);

            return $locked;
        });
    }

    /** Bước 15a: nguồn hợp lệ của đơn sắp tạo, hoặc null (A7). Gọi trong giao dịch tạo đơn. */
    public static function sourceFor(?string $ref, int $buyerId): ?int
    {
        $ref = Str::lower(trim((string) $ref));
        if ($ref === '') {
            return null;
        }
        $affiliate = Affiliate::query()->where('code', $ref)->sharedLock()->first();
        if ($affiliate === null || ! $affiliate->is_active || $affiliate->user_id === $buyerId) {
            return null;
        }
        $owner = User::query()->find($affiliate->user_id);

        return $owner !== null && $owner->isActive() ? $affiliate->id : null;
    }
}
