<?php

namespace App\Models\M18;

use App\Enums\M18\RegistrationStatus;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * N20 exam_registrations — đăng ký thi (CR-145).
 *
 * @property int $id
 * @property int $exam_event_id
 * @property int $user_id
 * @property RegistrationStatus $status
 * @property ?CarbonImmutable $created_at
 */
class ExamRegistration extends Model
{
    protected $table = 'exam_registrations';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'status' => RegistrationStatus::class,
            'exam_event_id' => 'integer',
            'user_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
