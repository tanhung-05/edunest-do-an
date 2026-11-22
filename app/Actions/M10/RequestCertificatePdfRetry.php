<?php

namespace App\Actions\M10;

use App\Enums\M01\RoleCode;
use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use App\Support\Tx;

final class RequestCertificatePdfRetry
{
    public function __construct(private EnsureCertificatePdfJob $pdf) {}

    public function handle(User $user, Certificate $certificate): void
    {
        Tx::run(function () use ($user, $certificate): void {
            $user = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            if (! $user->hasEffectiveRole(RoleCode::GiaoVu)) {
                throw DomainRejection::forbidden('Bạn không có quyền tạo lại PDF.');
            }
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($certificate->id);
            TestBarrier::reach('request-certificate-pdf-retry.locked');
            if ($certificate->status !== CertificateStatus::HieuLuc || $certificate->pdf_status !== CertificatePdfStatus::Loi) {
                throw new DomainRejection('Chứng nhận hiện không đủ điều kiện tạo lại PDF.');
            }
            $previous = $certificate->pdf_job_token;
            $this->pdf->scheduleUnderLock($certificate);
            if ($previous !== $certificate->pdf_job_token) {
                Audit::log('CERTIFICATE_PDF_RETRY', $user, $certificate);
            }
        });
    }
}
