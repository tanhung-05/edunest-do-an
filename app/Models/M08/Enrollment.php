<?php

namespace App\Models\M08;

use App\Enums\M08\EnrollmentSource;
use App\Enums\M08\LearningStatus;
use App\Models\M03\Course;
use App\Models\M05\CourseVersion;
use App\Models\M07\OrderItem;
use App\Models\M09\LessonProgress;
use App\Models\M10\Certificate;
use App\Models\M10\QuizAttempt;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * L32 enrollments — ghi danh (03 §9). Hiệu lực suy ra qua EnrollmentValidityChecker.
 *
 * @property int $id
 * @property int $user_id
 * @property int $course_id
 * @property int $course_version_id
 * @property LearningStatus $learning_status
 * @property ?CarbonImmutable $het_han_luc
 * @property ?CarbonImmutable $revoked_at
 * @property ?int $revoked_by_id
 * @property ?string $revoked_reason
 * @property EnrollmentSource $source
 * @property ?int $order_item_id
 * @property ?int $activation_code_id
 * @property CarbonImmutable $granted_at
 * @property ?CarbonImmutable $started_at
 * @property ?CarbonImmutable $completed_at
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Enrollment extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'learning_status' => LearningStatus::class,
            'source' => EnrollmentSource::class,
            'het_han_luc' => 'datetime',
            'revoked_at' => 'datetime',
            'granted_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Course, $this> */
    public function course(): BelongsTo
    {
        return $this->belongsTo(Course::class);
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function version(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'course_version_id');
    }

    /** @return BelongsTo<OrderItem, $this> */
    public function orderItem(): BelongsTo
    {
        return $this->belongsTo(OrderItem::class);
    }

    /** @return HasMany<LessonProgress, $this> */
    public function lessonProgress(): HasMany
    {
        return $this->hasMany(LessonProgress::class);
    }

    /** @return HasMany<QuizAttempt, $this> */
    public function quizAttempts(): HasMany
    {
        return $this->hasMany(QuizAttempt::class);
    }

    /** @return HasOne<Certificate, $this> */
    public function certificate(): HasOne
    {
        return $this->hasOne(Certificate::class);
    }
}
