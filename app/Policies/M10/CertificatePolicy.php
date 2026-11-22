<?php

namespace App\Policies\M10;

use App\Enums\M01\RoleCode;
use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Support\M09\LearningAccess;

final class CertificatePolicy
{
    public function view(User $user, Certificate $certificate): bool
    {
        return $certificate->enrollment !== null && LearningAccess::owns($user, $certificate->enrollment);
    }

    public function download(User $user, Certificate $certificate): bool
    {
        return $this->view($user, $certificate) && $certificate->status === CertificateStatus::HieuLuc
            && $certificate->pdf_status === CertificatePdfStatus::DaTao && $certificate->pdf_path !== null;
    }

    public function revoke(User $user): bool
    {
        return $user->hasEffectiveRole(RoleCode::GiaoVu);
    }

    public function regeneratePdf(User $user, Certificate $certificate): bool
    {
        return $this->revoke($user) && $certificate->status === CertificateStatus::HieuLuc && $certificate->pdf_status === CertificatePdfStatus::Loi;
    }
}
