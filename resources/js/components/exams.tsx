import { vnTime } from '@/components/commerce';
import { Block } from '@/components/page';
import { cn } from '@/lib/utils';
import type { Analytics, ExamSummary, RankRow } from '@/types/exams';
import { examTone } from '@/types/exams';

export function ExamBadge({
    exam,
}: {
    exam: Pick<ExamSummary, 'status' | 'status_label'>;
}) {
    return (
        <span
            className={cn(
                'inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold',
                examTone[exam.status],
            )}
        >
            {exam.status_label}
        </span>
    );
}

/** Lịch kỳ: giờ VN, thời lượng, đối tượng. */
export function ExamFacts({
    exam,
    extra,
}: {
    exam: ExamSummary;
    extra?: [string, React.ReactNode][];
}) {
    const rows: [string, React.ReactNode][] = [
        ['Mở', vnTime(exam.opens_at)],
        ['Đóng', vnTime(exam.closes_at)],
        ['Thời lượng', `${exam.duration_minutes} phút`],
        ['Đối tượng', exam.audience],
        ['Đăng ký', exam.requires_registration ? 'Bắt buộc' : 'Không bắt buộc'],
        [
            'Công bố',
            exam.published_at
                ? vnTime(exam.published_at)
                : exam.publish_at
                  ? `Hẹn ${vnTime(exam.publish_at)}`
                  : 'Ngay khi đóng',
        ],
        ...(extra ?? []),
    ];

    return (
        <dl className="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
            {rows.map(([label, value]) => (
                <div key={label} className="min-w-0">
                    <dt className="text-muted-foreground">{label}</dt>
                    <dd className="font-medium wrap-anywhere">{value}</dd>
                </div>
            ))}
        </dl>
    );
}

export function RankingTable({
    rows,
    empty = 'Chưa có xếp hạng.',
}: {
    rows: RankRow[];
    empty?: string;
}) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <table className="w-full text-sm">
            <thead className="text-left text-muted-foreground">
                <tr>
                    <th className="py-2 pr-3 font-medium">Hạng</th>
                    <th className="py-2 pr-3 font-medium">Người dự thi</th>
                    <th className="py-2 text-right font-medium">Điểm</th>
                </tr>
            </thead>
            <tbody className="divide-y divide-border">
                {rows.map((r, i) => (
                    <tr key={i} className={cn(r.mine && 'bg-amber-soft/50')}>
                        <td className="py-2 pr-3 font-semibold tabular-nums">
                            {r.rank}
                        </td>
                        <td className="py-2 pr-3">
                            {r.name}
                            {r.mine && (
                                <span className="text-muted-foreground">
                                    {' '}
                                    (bạn)
                                </span>
                            )}
                        </td>
                        <td className="py-2 text-right tabular-nums">
                            {r.percent}%
                        </td>
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

/** QT-20: tiến độ, thống kê điểm, phân bố 10 nhóm, từng câu (chỉ GV). */
export function AnalyticsPanel({
    data,
    exportUrl,
}: {
    data: Analytics;
    exportUrl?: string | null;
}) {
    const p = data.progress;
    const s = data.scores;
    const peak = Math.max(1, ...data.buckets.map((b) => b.count));
    const fmt = (v: string | null) => (v === null ? '—' : `${v}%`);

    return (
        <div className="grid gap-4">
            <Block className="grid gap-4">
                <div className="flex flex-wrap items-center justify-between gap-2">
                    <h2 className="text-lg font-semibold">Phân tích</h2>
                    {exportUrl && (
                        <a
                            href={exportUrl}
                            className="text-sm font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                        >
                            Xuất Excel
                        </a>
                    )}
                </div>
                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-5">
                    {(
                        [
                            ['Được giao', p.assigned],
                            ['Chưa bắt đầu', p.not_started],
                            ['Đang làm', p.in_progress],
                            ['Đã nộp', p.submitted],
                            ['Hết giờ', p.timed_out],
                        ] as const
                    ).map(([label, value]) => (
                        <div key={label} className="rounded-xl bg-muted/60 p-3">
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="text-xl font-bold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <dl className="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                    {(
                        [
                            ['Trung bình', fmt(s.average)],
                            ['Trung vị', fmt(s.median)],
                            ['Cao nhất', fmt(s.max)],
                            ['Thấp nhất', fmt(s.min)],
                        ] as const
                    ).map(([label, value]) => (
                        <div key={label}>
                            <dt className="text-muted-foreground">{label}</dt>
                            <dd className="font-semibold tabular-nums">
                                {value}
                            </dd>
                        </div>
                    ))}
                </dl>
                <figure className="grid gap-2">
                    <figcaption className="text-sm text-muted-foreground">
                        Phân bố điểm (thang 10) — {s.count} người có điểm
                    </figcaption>
                    <div
                        className="flex h-32 items-end gap-1.5"
                        role="img"
                        aria-label="Biểu đồ phân bố điểm"
                    >
                        {data.buckets.map((b) => (
                            <div
                                key={b.label}
                                className="flex h-full flex-1 flex-col items-center justify-end gap-1"
                            >
                                <span className="text-xs tabular-nums">
                                    {b.count || ''}
                                </span>
                                <div
                                    className="w-full rounded-t-md bg-sky-600/80 dark:bg-sky-400/70"
                                    style={{
                                        height: `${(b.count / peak) * 100}%`,
                                        minHeight: b.count ? 4 : 0,
                                    }}
                                />
                                <span className="text-[10px] text-muted-foreground tabular-nums">
                                    {b.label}
                                </span>
                            </div>
                        ))}
                    </div>
                </figure>
            </Block>
            {data.questions.length > 0 && (
                <Block className="grid gap-3">
                    <h2 className="text-lg font-semibold">
                        Từng câu (lượt chốt gần nhất mỗi người)
                    </h2>
                    <div className="overflow-x-auto">
                        <table className="w-full min-w-[36rem] text-sm">
                            <thead className="text-left text-muted-foreground">
                                <tr>
                                    <th className="py-2 pr-3 font-medium">
                                        Câu
                                    </th>
                                    <th className="py-2 pr-3 font-medium">
                                        Nội dung
                                    </th>
                                    <th className="py-2 pr-3 text-right font-medium">
                                        Đúng
                                    </th>
                                    <th className="py-2 pr-3 text-right font-medium">
                                        Sai
                                    </th>
                                    <th className="py-2 pr-3 text-right font-medium">
                                        Bỏ trống
                                    </th>
                                    <th className="py-2 text-right font-medium">
                                        Tỷ lệ đúng
                                    </th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-border">
                                {data.questions.map((q) => (
                                    <tr key={q.position} className="align-top">
                                        <td className="py-2 pr-3 tabular-nums">
                                            {q.position}
                                        </td>
                                        <td className="py-2 pr-3">
                                            <p className="wrap-anywhere">
                                                {q.content}
                                            </p>
                                            {q.choices.length > 0 && (
                                                <p className="mt-1 text-xs text-muted-foreground">
                                                    {q.choices
                                                        .map(
                                                            (c) =>
                                                                `${c.is_correct ? '✓ ' : ''}${c.content}: ${c.count}`,
                                                        )
                                                        .join(' · ')}
                                                </p>
                                            )}
                                        </td>
                                        <td className="py-2 pr-3 text-right tabular-nums">
                                            {q.correct}
                                        </td>
                                        <td className="py-2 pr-3 text-right tabular-nums">
                                            {q.wrong}
                                        </td>
                                        <td className="py-2 pr-3 text-right tabular-nums">
                                            {q.blank}
                                        </td>
                                        <td className="py-2 text-right tabular-nums">
                                            {fmt(q.accuracy)}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </Block>
            )}
        </div>
    );
}
