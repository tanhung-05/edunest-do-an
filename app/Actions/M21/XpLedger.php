<?php

namespace App\Actions\M21;

use App\Enums\M20\SessionStatus;
use App\Enums\M21\BadgeRule;
use App\Enums\M21\XpRule;
use App\Enums\M21\XpSourceType;
use App\Models\M21\Badge;
use App\Models\M21\StudentStat;
use App\Models\M21\XpSource;
use App\Models\User;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * QT-23: ghi nhận nguồn XP đã chốt và xử lý một lần (BR-HT-02, BR-XP-49/50/73). Mọi khoản, ngày học, mốc chuỗi,
 * số câu đúng và huy hiệu của một nguồn ghi trong cùng một transaction dưới khóa dòng `users` của học viên;
 * gián đoạn thì không có phần nào được ghi và TV-14 xử lý lại theo `processed_at`.
 */
final class XpLedger
{
    /** Mốc chuỗi → XP (BR-XP-41), không nhân hệ số. */
    public const MILESTONES = [3 => 10, 7 => 20, 14 => 40, 30 => 80];

    public const TZ = 'Asia/Ho_Chi_Minh';

    /**
     * Lưu nguồn bền vững (UNIQUE loại + mã nguồn) rồi xử lý sau khi transaction hiện hành commit.
     *
     * @param  array<string, mixed>  $payload
     */
    public function record(XpSourceType $type, string $key, int $userId, CarbonImmutable $occurredAt, array $payload): void
    {
        $now = now();
        DB::table('xp_sources')->insertOrIgnore([
            'user_id' => $userId, 'type' => $type->value, 'source_key' => $key, 'occurred_at' => $occurredAt->utc()->format('Y-m-d H:i:s.v'),
            'payload' => json_encode($payload, JSON_THROW_ON_ERROR), 'attempts' => 0, 'created_at' => $now, 'updated_at' => $now,
        ]);
        $id = (int) XpSource::query()->where('type', $type)->where('source_key', $key)->value('id');
        DB::afterCommit(fn () => $this->processSafely($id));
    }

    public function processSafely(int $sourceId): void
    {
        try {
            $this->process($sourceId);
        } catch (Throwable $e) {
            report($e);
            XpSource::query()->whereKey($sourceId)->increment('attempts');
        }
    }

    public function process(int $sourceId): void
    {
        Tx::run(function () use ($sourceId): void {
            $userId = XpSource::query()->whereKey($sourceId)->value('user_id');
            if ($userId === null) {
                return;
            }
            User::query()->whereKey($userId)->lockForUpdate()->first();
            $source = XpSource::query()->whereKey($sourceId)->lockForUpdate()->firstOrFail();
            if ($source->processed_at !== null) {
                return;
            }
            DB::table('student_stats')->insertOrIgnore(['user_id' => $userId, 'created_at' => now(), 'updated_at' => now()]);
            $stats = StudentStat::query()->whereKey($userId)->lockForUpdate()->firstOrFail();

            $added = 0;
            foreach (self::entries($source) as [$rule, $amount, $key]) {
                $added += $this->credit($source, $rule, $amount, $key);
            }
            $run = 0;
            if (self::keepsStreak($source)) {
                [$added2, $run] = $this->learningDay($source);
                $added += $added2;
            }
            $stats->total_xp += $added;
            $stats->level = max($stats->level, self::levelFor($stats->total_xp));
            $stats->longest_streak = max($stats->longest_streak, $run);
            if ($source->type === XpSourceType::PhienLuyenTap) {
                // Tính lại từ các nguồn đã chốt (kể cả nguồn này) nên xử lý lặp không cộng trùng (E40).
                $stats->correct_answers = (int) XpSource::query()->where('user_id', $userId)->where('type', XpSourceType::PhienLuyenTap)
                    ->where(fn ($q) => $q->whereNotNull('processed_at')->orWhereKey($source->id))
                    ->whereIn(DB::raw("JSON_UNQUOTE(JSON_EXTRACT(payload, '$.status'))"), [SessionStatus::HoanThanh->value, SessionStatus::HetMang->value])
                    ->sum(DB::raw("CAST(JSON_EXTRACT(payload, '$.correct') AS UNSIGNED)"));
            }
            $stats->save();
            $this->badges($source, $stats);
            $source->forceFill(['processed_at' => now(), 'attempts' => $source->attempts + 1])->save();
        });
    }

    /**
     * Khoản XP do nguồn sinh ra: [quy tắc, số XP, khóa duy nhất].
     *
     * @return list<array{0: XpRule, 1: int, 2: string}>
     */
    public static function entries(XpSource $source): array
    {
        $p = $source->payload;
        $factor = (int) ($p['factor_pct'] ?? 100);
        $u = $source->user_id;

        return match ($source->type) {
            XpSourceType::PhienLuyenTap => self::practiceEntries($source->source_key, $p, $factor),
            XpSourceType::PhienGame => ($p['status'] ?? null) === SessionStatus::HoanThanh->value
                ? [[XpRule::ThuongGame, self::scale(self::sessionBonus((int) $p['card_count']), $factor), "game:{$source->source_key}:thuong"]]
                : [],
            XpSourceType::HoanThanhBai => [[XpRule::Bai, 2, "bai:{$u}:{$p['course_id']}:{$p['lesson_key']}"]],
            XpSourceType::HoanThanhKhoa => [[XpRule::Khoa, 50, "khoa:{$u}:{$p['course_id']}"]],
            XpSourceType::KyThi => [],
        };
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array{0: XpRule, 1: int, 2: string}>
     */
    private static function practiceEntries(string $sessionId, array $p, int $factor): array
    {
        $status = $p['status'] ?? null;
        if (! in_array($status, [SessionStatus::HoanThanh->value, SessionStatus::HetMang->value], true)) {
            return [];
        }
        $entries = [];
        foreach ((array) ($p['questions'] ?? []) as $q) {
            $amount = self::scale(self::questionBase((string) $q['difficulty'], isset($q['correct_on_try']) ? (int) $q['correct_on_try'] : null, (bool) $q['hint_used']), $factor);
            if ($amount > 0) {
                $entries[] = [XpRule::Cau, $amount, "phien:{$sessionId}:cau:{$q['position']}"];
            }
        }
        if ($status === SessionStatus::HoanThanh->value) {
            $entries[] = [XpRule::ThuongPhien, self::scale(self::sessionBonus((int) $p['question_count']), $factor), "phien:{$sessionId}:thuong"];
        }

        return $entries;
    }

    /** XP gốc câu (BR-XP-20..23, CR-84): dùng gợi ý luôn lấy dòng 4/5/6. */
    public static function questionBase(string $difficulty, ?int $correctOnTry, bool $hintUsed): int
    {
        if ($correctOnTry === null) {
            return 0;
        }
        $row = match (true) {
            $hintUsed => [4, 5, 6],
            $correctOnTry === 1 => [8, 10, 12],
            default => [6, 8, 10],
        };

        return match ($difficulty) {
            'DE' => $row[0],
            'KHO' => $row[2],
            default => $row[1],
        };
    }

    /** Thưởng phiên/game gốc = min(10, 2 × ⌈n/5⌉) (BR-XP-24/67). */
    public static function sessionBonus(int $count): int
    {
        return min(10, 2 * intdiv($count + 4, 5));
    }

    /** Nhân hệ số (phần trăm) và làm tròn nửa lên (BR-XP-30). */
    public static function scale(int $base, int $factorPct): int
    {
        return intdiv($base * $factorPct + 50, 100);
    }

    /** Hệ số theo cấp, đơn vị phần trăm (BR-XP-28): max(20, 100 − 10 × ⌊(cấp − 1)/5⌋). */
    public static function factorPct(int $level): int
    {
        return max(20, 100 - 10 * intdiv(max(1, $level) - 1, 5));
    }

    /** Ngưỡng tổng XP của cấp L = 100(L−1) + 10(L−1)(L−2). */
    public static function threshold(int $level): int
    {
        return 100 * ($level - 1) + 10 * ($level - 1) * ($level - 2);
    }

    public static function levelFor(int $total): int
    {
        $level = 1;
        while (self::threshold($level + 1) <= $total) {
            $level++;
        }

        return $level;
    }

    /** Hệ số chốt lúc bắt đầu phiên (BR-XP-29), đọc dưới khóa học viên của người gọi. */
    public static function currentFactorPct(int $userId): int
    {
        return self::factorPct((int) (StudentStat::query()->whereKey($userId)->value('level') ?? 1));
    }

    /** BR-XP-38 / CR-87: chỉ phiên HOAN_THANH và hoàn thành bài/khóa giữ chuỗi. */
    public static function keepsStreak(XpSource $source): bool
    {
        return match ($source->type) {
            XpSourceType::PhienLuyenTap => ($source->payload['status'] ?? null) === SessionStatus::HoanThanh->value,
            XpSourceType::HoanThanhBai, XpSourceType::HoanThanhKhoa => true,
            default => false,
        };
    }

    public static function dayOf(CarbonImmutable $at): string
    {
        return $at->setTimezone(self::TZ)->toDateString();
    }

    private function credit(XpSource $source, XpRule $rule, int $amount, string $key, ?string $streakStart = null): int
    {
        $inserted = DB::table('xp_transactions')->insertOrIgnore([
            'user_id' => $source->user_id, 'xp_source_id' => $source->id, 'rule' => $rule->value, 'amount' => $amount, 'unique_key' => $key,
            'streak_start' => $streakStart, 'occurred_at' => $source->occurred_at->utc()->format('Y-m-d H:i:s.v'), 'created_at' => now(),
        ]);

        return $inserted > 0 ? $amount : 0;
    }

    /**
     * Ghi ngày học (giờ VN) và phát mốc chuỗi còn thiếu của đoạn liên tục chứa ngày đó (BR-XP-72, CR-158).
     *
     * @return array{0: int, 1: int} [XP thêm, độ dài đoạn]
     */
    private function learningDay(XpSource $source): array
    {
        $day = self::dayOf($source->occurred_at);
        DB::table('learning_days')->insertOrIgnore(['user_id' => $source->user_id, 'day' => $day, 'xp_source_id' => $source->id]);
        [$start, $end, $length] = self::runAround($source->user_id, $day);
        $awarded = DB::table('xp_transactions')->where('user_id', $source->user_id)
            ->whereIn('rule', [XpRule::Chuoi3->value, XpRule::Chuoi7->value, XpRule::Chuoi14->value, XpRule::Chuoi30->value])
            ->whereBetween('streak_start', [$start, $end])->pluck('rule')->all();
        $added = 0;
        foreach (self::MILESTONES as $days => $xp) {
            $rule = XpRule::from('CHUOI_'.$days);
            if ($length >= $days && ! in_array($rule->value, $awarded, true)) {
                $added += $this->credit($source, $rule, $xp, "chuoi:{$source->user_id}:{$start}:{$days}", $start);
            }
        }

        return [$added, $length];
    }

    /**
     * Đoạn ngày học liên tục chứa `$day`.
     *
     * @return array{0: string, 1: string, 2: int}
     */
    public static function runAround(int $userId, string $day): array
    {
        $days = array_flip(DB::table('learning_days')->where('user_id', $userId)->pluck('day')->map(fn ($d) => substr((string) $d, 0, 10))->all());
        $start = CarbonImmutable::parse($day);
        while (isset($days[$start->subDay()->toDateString()])) {
            $start = $start->subDay();
        }
        $end = CarbonImmutable::parse($day);
        while (isset($days[$end->addDay()->toDateString()])) {
            $end = $end->addDay();
        }

        return [$start->toDateString(), $end->toDateString(), (int) $start->diffInDays($end) + 1];
    }

    /** Chuỗi hiện tại: đoạn kết thúc hôm nay hoặc hôm qua theo giờ VN (A2), ngược lại 0. */
    public static function currentStreak(int $userId, ?CarbonImmutable $now = null): int
    {
        $today = ($now ?? CarbonImmutable::now())->setTimezone(self::TZ)->startOfDay();
        $last = DB::table('learning_days')->where('user_id', $userId)->where('day', '<=', $today->toDateString())->max('day');
        if ($last === null || substr((string) $last, 0, 10) < $today->subDay()->toDateString()) {
            return 0;
        }

        return self::runAround($userId, substr((string) $last, 0, 10))[2];
    }

    private function badges(XpSource $source, StudentStat $stats): void
    {
        $owned = DB::table('badge_user')->where('user_id', $source->user_id)->pluck('badge_id')->all();
        foreach (Badge::query()->where('is_active', true)->whereNotIn('id', $owned)->get() as $badge) {
            $earned = match ($badge->rule) {
                BadgeRule::KhoaHoanThanh => DB::table('xp_transactions')->where('user_id', $source->user_id)->where('rule', XpRule::Khoa->value)->count() >= $badge->threshold,
                BadgeRule::ChuoiNgay => $stats->longest_streak >= $badge->threshold,
                BadgeRule::CauDung => $stats->correct_answers >= $badge->threshold,
                BadgeRule::TopKyThi => $source->type === XpSourceType::KyThi && isset($source->payload['rank']) && (int) $source->payload['rank'] <= $badge->threshold,
            };
            if ($earned) {
                DB::table('badge_user')->insertOrIgnore(['badge_id' => $badge->id, 'user_id' => $source->user_id, 'xp_source_id' => $source->id,
                    'awarded_at' => now()->format('Y-m-d H:i:s.v')]);
            }
        }
    }
}
