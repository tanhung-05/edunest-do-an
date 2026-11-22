<?php

namespace App\Console\Commands;

use App\Actions\M10\CompleteEnrollment;
use App\Actions\M10\RecoverEnrollmentCompletions as Recovery;
use App\Enums\M08\LearningStatus;
use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M08\Enrollment;
use App\Models\M10\Certificate;
use Illuminate\Console\Command;
use Throwable;

final class RecoverEnrollmentCompletions extends Command
{
    protected $signature = 'learning:recover-completions';

    protected $description = 'Phục hồi chứng nhận, sự kiện hoàn thành và yêu cầu tạo PDF';

    public function handle(CompleteEnrollment $complete, Recovery $recovery): int
    {
        $failed = false;
        foreach (Enrollment::query()->where('learning_status', LearningStatus::HoanThanh)->whereDoesntHave('certificate')->lazyById() as $enrollment) {
            try {
                $complete->handle($enrollment);
            } catch (Throwable $failure) {
                $failed = true;
                report($failure);
            }
        }

        $pending = Certificate::query()
            ->where(fn ($q) => $q->whereNull('completion_event_published_at')
                ->where(fn ($q) => $q->whereNull('completion_event_claim_until')->orWhere('completion_event_claim_until', '<=', now())))
            ->orWhere(fn ($q) => $q->where('status', CertificateStatus::HieuLuc)
                ->where(fn ($q) => $q->where('pdf_status', CertificatePdfStatus::ChoTao)
                    ->orWhere(fn ($q) => $q->where('pdf_status', CertificatePdfStatus::Loi)->whereNotNull('pdf_job_token'))));
        foreach ($pending->lazyById() as $certificate) {
            try {
                $recovery->recover($certificate->id);
            } catch (Throwable $failure) {
                $failed = true;
                report($failure);
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }
}
