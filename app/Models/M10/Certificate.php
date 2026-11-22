<?php

namespace App\Models\M10;

use App\Enums\M10\CertificatePdfStatus;
use App\Enums\M10\CertificateStatus;
use App\Models\M08\Enrollment;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $enrollment_id
 * @property string $certificate_number
 * @property string $holder_name
 * @property string $course_title
 * @property CarbonImmutable $issued_on
 * @property CertificateStatus $status
 * @property CarbonImmutable|null $revoked_at
 * @property int|null $revoked_by_id
 * @property string|null $revoked_reason
 * @property CertificatePdfStatus $pdf_status
 * @property string|null $pdf_path
 * @property CarbonImmutable|null $pdf_generated_at
 * @property string|null $pdf_job_token
 * @property string $completion_event_id
 * @property CarbonImmutable|null $completion_event_published_at
 * @property string|null $completion_event_claim_token
 * @property CarbonImmutable|null $completion_event_claim_until
 */
class Certificate extends Model
{
    public $timestamps = false;

    protected $table = 'certificates';

    protected $guarded = ['id'];

    protected $attributes = ['status' => 'HIEU_LUC', 'pdf_status' => 'CHO_TAO'];

    protected function casts(): array
    {
        return [
            'enrollment_id' => 'integer',
            'issued_on' => 'immutable_date',
            'status' => CertificateStatus::class,
            'revoked_at' => 'immutable_datetime',
            'revoked_by_id' => 'integer',
            'pdf_status' => CertificatePdfStatus::class,
            'pdf_generated_at' => 'immutable_datetime',
            'completion_event_published_at' => 'immutable_datetime',
            'completion_event_claim_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<Enrollment, $this> */
    public function enrollment(): BelongsTo
    {
        return $this->belongsTo(Enrollment::class);
    }
}
