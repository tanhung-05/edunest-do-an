<?php

namespace App\Actions\M10;

use App\Events\M10\EnrollmentCompleted;
use App\Models\M10\Certificate;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Support\Str;

final class RecoverEnrollmentCompletions
{
    public function __construct(private EnsureCertificatePdfJob $pdf) {}

    public function recover(int $certificateId): void
    {
        // Hai phần phục hồi độc lập. Lỗi PDF vẫn để tác vụ sau thử lại phần đó.
        try {
            $this->pdf->handle($certificateId);
        } finally {
            $this->publish($certificateId);
        }
    }

    private function publish(int $certificateId): void
    {
        $claim = Tx::run(function () use ($certificateId) {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($certificateId);
            $at = now();
            if ($certificate->completion_event_published_at !== null || $certificate->completion_event_claim_until?->gt($at)) {
                return null;
            }
            $token = (string) Str::ulid();
            $certificate->forceFill([
                'completion_event_claim_token' => $token,
                'completion_event_claim_until' => $at->addMinutes(5),
            ])->save();

            return [$token, $certificate->enrollment_id, $certificate->completion_event_id];
        });
        if ($claim === null) {
            return;
        }

        [$token, $enrollmentId, $eventId] = $claim;
        TestBarrier::reach('completion.before-publish');
        EnrollmentCompleted::dispatch($enrollmentId, $eventId);
        TestBarrier::reach('completion.after-publish');

        Tx::run(function () use ($certificateId, $token): void {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($certificateId);
            if ($certificate->completion_event_claim_token === $token) {
                $certificate->forceFill([
                    'completion_event_published_at' => now(),
                    'completion_event_claim_token' => null,
                    'completion_event_claim_until' => null,
                ])->save();
            }
        });
    }
}
