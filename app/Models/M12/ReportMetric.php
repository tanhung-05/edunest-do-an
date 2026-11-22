<?php

namespace App\Models\M12;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property string $metric
 * @property string $scope_type
 * @property int $scope_id
 * @property CarbonImmutable $metric_date
 * @property array<string, mixed> $value
 * @property CarbonImmutable $computed_at
 */
class ReportMetric extends Model
{
    public $timestamps = false;

    protected $table = 'report_metrics';

    protected $guarded = ['id'];

    protected $attributes = ['scope_id' => 0];

    protected function casts(): array
    {
        return [
            'scope_id' => 'integer',
            'metric_date' => 'immutable_date',
            'value' => 'array',
            'computed_at' => 'immutable_datetime',
        ];
    }
}
