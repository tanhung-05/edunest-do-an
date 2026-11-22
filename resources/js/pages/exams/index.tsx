import { Head, Link } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { ExamBadge } from '@/components/exams';
import { Block, Page } from '@/components/page';
import type { ExamSummary } from '@/types/exams';

type Row = ExamSummary & {
    registered: boolean;
    attempted: boolean;
    url: string;
};

export default function ExamList({ events }: { events: Row[] }) {
    return (
        <Page
            title="Kỳ thi thử"
            description="Các kỳ thi bạn được tham gia. Đồng hồ tính theo máy chủ; mỗi kỳ làm một lượt."
        >
            <Head title="Kỳ thi thử" />
            <Block className="p-0 md:p-0">
                <ul className="divide-y divide-border">
                    {events.map((e) => (
                        <li key={e.id}>
                            <Link
                                href={e.url}
                                className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 transition-colors hover:bg-accent/50 md:px-6"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold wrap-anywhere">
                                        {e.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {vnTime(e.opens_at)} –{' '}
                                        {vnTime(e.closes_at)} ·{' '}
                                        {e.duration_minutes} phút · {e.audience}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2 text-sm">
                                    {e.attempted ? (
                                        <span className="text-muted-foreground">
                                            Đã làm
                                        </span>
                                    ) : e.registered ? (
                                        <span className="text-muted-foreground">
                                            Đã đăng ký
                                        </span>
                                    ) : null}
                                    <ExamBadge exam={e} />
                                </div>
                            </Link>
                        </li>
                    ))}
                    {events.length === 0 && (
                        <li className="px-6 py-8 text-center text-muted-foreground">
                            Chưa có kỳ thi nào dành cho bạn.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
