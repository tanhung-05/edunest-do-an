<?php

namespace Database\Factories\M10;

use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M08\Enrollment;
use App\Models\M10\Certificate;
use App\Models\User;
use Database\Factories\M09\LearningEnrollmentFactory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/** @extends Factory<Certificate> */
final class CertificateFactory extends Factory
{
    protected $model = Certificate::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'enrollment_id' => LearningEnrollmentFactory::new()->completed(),
            'certificate_number' => now()->format('Y').'-'.fake()->unique()->regexify('[23456789ABCDEFGHJKLMNPQRSTUVWXYZ]{8}'),
            'holder_name' => fn (array $a) => Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail()->user()->firstOrFail()->name,
            'course_title' => fn (array $a) => Enrollment::query()->whereKey($a['enrollment_id'])->firstOrFail()->course()->firstOrFail()->title,
            'issued_on' => now()->setTimezone('Asia/Ho_Chi_Minh')->toDateString(),
            'completion_event_id' => fn (array $a) => 'HT-'.$a['enrollment_id'],
            'status' => CertificateStatus::HieuLuc, 'pdf_status' => CertificatePdfStatus::ChoTao,
        ];
    }

    public function pendingPdf(): static
    {
        return $this->state(fn () => ['pdf_job_token' => (string) Str::uuid()]);
    }

    public function readyPdf(string $path): static
    {
        return $this->state(fn () => ['pdf_status' => CertificatePdfStatus::DaTao, 'pdf_path' => $path,
            'pdf_generated_at' => now(), 'pdf_job_token' => null]);
    }

    public function failedPdf(): static
    {
        return $this->state(['pdf_status' => CertificatePdfStatus::Loi, 'pdf_job_token' => null]);
    }

    public function published(): static
    {
        return $this->state(fn () => ['completion_event_published_at' => now()]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => ['status' => CertificateStatus::DaThuHoi, 'revoked_at' => now(),
            'revoked_by_id' => User::factory(), 'revoked_reason' => 'Chứng nhận kiểm thử bị thu hồi.', 'pdf_job_token' => null]);
    }
}
