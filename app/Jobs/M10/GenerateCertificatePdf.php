<?php

namespace App\Jobs\M10;

use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M10\Certificate;
use App\Support\M10\CertificatePdfRenderer;
use App\Support\TestBarrier;
use App\Support\Tx;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;

#[Tries(4)]
#[Backoff([60, 300, 900])]
final class GenerateCertificatePdf implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $certificateId, public readonly string $token) {}

    /** Phải khớp #[Tries] ở trên. */
    private const MAX_TRIES = 4;

    public function handle(CertificatePdfRenderer $renderer): void
    {
        try {
            $this->generate($renderer);
        } catch (\Throwable $e) {
            // Rà P4 #5: lần thử cuối chốt LOI ngay khi còn giữ dòng queue, trước khi Laravel xóa dòng rồi mới gọi failed().
            // Nếu để tới failed(), TV-17 có thể quét đúng khoảng giữa, thấy vé mất dòng queue và cấp vé thay thế.
            if ($this->job !== null && $this->attempts() >= self::MAX_TRIES) {
                $this->markFailed();
            }
            throw $e;
        }
    }

    private function generate(CertificatePdfRenderer $renderer): void
    {
        $certificate = Certificate::query()->findOrFail($this->certificateId);
        if (! $this->current($certificate)) {
            return;
        }

        $disk = Storage::disk('local');
        $path = 'certificates/'.$certificate->id.'/'.$this->token.'-'.Str::ulid().'.pdf';
        $published = false;
        try {
            if (! $disk->put($path, $renderer->render($certificate))) {
                throw new RuntimeException('Không ghi được tệp PDF tạm.');
            }
            TestBarrier::reach('certificate-pdf.rendered');
            $published = Tx::run(function () use ($path): bool {
                $certificate = Certificate::query()->lockForUpdate()->findOrFail($this->certificateId);
                if (! $this->current($certificate)) {
                    return false;
                }
                $certificate->forceFill([
                    'pdf_path' => $path,
                    'pdf_status' => CertificatePdfStatus::DaTao,
                    'pdf_generated_at' => now(),
                    'pdf_job_token' => null,
                ])->save();

                return true;
            });
        } finally {
            if (! $published) {
                $disk->delete($path);
            }
        }
    }

    public function failed(?\Throwable $failure): void
    {
        $this->markFailed();
    }

    private function markFailed(): void
    {
        Tx::run(function (): void {
            $certificate = Certificate::query()->lockForUpdate()->findOrFail($this->certificateId);
            if ($this->current($certificate)) {
                $certificate->forceFill(['pdf_status' => CertificatePdfStatus::Loi, 'pdf_job_token' => null])->save();
            }
        });
    }

    private function current(Certificate $certificate): bool
    {
        return $certificate->status === CertificateStatus::HieuLuc
            && $certificate->pdf_status !== CertificatePdfStatus::DaTao
            && $certificate->pdf_job_token === $this->token;
    }
}
