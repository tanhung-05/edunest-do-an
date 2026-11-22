<?php

namespace App\Actions\M13;

use App\Enums\M13\ActivationCodeStatus;
use App\Models\M03\Course;
use App\Models\M13\ActivationCode;
use App\Models\M13\ActivationCodeBatch;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\M07\OrderLocks;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;

/** QT-14 b1–3, b11–13, A1, A11 (NH-13, NH-15; MK-1, MK-3, MK-4). */
final class ActivationCodes
{
    public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public function createBatch(User $actor, Course $course, int $quantity, CarbonImmutable $redeemUntil, ?string $note): ActivationCodeBatch
    {
        if ($quantity < 1 || $quantity > 1000) {
            throw new DomainRejection('Số lượng mã từ 1 đến 1.000.', 'quantity');
        }
        for ($attempt = 1; ; $attempt++) {
            try {
                return Tx::run(function () use ($actor, $course, $quantity, $redeemUntil, $note) {
                    $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
                    OrderLocks::academic($locked);
                    $course = Course::query()->whereKey($course->id)->lockForUpdate()->firstOrFail();
                    // CR-130: hạn đổi phải lớn hơn giờ máy chủ lúc ghi.
                    if ($redeemUntil->lte(now())) {
                        throw new DomainRejection('Hạn đổi mã phải sau thời điểm hiện tại.', 'redeem_until');
                    }
                    $batch = ActivationCodeBatch::query()->create([
                        'course_id' => $course->id, 'quantity' => $quantity, 'redeem_until' => $redeemUntil,
                        'access_months' => $course->access_months, 'note' => $note, 'created_by_id' => $actor->id,
                    ]);
                    $now = now();
                    $codes = [];
                    while (count($codes) < $quantity) {
                        $codes[self::generate()] = true;
                    }
                    foreach (array_chunk(array_keys($codes), 500) as $chunk) {
                        ActivationCode::query()->insert(array_map(fn (string $code) => [
                            'batch_id' => $batch->id, 'code' => $code, 'status' => ActivationCodeStatus::ChuaDung->value,
                            'created_at' => $now, 'updated_at' => $now,
                        ], $chunk));
                    }
                    Audit::log('ACTIVATION_BATCH_CREATE', $actor, $batch, null, ['course_id' => $course->id, 'quantity' => $quantity]);

                    return $batch;
                });
            } catch (QueryException $e) {
                if ($attempt >= 3 || Tx::code($e) !== 1062) {
                    throw $e;
                }
            }
        }
    }

    /**
     * MK-3: vô hiệu một mã hoặc mọi mã `CHUA_DUNG` của lô; ghi danh đã tạo không đổi. Trả số mã đã vô hiệu.
     */
    public function void(User $actor, ActivationCodeBatch $batch, ?ActivationCode $code, string $reason, bool $confirmed): int
    {
        $reason = trim($reason);
        if ($reason === '' || mb_strlen($reason) > 500) {
            throw new DomainRejection('Nhập lý do vô hiệu từ 1 đến 500 ký tự.', 'reason');
        }
        if (! $confirmed) {
            throw new DomainRejection('Hãy xác nhận vô hiệu.', 'confirmed');
        }

        return Tx::run(function () use ($actor, $batch, $code, $reason) {
            $locked = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            OrderLocks::academic($locked);
            $now = now();
            $codes = ActivationCode::query()->where('batch_id', $batch->id)
                ->when($code !== null, fn ($q) => $q->whereKey($code->id))
                ->where('status', ActivationCodeStatus::ChuaDung)->orderBy('id')->lockForUpdate()->get();
            if ($code !== null && $codes->isEmpty()) {
                throw new DomainRejection('Chỉ vô hiệu được mã chưa dùng.');
            }
            foreach ($codes as $row) {
                $row->update(['status' => ActivationCodeStatus::DaVoHieu, 'voided_at' => $now, 'voided_by_id' => $actor->id, 'void_reason' => $reason]);
            }
            Audit::log($code === null ? 'ACTIVATION_BATCH_VOID' : 'ACTIVATION_CODE_VOID', $actor, $code ?? $batch,
                ['status' => 'CHUA_DUNG'], ['status' => 'DA_VO_HIEU', 'count' => $codes->count()], $reason);

            return $codes->count();
        });
    }

    /** TV-18 (MK-4): mã `CHUA_DUNG` đã quá hạn đổi → `HET_HAN`. */
    public function expireOverdue(): int
    {
        return ActivationCode::query()->where('status', ActivationCodeStatus::ChuaDung)
            ->whereHas('batch', fn ($q) => $q->where('redeem_until', '<', now()))
            ->update(['status' => ActivationCodeStatus::HetHan, 'updated_at' => now()]);
    }

    /**
     * CR-132: thống kê bốn nhóm; mã chưa dùng đã quá hạn tính vào Hết hạn.
     *
     * @return array{used: int, remaining: int, expired: int, voided: int}
     */
    public static function stats(ActivationCodeBatch $batch): array
    {
        $now = now();
        $overdue = $now->gt($batch->redeem_until);
        $counts = ActivationCode::query()->where('batch_id', $batch->id)->selectRaw('status, count(*) as n')
            ->groupBy('status')->pluck('n', 'status')->map(fn ($n) => (int) $n);
        $unused = $counts->get(ActivationCodeStatus::ChuaDung->value, 0);

        return [
            'used' => $counts->get(ActivationCodeStatus::DaDung->value, 0),
            'remaining' => $overdue ? 0 : $unused,
            'expired' => $counts->get(ActivationCodeStatus::HetHan->value, 0) + ($overdue ? $unused : 0),
            'voided' => $counts->get(ActivationCodeStatus::DaVoHieu->value, 0),
        ];
    }

    public static function generate(): string
    {
        $code = '';
        for ($i = 0; $i < 12; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
