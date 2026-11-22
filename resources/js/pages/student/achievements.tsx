import { Form, Head, Link } from '@inertiajs/react';
import { Award, Flame, Lock, Trophy, Zap } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Props = {
    summary: {
        total_xp: number;
        level: number;
        level_floor: number;
        next_level_at: number;
        factor: number;
        current_streak: number;
        longest_streak: number;
        correct_answers: number;
        learned_today: boolean;
        calendar: { day: string; learned: boolean }[];
        badges: {
            id: number;
            code: string;
            name: string;
            description: string | null;
            awarded_at: string | null;
        }[];
    };
    ledger: {
        id: number;
        rule: string;
        source: string;
        amount: number;
        occurred_at: string;
    }[];
    leaderboard: {
        period: 'week' | 'month';
        from: string;
        to: string;
        rows: { rank: number; xp: number; name: string; is_me: boolean }[];
        me: { rank: number; xp: number } | null;
    };
    profile: {
        nickname: string | null;
        show_name: boolean;
        name: string;
        public_name: string;
    };
};

const dmy = (d: string) => d.split('-').reverse().join('/');

/** MH-HV-18: XP, cấp, chuỗi ngày (giờ VN), huy hiệu, sổ XP và bảng xếp hạng (QT-23). */
export default function Achievements({
    summary,
    ledger,
    leaderboard,
    profile,
}: Props) {
    const span = Math.max(1, summary.next_level_at - summary.level_floor);
    const progress = Math.min(
        100,
        ((summary.total_xp - summary.level_floor) / span) * 100,
    );
    const earned = summary.badges.filter((b) => b.awarded_at).length;
    const lastWeek = summary.calendar.slice(-7);

    return (
        <Page
            title="Thành tích"
            description="XP chỉ do hệ thống cộng từ luyện tập, mini game, hoàn thành bài và khóa. Ngày học tính theo giờ Việt Nam."
            stats={[
                { label: 'Cấp hiện tại', value: summary.level, icon: Trophy },
                {
                    label: 'Tổng XP',
                    value: summary.total_xp.toLocaleString('vi-VN'),
                    icon: Zap,
                },
                {
                    label: 'Chuỗi hiện tại',
                    value: `${summary.current_streak} ngày`,
                    icon: Flame,
                    attention:
                        summary.current_streak > 0 && !summary.learned_today,
                },
                {
                    label: 'Huy hiệu',
                    value: `${earned}/${summary.badges.length}`,
                    icon: Award,
                },
            ]}
        >
            <Head title="Thành tích" />
            <div className="grid gap-5 lg:grid-cols-[minmax(0,1.6fr)_minmax(0,1fr)]">
                <Block className="flex flex-wrap items-center gap-5">
                    <div
                        aria-hidden
                        className="grid size-24 shrink-0 place-items-center rounded-full bg-navy text-white shadow-[0_14px_30px_-14px_rgb(11_31_58/0.8)] ring-4 ring-amber/60 dark:bg-slate-800"
                    >
                        <div className="text-center leading-none">
                            <div className="text-[0.65rem] tracking-wide text-white/70 uppercase">
                                Cấp
                            </div>
                            <div className="font-display text-4xl font-bold tabular-nums">
                                {summary.level}
                            </div>
                        </div>
                    </div>
                    <div className="grid min-w-0 flex-1 gap-2">
                        <p className="flex flex-wrap items-baseline gap-x-2">
                            <span className="font-display text-3xl font-bold tabular-nums">
                                {summary.total_xp.toLocaleString('vi-VN')} XP
                            </span>
                            <span className="text-sm text-muted-foreground">
                                hệ số thưởng ×{summary.factor.toFixed(1)}
                            </span>
                        </p>
                        <div
                            className="h-3 overflow-hidden rounded-full bg-muted"
                            role="progressbar"
                            aria-valuemin={summary.level_floor}
                            aria-valuemax={summary.next_level_at}
                            aria-valuenow={summary.total_xp}
                            aria-label={`Tiến tới cấp ${summary.level + 1}`}
                        >
                            <div
                                className="h-full rounded-full bg-amber"
                                style={{ width: `${progress}%` }}
                            />
                        </div>
                        <p className="flex justify-between text-xs text-muted-foreground tabular-nums">
                            <span>
                                Cấp {summary.level}: {summary.level_floor} XP
                            </span>
                            <span>
                                Còn{' '}
                                <b className="text-foreground">
                                    {summary.next_level_at - summary.total_xp}
                                </b>{' '}
                                XP lên cấp {summary.level + 1}
                            </span>
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {summary.correct_answers} câu đúng trong luyện tập,
                            chuỗi dài nhất {summary.longest_streak} ngày
                        </p>
                    </div>
                </Block>
                <Block
                    tone={summary.learned_today ? 'plain' : 'accent'}
                    className="grid content-between gap-3"
                >
                    <div className="flex items-center gap-3">
                        <span
                            aria-hidden
                            className={cn(
                                'grid size-12 shrink-0 place-items-center rounded-2xl',
                                summary.current_streak > 0
                                    ? 'bg-amber/25 text-amber-700 dark:text-amber'
                                    : 'bg-muted text-muted-foreground',
                            )}
                        >
                            <Flame className="size-6" />
                        </span>
                        <div>
                            <p className="font-display text-2xl font-bold tabular-nums">
                                {summary.current_streak} ngày liên tiếp
                            </p>
                            <p className="text-sm text-muted-foreground">
                                {summary.learned_today
                                    ? 'Hôm nay đã giữ chuỗi.'
                                    : summary.current_streak > 0
                                      ? 'Học một bài hoặc hoàn thành một phiên hôm nay để giữ chuỗi.'
                                      : 'Bắt đầu chuỗi mới hôm nay.'}
                            </p>
                        </div>
                    </div>
                    <ol
                        className="grid grid-cols-7 gap-1.5"
                        aria-label="7 ngày gần nhất"
                    >
                        {lastWeek.map((c) => (
                            <li
                                key={c.day}
                                className="grid justify-items-center"
                            >
                                <span
                                    className={cn(
                                        'grid size-8 place-items-center rounded-full text-xs',
                                        c.learned
                                            ? 'bg-amber text-navy'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                    title={
                                        dmy(c.day) +
                                        (c.learned ? ' (có học)' : '')
                                    }
                                >
                                    {c.learned ? (
                                        <Flame aria-hidden className="size-4" />
                                    ) : (
                                        Number(c.day.slice(8))
                                    )}
                                </span>
                            </li>
                        ))}
                    </ol>
                </Block>
            </div>

            <div className="grid gap-5 lg:grid-cols-[1.2fr_1fr]">
                <Block className="grid gap-4">
                    <h2 className="text-lg font-semibold">35 ngày gần nhất</h2>
                    <ol
                        className="grid max-w-sm grid-cols-7 gap-1.5"
                        aria-label="Lịch ngày học"
                    >
                        {summary.calendar.map((c) => (
                            <li
                                key={c.day}
                                title={`${dmy(c.day)}${c.learned ? ' · có học' : ''}`}
                                className={cn(
                                    'aspect-square rounded-md bg-muted',
                                    c.learned &&
                                        'bg-success/80 shadow-[inset_0_-2px_0_rgb(0_0_0/0.12)]',
                                )}
                            />
                        ))}
                    </ol>
                    <p className="flex items-center gap-3 text-xs text-muted-foreground">
                        <span className="flex items-center gap-1.5">
                            <span className="size-3 rounded-sm bg-success/80" />
                            Có học
                        </span>
                        <span className="flex items-center gap-1.5">
                            <span className="size-3 rounded-sm bg-muted ring-1 ring-border" />
                            Chưa học
                        </span>
                    </p>
                    <h2 className="text-lg font-semibold">
                        Huy hiệu{' '}
                        <span className="text-sm font-normal text-muted-foreground tabular-nums">
                            {earned}/{summary.badges.length}
                        </span>
                    </h2>
                    <ul className="grid gap-3 sm:grid-cols-2">
                        {summary.badges.map((b) => (
                            <li
                                key={b.id}
                                className={cn(
                                    'flex items-center gap-3 rounded-xl border p-3',
                                    b.awarded_at
                                        ? 'border-amber/40 bg-amber/10'
                                        : 'border-border',
                                )}
                            >
                                <span
                                    aria-hidden
                                    className={cn(
                                        'grid size-11 shrink-0 place-items-center rounded-full',
                                        b.awarded_at
                                            ? 'bg-amber text-navy shadow-[0_6px_14px_-6px_rgb(217_119_6/0.8)]'
                                            : 'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {b.awarded_at ? (
                                        <Award className="size-5" />
                                    ) : (
                                        <Lock className="size-4" />
                                    )}
                                </span>
                                <div className="min-w-0">
                                    <p className="font-medium wrap-anywhere">
                                        {b.name}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {b.awarded_at
                                            ? `Nhận ${vnTime(b.awarded_at, false)}`
                                            : b.description}
                                    </p>
                                </div>
                            </li>
                        ))}
                    </ul>
                </Block>

                <Block className="grid content-start gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            Bảng xếp hạng XP
                        </h2>
                        <div className="flex gap-1 rounded-lg bg-muted p-1 text-sm">
                            {(['week', 'month'] as const).map((p) => (
                                <Link
                                    key={p}
                                    href={student.achievements.index({
                                        query: { period: p },
                                    })}
                                    preserveScroll
                                    className={cn(
                                        'rounded-md px-3 py-1',
                                        leaderboard.period === p &&
                                            'bg-background font-medium shadow-sm',
                                    )}
                                >
                                    {p === 'week' ? 'Tuần' : 'Tháng'}
                                </Link>
                            ))}
                        </div>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {dmy(leaderboard.from)} – {dmy(leaderboard.to)} · đồng
                        điểm đồng hạng
                    </p>
                    <ol className="divide-y divide-border">
                        {leaderboard.rows.map((r, i) => (
                            <li
                                key={i}
                                className={cn(
                                    'flex items-center gap-3 py-2',
                                    r.is_me &&
                                        'rounded-md bg-amber-soft/50 px-2 font-semibold',
                                )}
                            >
                                <span
                                    className={cn(
                                        'grid size-7 shrink-0 place-items-center rounded-full text-xs font-bold tabular-nums',
                                        r.rank === 1 &&
                                            'bg-amber text-navy shadow-[0_4px_10px_-4px_rgb(217_119_6/0.9)]',
                                        r.rank === 2 &&
                                            'bg-slate-300 text-slate-800',
                                        r.rank === 3 &&
                                            'bg-orange-300 text-orange-950',
                                        r.rank > 3 && 'text-muted-foreground',
                                    )}
                                >
                                    {r.rank}
                                </span>
                                <span className="min-w-0 flex-1 wrap-anywhere">
                                    {r.name}
                                </span>
                                <span className="tabular-nums">{r.xp} XP</span>
                            </li>
                        ))}
                        {leaderboard.rows.length === 0 && (
                            <li className="py-4 text-center text-muted-foreground">
                                Chưa có XP trong kỳ này.
                            </li>
                        )}
                    </ol>
                    {leaderboard.me &&
                        !leaderboard.rows.some((r) => r.is_me) && (
                            <p className="text-sm">
                                Hạng của bạn: <b>{leaderboard.me.rank}</b> ·{' '}
                                {leaderboard.me.xp} XP
                            </p>
                        )}
                    <Form
                        {...student.achievements.profile.form()}
                        options={{ preserveScroll: true }}
                        className="grid gap-2 border-t pt-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Label htmlFor="nickname">
                                    Biệt danh trên bảng xếp hạng
                                </Label>
                                <Input
                                    id="nickname"
                                    name="nickname"
                                    defaultValue={profile.nickname ?? ''}
                                    maxLength={30}
                                    placeholder="Để trống nếu không dùng"
                                />
                                {errors.nickname && (
                                    <p className="text-sm text-destructive">
                                        {errors.nickname}
                                    </p>
                                )}
                                <label className="flex items-center gap-2 text-sm">
                                    <Checkbox
                                        name="show_name"
                                        value="1"
                                        defaultChecked={profile.show_name}
                                    />{' '}
                                    Cho hiện tên thật ({profile.name}) khi không
                                    có biệt danh
                                </label>
                                <p className="text-xs text-muted-foreground">
                                    Người khác đang thấy bạn là:{' '}
                                    {profile.public_name}
                                </p>
                                <Button
                                    type="submit"
                                    variant="outline"
                                    size="sm"
                                    disabled={processing}
                                    className="justify-self-start"
                                >
                                    Lưu
                                </Button>
                            </>
                        )}
                    </Form>
                </Block>
            </div>

            <Block className="p-0 md:p-0">
                <h2 className="px-5 pt-5 text-lg font-semibold md:px-6">
                    Sổ XP
                </h2>
                <ul className="mt-2 divide-y divide-border">
                    {ledger.map((l) => (
                        <li
                            key={l.id}
                            className="flex flex-wrap items-center gap-x-4 px-5 py-2.5 text-sm md:px-6"
                        >
                            <span className="min-w-0 flex-1">
                                {l.rule}{' '}
                                <span className="text-muted-foreground">
                                    · {l.source}
                                </span>
                            </span>
                            <span className="text-muted-foreground">
                                {vnTime(l.occurred_at)}
                            </span>
                            <span className="rounded-full bg-success/10 px-2.5 py-0.5 font-semibold text-success tabular-nums">
                                +{l.amount} XP
                            </span>
                        </li>
                    ))}
                    {ledger.length === 0 && (
                        <li className="px-6 py-6 text-center text-muted-foreground">
                            Chưa có khoản XP nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
