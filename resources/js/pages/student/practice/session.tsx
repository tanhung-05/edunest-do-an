import { Head, router } from '@inertiajs/react';
import {
    CheckCircle2,
    Heart,
    Lightbulb,
    SkipForward,
    XCircle,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import { hasAnswer, QuestionInput } from '@/components/learning/question-input';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import type { AnswerValue, Question } from '@/types/learning';

type Reveal = {
    choices: { content: string; is_correct: boolean }[];
    ordered: boolean;
    explanation: string | null;
};

type Props = {
    session: {
        id: number;
        title: string;
        mode: 'LEARNING' | 'TRY_HARD';
        mode_label: string;
        lives: number | null;
        position: number;
        count: number;
        deadline_ms: number | null;
        seconds_per_question: number | null;
        answer_url: string;
        hint_url: string;
        results: (string | null)[];
    };
    question: {
        position: number;
        stem: string;
        type: Question['type'];
        difficulty: string | null;
        image_url: string | null;
        choices: { id: number; content: string }[];
        tries: number;
        hint: string | null;
        has_hint: boolean;
        retry: boolean;
    } | null;
    feedback: {
        position: number;
        result: string | null;
        result_label: string | null;
        correct: boolean;
        answer: Reveal | null;
    } | null;
};

const difficultyLabel: Record<string, string> = {
    DE: 'Dễ',
    TRUNG_BINH: 'Trung bình',
    KHO: 'Khó',
};

/** MH-HV-17: một câu mỗi lần; Learning có thử lại và gợi ý, Try Hard có đồng hồ câu theo máy chủ và 3 mạng (QT-22). */
export default function PracticeSession(props: Props) {
    const key = `${props.session.id}-${props.question?.position ?? 0}-${props.question?.tries ?? 0}`;

    return <Runner key={key} {...props} />;
}

function Runner({ session, question, feedback }: Props) {
    const [value, setValue] = useState<AnswerValue>(null);
    const [busy, setBusy] = useState(false);
    const [left, setLeft] = useState(session.deadline_ms);

    useEffect(() => {
        if (session.deadline_ms === null) {
            return;
        }
        const started = performance.now();
        let reloaded = false;
        const timer = window.setInterval(() => {
            const remain =
                (session.deadline_ms ?? 0) - (performance.now() - started);
            setLeft(Math.max(0, remain));
            if (remain < -300 && !reloaded) {
                reloaded = true;
                router.reload();
            }
        }, 200);

        return () => window.clearInterval(timer);
    }, [session.deadline_ms]);

    const submit = (answer: AnswerValue) => {
        if (!question) {
            return;
        }
        setBusy(true);
        router.post(
            session.answer_url,
            {
                position: question.position,
                answer,
                request_id: crypto.randomUUID(),
            },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };

    const asQuestion: Question | null = question && {
        id: question.position,
        stem: question.stem,
        type: question.type,
        choices: question.choices,
        answer: value,
        save_url: '',
    };
    const seconds = left === null ? null : Math.ceil(left / 1000);
    const ratio =
        left !== null && session.seconds_per_question
            ? Math.min(1, left / (session.seconds_per_question * 1000))
            : null;

    return (
        <Page
            title={session.title}
            eyebrow={`${session.mode_label} · Câu ${session.position}/${session.count}`}
            className="max-w-4xl"
        >
            <Head title={`Luyện tập · ${session.title}`} />
            <div className="flex flex-wrap items-center gap-3">
                <ol
                    className="flex flex-1 flex-wrap gap-1"
                    aria-label="Tiến độ"
                >
                    {session.results.map((r, i) => (
                        <li
                            key={i}
                            title={`Câu ${i + 1}`}
                            className={cn(
                                'h-2 w-6 rounded-full bg-muted',
                                r === 'DUNG' && 'bg-success',
                                (r === 'SAI' || r === 'BO_TRONG') &&
                                    'bg-destructive',
                                r === 'HET_GIO' && 'bg-amber',
                                r === null &&
                                    i + 1 === session.position &&
                                    'bg-foreground/40',
                            )}
                        />
                    ))}
                </ol>
                {session.lives !== null && (
                    <span
                        className="flex items-center gap-0.5"
                        aria-label={`Còn ${session.lives} mạng`}
                    >
                        {[0, 1, 2].map((i) => (
                            <Heart
                                key={i}
                                className={cn(
                                    'size-5',
                                    i < (session.lives ?? 0)
                                        ? 'fill-destructive text-destructive'
                                        : 'text-muted-foreground',
                                )}
                            />
                        ))}
                    </span>
                )}
            </div>

            {feedback && (
                <Block
                    className={cn(
                        'grid gap-2 py-4',
                        feedback.correct
                            ? 'border-success/40'
                            : 'border-destructive/40',
                    )}
                >
                    <p className="flex items-center gap-2 font-medium">
                        {feedback.correct ? (
                            <CheckCircle2 className="text-success" />
                        ) : (
                            <XCircle className="text-destructive" />
                        )}
                        Câu {feedback.position}: {feedback.result_label}
                    </p>
                    {feedback.answer && !feedback.correct && (
                        <p className="text-sm">
                            Đáp án:{' '}
                            {feedback.answer.choices
                                .filter(
                                    (c) =>
                                        feedback.answer?.ordered ||
                                        c.is_correct,
                                )
                                .map((c) => c.content)
                                .join(feedback.answer.ordered ? ' → ' : ' · ')}
                        </p>
                    )}
                    {feedback.answer?.explanation && (
                        <p className="text-sm whitespace-pre-wrap text-muted-foreground">
                            {feedback.answer.explanation}
                        </p>
                    )}
                </Block>
            )}

            {question && asQuestion && (
                <Block className="grid gap-4">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <h2 className="min-w-0 text-lg font-semibold wrap-anywhere whitespace-pre-wrap">
                            {question.stem}
                        </h2>
                        {question.difficulty && (
                            <span className="rounded-full bg-secondary px-2.5 py-0.5 text-xs">
                                {difficultyLabel[question.difficulty] ??
                                    question.difficulty}
                            </span>
                        )}
                    </div>
                    {question.image_url && (
                        <img
                            src={question.image_url}
                            alt=""
                            className="max-h-72 rounded-lg object-contain"
                        />
                    )}
                    {seconds !== null && (
                        <div className="grid gap-1" aria-live="polite">
                            <div className="flex justify-between text-sm">
                                <span className="text-muted-foreground">
                                    Thời gian câu này
                                </span>
                                <span
                                    className={cn(
                                        'font-semibold tabular-nums',
                                        seconds <= 5 && 'text-destructive',
                                    )}
                                >
                                    {seconds} giây
                                </span>
                            </div>
                            <div className="h-1.5 overflow-hidden rounded-full bg-muted">
                                <div
                                    className="h-full bg-foreground/70 transition-[width]"
                                    style={{ width: `${(ratio ?? 0) * 100}%` }}
                                />
                            </div>
                        </div>
                    )}
                    {question.retry && (
                        <p className="rounded-lg bg-amber-soft/60 px-3 py-2 text-sm">
                            Chưa đúng. Bạn còn một lần trả lời cho câu này.
                        </p>
                    )}
                    {question.hint && (
                        <p className="flex gap-2 rounded-lg bg-secondary px-3 py-2 text-sm">
                            <Lightbulb className="size-4 shrink-0" />{' '}
                            {question.hint}
                        </p>
                    )}
                    <QuestionInput
                        question={asQuestion}
                        value={value}
                        onChange={setValue}
                        disabled={busy}
                    />
                    <div className="flex flex-wrap gap-2">
                        <Button
                            disabled={busy || !hasAnswer(value)}
                            onClick={() => submit(value)}
                        >
                            Trả lời
                        </Button>
                        <Button
                            variant="outline"
                            disabled={busy}
                            onClick={() => submit(null)}
                        >
                            <SkipForward /> Bỏ qua
                        </Button>
                        {question.has_hint && (
                            <Button
                                variant="ghost"
                                disabled={busy}
                                onClick={() =>
                                    router.post(
                                        session.hint_url,
                                        {},
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                <Lightbulb /> Xin gợi ý
                            </Button>
                        )}
                    </div>
                    <p className="text-xs text-muted-foreground">
                        {session.mode === 'LEARNING'
                            ? 'Bỏ qua được tính là một lần trả lời sai. Dùng gợi ý thì câu đúng nhận ít XP hơn.'
                            : 'Sai hoặc bỏ qua mất một mạng; hết giờ không mất mạng nhưng câu không có XP.'}
                    </p>
                </Block>
            )}
        </Page>
    );
}
