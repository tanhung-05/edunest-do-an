import { Head, Link } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { ExamBadge } from '@/components/exams';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import type { ExamSummary } from '@/types/exams';

type Props = {
    scope: 'teacher' | 'academic';
    events: (ExamSummary & { url: string })[];
    create_url: string;
    papers_url: string | null;
};

/** MH-GV-14 / MH-GVU-12: kỳ thi do bạn tổ chức. */
export default function ManageExams({
    scope,
    events,
    create_url,
    papers_url,
}: Props) {
    return (
        <Page
            title="Kỳ thi thử"
            description={
                scope === 'teacher'
                    ? 'Kỳ thi dùng đề của bạn. Chuyển sang "Sắp mở" để khóa đề; hệ thống tự mở, đóng và công bố theo lịch.'
                    : 'Kỳ thi do bạn tổ chức. Bạn chỉ thấy tên, số câu và thời lượng của đề (không thấy đáp án).'
            }
            actions={
                <>
                    {papers_url && (
                        <Button variant="outline" asChild>
                            <Link href={papers_url}>Đề thi của tôi</Link>
                        </Button>
                    )}
                    <Button asChild>
                        <Link href={create_url}>Tạo kỳ thi</Link>
                    </Button>
                </>
            }
        >
            <Head title="Kỳ thi thử" />
            <Block className="p-0 md:p-0">
                <ul className="divide-y divide-border">
                    {events.map((e) => (
                        <li key={e.id}>
                            <Link
                                href={e.url}
                                className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-4 hover:bg-accent/50 md:px-6"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold wrap-anywhere">
                                        {e.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Đề: {e.paper ?? '—'} ·{' '}
                                        {vnTime(e.opens_at)} –{' '}
                                        {vnTime(e.closes_at)} ·{' '}
                                        {e.duration_minutes} phút
                                    </p>
                                </div>
                                <ExamBadge exam={e} />
                            </Link>
                        </li>
                    ))}
                    {events.length === 0 && (
                        <li className="px-6 py-8 text-center text-muted-foreground">
                            Bạn chưa tổ chức kỳ thi nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
