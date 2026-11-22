<?php

namespace Database\Factories\M12;

use App\Models\M12\ReportMetric;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ReportMetric> */
final class ReportMetricFactory extends Factory
{
    protected $model = ReportMetric::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return [
            'metric' => 'revenue', 'scope_type' => 'TOAN_HE_THONG', 'scope_id' => 0,
            'metric_date' => now()->setTimezone('Asia/Ho_Chi_Minh')->toDateString(),
            'value' => ['total' => 499000, 'label' => 'Mô phỏng'], 'computed_at' => now(),
        ];
    }
}
