<?php

namespace App\Actions\M10;

use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Jobs\M10\GenerateCertificatePdf;
use App\Models\M10\Certificate;
use App\Support\Tx;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

final class EnsureCertificatePdfJob
{
    public function handle(int $certificateId): void
    {
        Tx::run(function () use ($certificateId): void {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($certificateId);
            if ($certificate->status === CertificateStatus::HieuLuc
                && ($certificate->pdf_status === CertificatePdfStatus::ChoTao
                    || ($certificate->pdf_status === CertificatePdfStatus::Loi && $certificate->pdf_job_token !== null))) {
                $this->scheduleUnderLock($certificate);
            }
        });
    }

    public function scheduleUnderLock(Certificate $certificate): void
    {
        $token = $certificate->pdf_job_token;
        if ($token !== null && DB::table(config()->string('queue.connections.database.table'))
            ->where('queue', 'default')->where('payload->uuid', $token)->exists()) {
            return;
        }

        $token = (string) Str::uuid();
        $certificate->pdf_job_token = $token;
        $certificate->save();
        Queue::connection('database')->push((new GenerateCertificatePdf($certificate->id, $token))->beforeCommit(), '', 'default');
    }
}
