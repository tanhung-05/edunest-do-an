<?php

namespace App\Actions\M10;

use App\Enums\M01\RoleCode;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use App\Support\Tx;

final class RevokeCertificate
{
    public function handle(User $user, Certificate $certificate, string $reason): void
    {
        Tx::run(function () use ($user, $certificate, $reason): void {
            $user = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            if (! $user->hasEffectiveRole(RoleCode::GiaoVu)) {
                throw DomainRejection::forbidden('Bạn không có quyền thu hồi chứng nhận.');
            }
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);
            TestBarrier::reach('revoke-certificate.locked');
            if ($certificate->status === CertificateStatus::DaThuHoi) {
                throw new DomainRejection('Chứng nhận đã thu hồi. Tải lại danh sách.');
            }
            $reason = trim($reason);
            if ($reason === '' || mb_strlen($reason) > 500) {
                throw new DomainRejection('Nhập lý do thu hồi, tối đa 500 ký tự.', 'reason');
            }

            $certificate->forceFill([
                'status' => CertificateStatus::DaThuHoi,
                'revoked_at' => now(),
                'revoked_by_id' => $user->id,
                'revoked_reason' => $reason,
            ])->save();
            Audit::log('CERTIFICATE_REVOKE', $user, $certificate, ['status' => CertificateStatus::HieuLuc->value], ['status' => $certificate->status->value], $reason);
        });
    }
}
