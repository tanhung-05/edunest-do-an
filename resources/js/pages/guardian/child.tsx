import { Form, Head, Link } from '@inertiajs/react';
import { Flame } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import guardian from '@/routes/guardian';

type Props = {
    report: {
        student: { id: number; name: string };
        streak: number;
        courses: {
            id: number;
            course: string | null;
            completed: number;
            required: number;
            percent: string | null;
            validity: string;
            expires_at: string | null;
            quizzes: { title: string; best_percent: string | null }[];
        }[];
        exams: {
            title: string;
            published: boolean;
            score_percent: string | null;
            rank: number | null;
        }[];
    };
    link_id: number;
};

/** MH-PH-02: năm nhóm dữ liệu được xem của một con (BR-MR-05/06). Không có đáp án, chứng nhận, đơn hay ghi chú. */
export default function GuardianChild({ report, link_id }: Props) {
    return (
        <Page
            title={report.student.name}
            eyebrow={
                <Link href={guardian.dashboard()} className="hover:underline">
                    Con của tôi
                </Link>
            }
            description="Dữ liệu chỉ đọc. Kết quả thi thử chỉ hiện điểm sau khi kỳ thi công bố."
            actions={
                <Form
                    {...guardian.links.destroy.form(link_id)}
                    onBefore={() =>
                        window.confirm(
                            'Gỡ liên kết với con? Bạn sẽ không xem được dữ liệu và không nhận báo cáo tuần nữa.',
                        )
                    }
                >
                    <Button type="submit" variant="outline">
                        Gỡ liên kết
                    </Button>
                </Form>
            }
        >
            <Head title={report.student.name} />
            <div className="grid gap-3 sm:grid-cols-3">
                <Block tone="accent" className="grid gap-1 p-4 md:p-5">
                    <div className="flex items-center gap-1.5 text-sm">
                        <Flame className="size-4" /> Chuỗi ngày học
                    </div>
                    <div className="font-display text-3xl font-bold tabular-nums">
                        {report.streak} ngày
                    </div>
                </Block>
                <Stat label="Khóa đang học" value={report.courses.length} />
                <Stat label="Kỳ thi thử đã nộp" value={report.exams.length} />
            </div>

            <div className="grid gap-4 lg:grid-cols-2">
                {report.courses.map((c) => (
                    <Block key={c.id} className="grid content-start gap-3">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h2 className="text-lg font-semibold wrap-anywhere">
                                {c.course}
                            </h2>
                            <span className="text-sm text-muted-foreground">
                                {c.validity} · hạn{' '}
                                {c.expires_at
                                    ? vnTime(c.expires_at)
                                    : 'vĩnh viễn'}
                            </span>
                        </div>
                        <div>
                            <div className="mb-1 flex justify-between text-sm">
                                <span>Bài bắt buộc đã xong</span>
                                <span className="tabular-nums">
                                    {c.completed}/{c.required}
                                </span>
                            </div>
                            <div
                                className="h-2 overflow-hidden rounded-full bg-muted"
                                aria-label={`Tiến độ ${c.percent ?? 0}%`}
                            >
                                <div
                                    className="h-full bg-success"
                                    style={{ width: `${c.percent ?? 0}%` }}
                                />
                            </div>
                        </div>
                        {c.quizzes.length > 0 && (
                            <ul className="grid gap-1 text-sm">
                                {c.quizzes.map((q) => (
                                    <li
                                        key={q.title}
                                        className="flex justify-between gap-3 border-t border-border pt-1"
                                    >
                                        <span className="wrap-anywhere">
                                            Quiz: {q.title}
                                        </span>
                                        <span className="shrink-0 tabular-nums">
                                            {q.best_percent === null
                                                ? 'Chưa làm'
                                                : `${q.best_percent}%`}
                                        </span>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </Block>
                ))}
            </div>

            <Block className="grid gap-3">
                <h2 className="text-lg font-semibold">Thi thử</h2>
                {report.exams.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Chưa có lượt thi thử đã nộp.
                    </p>
                ) : (
                    <ul className="grid gap-2 text-sm">
                        {report.exams.map((e, i) => (
                            <li
                                key={`${e.title}-${i}`}
                                className="flex flex-wrap justify-between gap-2 border-t border-border pt-2"
                            >
                                <span className="wrap-anywhere">{e.title}</span>
                                <span className="tabular-nums">
                                    {e.published
                                        ? `${e.score_percent ?? '—'}%${e.rank ? ` · hạng ${e.rank}` : ''}`
                                        : 'Đã nộp, chờ công bố'}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Block>
        </Page>
    );
}
