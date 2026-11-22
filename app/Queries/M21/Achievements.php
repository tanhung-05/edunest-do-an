<?php

namespace App\Queries\M21;

use App\Actions\M21\XpLedger;
use App\Enums\M01\RoleCode;
use App\Enums\M21\XpRule;
use App\Enums\M21\XpSourceType;
use App\Models\M21\Badge;
use App\Models\M21\StudentStat;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** MH-HV-18: XP, cấp, chuỗi ngày, huy hiệu, sổ XP và bảng xếp hạng tuần/tháng theo giờ VN (QT-23 bước 6–7, CR-157). */
final class Achievements
{
    /** @return array<string, mixed> */
    public static function summary(User $user, ?CarbonImmutable $now = null): array
    {
        $now ??= CarbonImmutable::now();
        $stats = StudentStat::query()->find($user->id);
        $total = $stats->total_xp ?? 0;
        $level = $stats->level ?? 1;
        $floor = XpLedger::threshold($level);
        $next = XpLedger::threshold($level + 1);
        $today = $now->setTimezone(XpLedger::TZ)->startOfDay();
        $days = DB::table('learning_days')->where('user_id', $user->id)->where('day', '>=', $today->subDays(34)->toDateString())
            ->pluck('day')->map(fn ($d) => substr((string) $d, 0, 10))->all();
        $calendar = [];
        for ($i = 34; $i >= 0; $i--) {
            $d = $today->subDays($i)->toDateString();
            $calendar[] = ['day' => $d, 'learned' => in_array($d, $days, true)];
        }
        $awarded = DB::table('badge_user')->where('user_id', $user->id)->pluck('awarded_at', 'badge_id');

        return [
            'total_xp' => $total, 'level' => $level, 'level_floor' => $floor, 'next_level_at' => $next,
            'factor' => XpLedger::factorPct($level) / 100,
            'current_streak' => XpLedger::currentStreak($user->id, $now), 'longest_streak' => $stats->longest_streak ?? 0,
            'correct_answers' => $stats->correct_answers ?? 0, 'learned_today' => in_array($today->toDateString(), $days, true),
            'calendar' => $calendar,
            'badges' => Badge::query()->where(fn ($q) => $q->where('is_active', true)->orWhereIn('id', $awarded->keys()))->orderBy('id')->get()
                ->map(fn (Badge $b) => ['id' => $b->id, 'code' => $b->code, 'name' => $b->name, 'description' => $b->description,
                    'awarded_at' => isset($awarded[$b->id]) ? CarbonImmutable::parse((string) $awarded[$b->id], 'UTC')->toIso8601String() : null])->all(),
        ];
    }

    /** @return list<array<string, mixed>> */
    public static function ledger(User $user, int $limit = 30): array
    {
        return array_values(DB::table('xp_transactions')->join('xp_sources', 'xp_sources.id', '=', 'xp_transactions.xp_source_id')
            ->where('xp_transactions.user_id', $user->id)->orderByDesc('xp_transactions.occurred_at')->orderByDesc('xp_transactions.id')->limit($limit)
            ->get(['xp_transactions.id', 'xp_transactions.rule', 'xp_transactions.amount', 'xp_transactions.occurred_at', 'xp_sources.type'])
            ->map(fn ($r) => ['id' => (int) $r->id, 'rule' => XpRule::from($r->rule)->label(), 'source' => XpSourceType::from($r->type)->label(),
                'amount' => (int) $r->amount, 'occurred_at' => CarbonImmutable::parse((string) $r->occurred_at, 'UTC')->toIso8601String()])->all());
    }

    /**
     * Bảng xếp hạng XP (BR-XP-48, CR-157): tuần thứ Hai–Chủ nhật hoặc tháng lịch, giờ VN; chỉ vai Học viên; hạng 1, 2, 2, 4.
     *
     * @return array{period: string, from: string, to: string, rows: list<array<string, mixed>>, me: ?array<string, mixed>}
     */
    public static function leaderboard(User $viewer, string $period, ?CarbonImmutable $now = null, int $limit = 50): array
    {
        $now = ($now ?? CarbonImmutable::now())->setTimezone(XpLedger::TZ);
        $start = $period === 'month' ? $now->startOfMonth() : $now->startOfWeek(CarbonImmutable::MONDAY);
        $end = $period === 'month' ? $start->addMonth() : $start->addWeek();
        $students = DB::table('role_user')->join('roles', 'roles.id', '=', 'role_user.role_id')->where('roles.code', RoleCode::HocVien->value)->select('role_user.user_id');
        $totals = DB::table('xp_transactions')->whereIn('user_id', $students)
            ->where('occurred_at', '>=', $start->utc()->format('Y-m-d H:i:s.v'))->where('occurred_at', '<', $end->utc()->format('Y-m-d H:i:s.v'))
            ->groupBy('user_id')->selectRaw('user_id, SUM(amount) AS xp')->orderByDesc('xp')->orderBy('user_id')->get();
        $rows = [];
        $me = null;
        $rank = 0;
        $prev = null;
        $users = User::query()->whereIn('id', $totals->take($limit)->pluck('user_id')->push($viewer->id))->get()->keyBy('id');
        foreach ($totals->values() as $i => $row) {
            $xp = (int) $row->xp;
            if ($xp !== $prev) {
                $rank = $i + 1;
                $prev = $xp;
            }
            $isMe = (int) $row->user_id === $viewer->id;
            if ($i >= $limit && ! $isMe) {
                continue;
            }
            $u = $users->get((int) $row->user_id);
            $entry = ['rank' => $rank, 'xp' => $xp, 'is_me' => $isMe, 'name' => $isMe ? ($u?->nickname ?: ($u === null ? '' : $u->name)) : self::publicName($u)];
            if ($i < $limit) {
                $rows[] = $entry;
            }
            if ($isMe) {
                $me = $entry;
            }
        }

        return ['period' => $period === 'month' ? 'month' : 'week', 'from' => $start->toDateString(), 'to' => $end->subDay()->toDateString(), 'rows' => $rows, 'me' => $me];
    }

    public static function publicName(?User $user): string
    {
        if ($user === null) {
            return 'Ẩn danh';
        }
        if (filled($user->nickname)) {
            return (string) $user->nickname;
        }

        return $user->show_name_on_leaderboard ? $user->name : 'Ẩn danh';
    }
}
