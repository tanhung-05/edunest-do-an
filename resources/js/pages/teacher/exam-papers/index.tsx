import { Head, Link } from '@inertiajs/react';
import { Eye, FileText, Lock, ShieldCheck } from 'lucide-react';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';

type Props = {
    papers: {
        id: number;
        title: string;
        question_count: number;
        locked: boolean;
        answers_open: boolean;
        edit_url: string;
    }[];
    banks: {
        id: number;
        name: string;
        course: string | null;
        create_url: string;
    }[];
    exams_url: string;
};

/** MH-GV-13: đề thi thử của bạn; tạo đề từ ngân hàng bảo mật của khóa được giao. */
export default function ExamPapers({ papers, banks, exams_url }: Props) {
    return (
        <Page
            title="Đề thi thử"
            description="Đề lấy câu từ ngân hàng bảo mật. Đề bị khóa khi có kỳ thi chuyển sang Sắp mở."
            actions={
                <Button variant="outline" asChild>
                    <Link href={exams_url}>Kỳ thi</Link>
                </Button>
            }
            stats={[
                { label: 'Đề thi', value: papers.length, icon: FileText },
                {
                    label: 'Đã khóa',
                    value: papers.filter((p) => p.locked).length,
                    icon: Lock,
                },
                {
                    label: 'Đã mở đáp án',
                    value: papers.filter((p) => p.answers_open).length,
                    icon: Eye,
                },
                {
                    label: 'Ngân hàng bảo mật',
                    value: banks.length,
                    icon: ShieldCheck,
                },
            ]}
        >
            <Head title="Đề thi thử" />
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_20rem]">
                <Block className="p-0 md:p-0">
                    <ul className="divide-y divide-border">
                        {papers.map((p) => (
                            <li
                                key={p.id}
                                className="flex flex-wrap items-center gap-3 px-5 py-4 md:px-6"
                            >
                                <div className="min-w-0 flex-1">
                                    <p className="font-semibold wrap-anywhere">
                                        {p.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {p.question_count} câu
                                        {p.locked && ' · Đã khóa'}
                                        {p.answers_open && ' · Đã mở đáp án'}
                                    </p>
                                </div>
                                <Button size="sm" variant="outline" asChild>
                                    <Link href={p.edit_url}>
                                        {p.locked ? 'Xem' : 'Sửa'}
                                    </Link>
                                </Button>
                            </li>
                        ))}
                        {papers.length === 0 && (
                            <li className="px-6 py-8 text-center text-muted-foreground">
                                Chưa có đề nào.
                            </li>
                        )}
                    </ul>
                </Block>
                <Block tone="surface" className="grid gap-3">
                    <h2 className="font-semibold">
                        Tạo đề từ ngân hàng bảo mật
                    </h2>
                    {banks.map((b) => (
                        <Link
                            key={b.id}
                            href={b.create_url}
                            className="rounded-lg border border-white/15 px-3 py-2 text-sm hover:bg-white/10"
                        >
                            <span className="font-medium">{b.name}</span>
                            <span className="block text-xs opacity-75">
                                {b.course}
                            </span>
                        </Link>
                    ))}
                    {banks.length === 0 && (
                        <p className="text-sm opacity-80">
                            Chưa có ngân hàng bảo mật. Bật "Bảo mật" ở trang
                            ngân hàng câu hỏi (ngân hàng chưa dùng cho quiz bài
                            học).
                        </p>
                    )}
                </Block>
            </div>
        </Page>
    );
}
