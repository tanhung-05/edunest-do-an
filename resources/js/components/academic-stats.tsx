import { router } from '@inertiajs/react';
import { useState } from 'react';
import { vnd } from '@/components/commerce';
import { Block } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

export type DashboardStats = {
    from: string;
    to: string;
    granularity: 'day' | 'month';
    revenue: {
        total: number;
        label: string;
        series: { key: string; amount: number }[];
    };
    funnel: {
        views: number;
        orders: number;
        starts: number;
        completions: number;
        conversion: string | null;
    };
    top_courses: {
        course_id: number;
        title: string;
        sold: number;
        amount: number;
    }[];
    coupons_used: number;
    codes_redeemed: number;
    renewal: { renewed: number; due: number; rate: string | null };
    orders_cxl: number;
};

/** QT-20 (MH-GVU-01 mở rộng): doanh thu theo ngày/tháng, phễu, khóa bán chạy, mã đã dùng, gia hạn; lọc ngày, xuất Excel. */
export function AcademicStats({
    stats,
    exportUrl,
    baseUrl,
}: {
    stats: DashboardStats;
    exportUrl: string;
    baseUrl: string;
}) {
    const [from, setFrom] = useState(stats.from);
    const [to, setTo] = useState(stats.to);
    const peak = Math.max(1, ...stats.revenue.series.map((r) => r.amount));
    const f = stats.funnel;
    const funnel = [
        ['Lượt xem trang khóa', f.views],
        ['Đơn mua lần đầu hoàn tất', f.orders],
        ['Bắt đầu học', f.starts],
        ['Hoàn thành', f.completions],
    ] as const;
    const widest = Math.max(1, ...funnel.map(([, v]) => v));

    return (
        <div className="grid gap-4">
            <Block className="flex flex-wrap items-end justify-between gap-3 p-4 md:p-5">
                <form
                    className="flex flex-wrap items-end gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            baseUrl,
                            { from, to },
                            {
                                preserveScroll: true,
                                preserveState: true,
                                only: ['stats', 'export_url'],
                            },
                        );
                    }}
                >
                    <label className="grid gap-1 text-sm">
                        Từ ngày
                        <Input
                            type="date"
                            value={from}
                            onChange={(e) => setFrom(e.target.value)}
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Đến ngày
                        <Input
                            type="date"
                            value={to}
                            onChange={(e) => setTo(e.target.value)}
                        />
                    </label>
                    <Button variant="outline">Xem</Button>
                </form>
                <a
                    href={exportUrl}
                    className="text-sm font-medium text-sky-700 underline-offset-4 hover:underline dark:text-sky-300"
                >
                    Xuất Excel
                </a>
            </Block>
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                <Block className="grid gap-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-semibold">
                            Doanh thu ({stats.revenue.label.toLowerCase()})
                        </h2>
                        <span className="font-display text-2xl font-bold tabular-nums">
                            {vnd(stats.revenue.total)}
                        </span>
                    </div>
                    <div
                        className="flex h-40 items-end gap-0.5"
                        role="img"
                        aria-label={`Doanh thu theo ${stats.granularity === 'day' ? 'ngày' : 'tháng'}`}
                    >
                        {stats.revenue.series.map((r) => (
                            <div
                                key={r.key}
                                title={`${r.key}: ${vnd(r.amount)}`}
                                className="flex-1 rounded-t-sm bg-sky-600/80 dark:bg-sky-400/70"
                                style={{
                                    height: `${(r.amount / peak) * 100}%`,
                                    minHeight: r.amount ? 3 : 1,
                                    opacity: r.amount ? 1 : 0.25,
                                }}
                            />
                        ))}
                    </div>
                    <p className="flex justify-between text-xs text-muted-foreground tabular-nums">
                        <span>{stats.revenue.series[0]?.key}</span>
                        <span>{stats.revenue.series.at(-1)?.key}</span>
                    </p>
                </Block>
                <Block className="grid gap-3">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-semibold">Phễu</h2>
                        <span className="text-sm text-muted-foreground">
                            Chuyển đổi:{' '}
                            <strong className="text-foreground">
                                {f.conversion === null
                                    ? 'Chưa đủ dữ liệu'
                                    : `${f.conversion}%`}
                            </strong>
                        </span>
                    </div>
                    <ul className="grid gap-2">
                        {funnel.map(([label, value]) => (
                            <li key={label} className="grid gap-1">
                                <div className="flex justify-between text-sm">
                                    <span>{label}</span>
                                    <span className="font-semibold tabular-nums">
                                        {value}
                                    </span>
                                </div>
                                <div className="h-2 rounded-full bg-muted">
                                    <div
                                        className="h-2 rounded-full bg-navy dark:bg-white/60"
                                        style={{
                                            width: `${(value / widest) * 100}%`,
                                        }}
                                    />
                                </div>
                            </li>
                        ))}
                    </ul>
                </Block>
            </div>
            <div className="grid gap-4 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                <Block className="grid gap-3">
                    <h2 className="text-lg font-semibold">Khóa bán chạy</h2>
                    <ol className="grid gap-2 text-sm">
                        {stats.top_courses.map((c, i) => (
                            <li
                                key={c.course_id}
                                className="flex items-center gap-3"
                            >
                                <span className="w-5 text-muted-foreground tabular-nums">
                                    {i + 1}
                                </span>
                                <span className="min-w-0 flex-1 truncate">
                                    {c.title}
                                </span>
                                <span className="tabular-nums">
                                    {c.sold} đơn
                                </span>
                                <span className="w-28 text-right font-medium tabular-nums">
                                    {vnd(c.amount)}
                                </span>
                            </li>
                        ))}
                        {stats.top_courses.length === 0 && (
                            <li className="text-muted-foreground">
                                Chưa có đơn trong khoảng này.
                            </li>
                        )}
                    </ol>
                </Block>
                <Block>
                    <dl className="grid grid-cols-2 gap-4 text-sm">
                        <div>
                            <dt className="text-muted-foreground">
                                Mã giảm giá đã dùng
                            </dt>
                            <dd className="text-2xl font-bold tabular-nums">
                                {stats.coupons_used}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Mã kích hoạt đã đổi
                            </dt>
                            <dd className="text-2xl font-bold tabular-nums">
                                {stats.codes_redeemed}
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Tỷ lệ gia hạn
                            </dt>
                            <dd className="text-2xl font-bold tabular-nums">
                                {stats.renewal.rate === null
                                    ? '—'
                                    : `${stats.renewal.rate}%`}
                            </dd>
                            <dd className="text-xs text-muted-foreground">
                                {stats.renewal.renewed}/{stats.renewal.due} ghi
                                danh đến hạn
                            </dd>
                        </div>
                        <div>
                            <dt className="text-muted-foreground">
                                Đơn cần xử lý
                            </dt>
                            <dd className="text-2xl font-bold tabular-nums">
                                {stats.orders_cxl}
                            </dd>
                        </div>
                    </dl>
                </Block>
            </div>
        </div>
    );
}
