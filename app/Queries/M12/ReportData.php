<?php

namespace App\Queries\M12;

use App\Enums\M07\OrderStatus;
use App\Enums\M08\EnrollmentValidity;
use App\Models\M07\Order;
use App\Models\M08\Enrollment;
use App\Models\M12\ReportMetric;
use App\Support\M08\EnrollmentValidityChecker;
use Closure;
use Throwable;

final class ReportData
{
    /** @return array{value: array<string, mixed>, computed_at: string}|null */
    public function latest(string $metric, string $scope, int $id = 0): ?array
    {
        $row = ReportMetric::query()->where('metric', $metric)->where('scope_type', $scope)->where('scope_id', $id)
            ->orderByDesc('metric_date')->first();

        return $row === null ? null : ['value' => $row->value, 'computed_at' => $row->computed_at->toISOString()];
    }

    /** @return array<string, int> */
    public function orders(): array
    {
        $rows = Order::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->toBase()->get();

        $counts = array_fill_keys(array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases()), 0);

        return array_replace($counts, $rows->mapWithKeys(fn ($row) => [(string) $row->status => (int) $row->total])->all());
    }

    /** @return array<string, int> */
    public function enrollments(): array
    {
        $counts = array_fill_keys(array_map(fn (EnrollmentValidity $validity) => $validity->value, EnrollmentValidity::cases()), 0);
        $at = now();
        foreach (Enrollment::query()->lazyById() as $enrollment) {
            $counts[EnrollmentValidityChecker::for($enrollment, $at)->value]++;
        }

        return $counts;
    }

    /** @return array{state: string, data: mixed} */
    public function section(Closure $read): array
    {
        try {
            $data = $read();

            return ['state' => $data === null ? 'empty' : 'ready', 'data' => $data];
        } catch (Throwable $failure) {
            report($failure);

            return ['state' => 'error', 'data' => null];
        }
    }
}
