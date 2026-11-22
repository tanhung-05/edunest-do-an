<?php

namespace App\Models\M24;

use App\Models\M03\Course;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * bundles — combo nhiều khóa, giá chung do Giáo vụ đặt (BR-TT-68/85, CR-96/103/162).
 *
 * @property int $id
 * @property string $title
 * @property string $slug
 * @property string|null $description
 * @property int $price
 * @property bool $deduct_owned
 * @property bool $accepts_orders
 * @property int $created_by_id
 * @property int $updated_by_id
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 */
class Bundle extends Model
{
    protected $table = 'bundles';

    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'deduct_owned' => 'boolean',
            'accepts_orders' => 'boolean',
            'created_by_id' => 'integer',
            'updated_by_id' => 'integer',
        ];
    }

    /** @return BelongsToMany<Course, $this> */
    public function courses(): BelongsToMany
    {
        return $this->belongsToMany(Course::class, 'bundle_course')->withPivot('position')->orderByPivot('position');
    }
}
