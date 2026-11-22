import { Head, Link, router } from '@inertiajs/react';
import { RotateCcw } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Props = {
    session: {
        id: number;
        title: string;
        mode: string;
        mode_label: string;
        status: string;
        status_label: string;
        end_reason: string | null;
        count: number;
        correct: number;
        lives: number | null;
        started_at: string;
        ended_at: string | null;
        factor: number;
        xp: number | null;
        xp_pending: boolean;
        retry_url: string;
    };
    questions: {
        position: number;
        stem: string;
        result: string | null;
        result_label: string;
        tries: number;
        hint_used: boolean;
        given: string | null;
        answer: {
            choices: { content: string; is_correct: boolean }[];
            ordered: boolean;
            explanation: string | null;
        };
    }[];
};

/** Kết quả phiên luyện tập đã chốt: từng câu, đáp án, lời giải và XP QT-23 đã ghi. */
export default function PracticeResult({ session, questions }: Props) {
    return (
        <Page
            title={session.title}
            eyebrow={`${session.mode_label} · ${session.status_label}${session.end_reason && session.status === 'BO_DO' ? ` (${session.end_reason})` : ''}`}
            description={`Bắt đầu ${vnTime(session.started_at)} · kết thúc ${vnTime(session.ended_at)} · hệ số cấp ×${session.factor.toFixed(2)}`}
            actions={
                <>
                    <Button asChild variant="outline">
                        <Link href={student.practice.index()}>
                            Về danh sách
                        </Link>
                    </Button>
                    <Button
                        onClick={() =>
                            router.post(session.retry_url, {
                                mode: session.mode,
                            })
                        }
                    >
                        <RotateCcw /> Làm lại
                    </Button>
                </>
            }
        >
            <Head title={`Kết quả · ${session.title}`} />
            <div className="grid gap-3 sm:grid-cols-3">
                <Stat
                    label="Câu đúng"
                    value={`${session.correct}/${session.count}`}
                />
                <Stat
                    label="XP nhận được"
                    value={
                        session.status === 'BO_DO'
                            ? 0
                            : session.xp === null
                              ? 'Đang ghi…'
                              : `+${session.xp}`
                    }
                    tone="accent"
                />
                {session.lives !== null ? (
                    <Stat label="Mạng còn lại" value={session.lives} />
                ) : (
                    <Stat label="Chế độ" value={session.mode_label} />
                )}
            </div>
            {session.status === 'BO_DO' && (
                <p className="text-sm text-muted-foreground">
                    Phiên bỏ dở không có XP và không giữ chuỗi ngày học.
                </p>
            )}
            <Block>
                <ol className="grid gap-3">
                    {questions.map((q) => (
                        <li
                            key={q.position}
                            className="grid gap-2 rounded-xl border border-border p-4"
                        >
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <p className="min-w-0 font-medium wrap-anywhere whitespace-pre-wrap">
                                    Câu {q.position}. {q.stem}
                                </p>
                                <span
                                    className={cn(
                                        'rounded-full px-2.5 py-0.5 text-xs',
                                        q.result === 'DUNG'
                                            ? 'bg-success/15 text-success'
                                            : 'bg-destructive/10 text-destructive',
                                        q.result === null &&
                                            'bg-muted text-muted-foreground',
                                    )}
                                >
                                    {q.result_label}
                                    {q.hint_used ? ' · có gợi ý' : ''}
                                    {q.tries > 1 ? ` · lần ${q.tries}` : ''}
                                </span>
                            </div>
                            <dl className="grid gap-1 text-sm sm:grid-cols-2">
                                <div>
                                    <dt className="text-muted-foreground">
                                        Bạn trả lời
                                    </dt>
                                    <dd className="wrap-anywhere">
                                        {q.given ?? '—'}
                                    </dd>
                                </div>
                                <div>
                                    <dt className="text-muted-foreground">
                                        {q.answer.ordered
                                            ? 'Thứ tự đúng'
                                            : 'Đáp án'}
                                    </dt>
                                    <dd className="wrap-anywhere">
                                        {q.answer.choices
                                            .filter(
                                                (c) =>
                                                    q.answer.ordered ||
                                                    c.is_correct,
                                            )
                                            .map((c) => c.content)
                                            .join(
                                                q.answer.ordered
                                                    ? ' → '
                                                    : ' · ',
                                            )}
                                    </dd>
                                </div>
                            </dl>
                            {q.answer.explanation && (
                                <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                                    {q.answer.explanation}
                                </p>
                            )}
                        </li>
                    ))}
                </ol>
            </Block>
        </Page>
    );
}
