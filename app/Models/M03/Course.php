<?php

namespace App\Models\M03;

use App\Enums\M03\LearningMode;
use App\Enums\M03\SaleStatus;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Models\M04\QuestionBank;
use App\Models\M05\CourseVersion;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * L10 courses — khóa học (thông tin bán do Giáo vụ quản lý).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property int $category_id
 * @property int $level_id
 * @property LearningMode $learning_mode
 * @property int $price
 * @property ?int $original_price
 * @property ?int $access_months
 * @property ?int $extension_price
 * @property SaleStatus $sale_status
 * @property ?int $current_version_id
 * @property ?string $cover_path
 * @property ?int $prerequisite_course_id
 * @property ?string $rating_avg
 * @property int $rating_count
 * @property ?CarbonImmutable $last_published_at
 * @property int $created_by_id
 * @property ?CarbonImmutable $created_at
 * @property ?CarbonImmutable $updated_at
 */
class Course extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'learning_mode' => LearningMode::class,
            'sale_status' => SaleStatus::class,
            'price' => 'integer',
            'original_price' => 'integer',
            'extension_price' => 'integer',
            'access_months' => 'integer',
            'last_published_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class)->withTrashed();
    }

    /** @return BelongsTo<Level, $this> */
    public function level(): BelongsTo
    {
        return $this->belongsTo(Level::class)->withTrashed();
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'course_tag');
    }

    /** @return HasMany<CourseVersion, $this> */
    public function versions(): HasMany
    {
        return $this->hasMany(CourseVersion::class);
    }

    /** @return BelongsTo<CourseVersion, $this> */
    public function currentVersion(): BelongsTo
    {
        return $this->belongsTo(CourseVersion::class, 'current_version_id');
    }

    /**
     * Bản nháp mở (NHAP, TRA_LAI, CHO_DUYET, DA_DUYET) — tối đa một.
     *
     * @return HasOne<CourseVersion, $this>
     */
    public function openVersion(): HasOne
    {
        return $this->hasOne(CourseVersion::class)->whereNotNull('open_draft_key');
    }

    /** @return HasMany<CourseAuthor, $this> */
    public function authors(): HasMany
    {
        return $this->hasMany(CourseAuthor::class);
    }

    /** @return HasOne<CourseAuthor, $this> */
    public function currentAuthor(): HasOne
    {
        return $this->hasOne(CourseAuthor::class)->whereNotNull('current_key');
    }

    /** @return HasMany<QuestionBank, $this> */
    public function questionBanks(): HasMany
    {
        return $this->hasMany(QuestionBank::class);
    }

    /** @return BelongsTo<User, $this> */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }
}
