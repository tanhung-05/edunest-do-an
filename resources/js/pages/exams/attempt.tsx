import { Form, Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { hasAnswer, QuestionInput } from '@/components/learning/question-input';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { postJson } from '@/lib/http';
import type { AnswerValue, Question } from '@/types/learning';
import { localTime, minuteSecond } from '@/types/learning';

type Props = {
    event: { id: number; title: string; url: string };
    attempt: { id: number; started_at: string; deadline_at: string };
    questions: Question[];
    submit_url: string;
    tab_leave_url: string;
    ask_consent: boolean;
    server_time: string;
};

/** MH-HV-14: làm bài thi thử — đồng hồ theo giờ máy chủ, lưu từng câu, ghi rời tab (CR-77), nộp kèm đồng ý công khai tên. */
export default function ExamAttempt(props: Props) {
    return (
        <Session key={`${props.attempt.id}-${props.server_time}`} {...props} />
    );
}

function Session({
    event,
    attempt,
    questions,
    submit_url,
    tab_leave_url,
    ask_consent,
    server_time,
}: Props) {
    const initialMs = Date.parse(attempt.deadline_at) - Date.parse(server_time);
    const [remaining, setRemaining] = useState(initialMs);
    const [pending, setPending] = useState<number | null>(null);
    const [leaves, setLeaves] = useState(0);
    const dirty = useRef(new Set<number>());
    const expired = remaining < 0;

    useEffect(() => {
        const startedAt = performance.now();
        let reloaded = false;
        let hiddenAt: number | null = null;
        const tick = () => {
            const left = initialMs - (performance.now() - startedAt);
            setRemaining(left < 0 ? -1 : Math.ceil(left / 1000) * 1000);
            if (left < 0 && !reloaded) {
                reloaded = true;
                router.reload();
            }
        };
        const visibility = () => {
            if (document.visibilityState === 'hidden') {
                hiddenAt = performance.now();
            } else if (hiddenAt !== null) {
                const ms = Math.round(performance.now() - hiddenAt);
                hiddenAt = null;
                setLeaves((n) => n + 1);
                void postJson(tab_leave_url, { duration_ms: ms });
            }
            tick();
        };
        const interval = window.setInterval(tick, 250);
        document.addEventListener('visibilitychange', visibility);

        return () => {
            window.clearInterval(interval);
            document.removeEventListener('visibilitychange', visibility);
        };
    }, [initialMs, tab_leave_url]);

    return (
        <Page
            title={event.title}
            eyebrow="Đang làm bài thi thử"
            className="min-w-0"
        >
            <Head title="Làm bài thi" />
            <div className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,19rem)]">
                <Block
                    tone="surface"
                    className="grid gap-2 p-4 lg:sticky lg:top-4 lg:col-start-2 lg:row-start-1"
                >
                    <p
                        role="timer"
                        aria-label="Thời gian còn lại"
                        className="text-5xl font-extrabold tracking-tight text-amber tabular-nums"
                    >
                        {minuteSecond(Math.max(0, Math.ceil(remaining / 1000)))}
                    </p>
                    <p className="text-sm">
                        Hạn nộp {localTime(attempt.deadline_at)}
                    </p>
                    <p role="status" className="text-sm">
                        {expired
                            ? 'Đã hết giờ. Đang chốt bài…'
                            : remaining <= 60_000
                              ? 'Còn tối đa 1 phút.'
                              : ''}
                    </p>
                    {leaves > 0 && (
                        <p className="text-xs opacity-80">
                            Đã ghi nhận {leaves} lần rời trang thi.
                        </p>
                    )}
                </Block>
                <div className="grid min-w-0 gap-4 lg:col-start-1 lg:row-start-1">
                    {questions.map((q, i) => (
                        <ExamAnswer
                            key={q.id}
                            question={q}
                            number={i + 1}
                            locked={expired || pending !== null}
                            onDirty={(d) =>
                                d
                                    ? dirty.current.add(q.id)
                                    : dirty.current.delete(q.id)
                            }
                            onSave={setPending}
                        />
                    ))}
                    <Block className="p-4">
                        <Form
                            action={submit_url}
                            method="post"
                            onBefore={() =>
                                window.confirm(
                                    dirty.current.size > 0
                                        ? 'Một số câu chưa lưu sẽ không được tính. Nộp bài?'
                                        : 'Nộp bài? Sau khi nộp không làm lại được.',
                                )
                            }
                        >
                            {({ processing, errors }) => (
                                <div className="grid justify-items-start gap-3">
                                    {ask_consent && (
                                        <label className="flex items-center gap-2 text-sm">
                                            <input
                                                type="checkbox"
                                                name="consent"
                                                value="1"
                                                className="size-4 accent-sky-600"
                                            />
                                            Đồng ý công khai tên nếu vào top 10
                                        </label>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={
                                            expired ||
                                            pending !== null ||
                                            processing
                                        }
                                    >
                                        {processing ? 'Đang nộp…' : 'Nộp bài'}
                                    </Button>
                                    {(errors.attempt || errors.general) && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {errors.attempt ?? errors.general}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    </Block>
                </div>
            </div>
        </Page>
    );
}

function ExamAnswer({
    question,
    number,
    locked,
    onDirty,
    onSave,
}: {
    question: Question;
    number: number;
    locked: boolean;
    onDirty: (dirty: boolean) => void;
    onSave: (id: number | null) => void;
}) {
    const form = useForm<{ answer: AnswerValue }>({ answer: question.answer });
    const saved = useRef(question.answer);
    const [netError, setNetError] = useState('');
    const error = form.errors.answer ?? netError;

    return (
        <form
            onSubmit={(e) => {
                e.preventDefault();
                if (locked || form.processing || !form.isDirty) return;
                setNetError('');
                form.patch(question.save_url, {
                    preserveScroll: true,
                    only: ['attempt'],
                    onStart: () => onSave(question.id),
                    onFinish: () => onSave(null),
                    onSuccess: () => {
                        saved.current = form.data.answer;
                        form.setDefaults();
                        onDirty(false);
                    },
                    onNetworkError: () => {
                        setNetError('Chưa lưu được câu trả lời. Hãy thử lại.');
                        return false;
                    },
                });
            }}
        >
            <fieldset
                disabled={locked || form.processing}
                className="grid min-w-0 gap-3 rounded-2xl border border-border bg-card p-5 shadow-soft sm:p-6"
            >
                <legend className="max-w-full rounded-full bg-accent px-3 py-1 text-xs font-semibold">
                    Câu {number}
                </legend>
                <p className="text-lg leading-snug font-bold wrap-anywhere whitespace-pre-wrap">
                    {question.stem ?? 'Câu hỏi'}
                </p>
                <QuestionInput
                    question={question}
                    value={form.data.answer}
                    onChange={(answer) => {
                        form.setData('answer', answer);
                        onDirty(
                            JSON.stringify(answer) !==
                                JSON.stringify(saved.current),
                        );
                    }}
                />
                <div className="flex flex-wrap items-center gap-3">
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={!form.isDirty}
                    >
                        {form.processing ? 'Đang lưu…' : 'Lưu câu'}
                    </Button>
                    <span
                        role="status"
                        className="text-xs text-muted-foreground"
                    >
                        {form.processing
                            ? ''
                            : form.isDirty
                              ? 'Chưa lưu'
                              : form.wasSuccessful || hasAnswer(question.answer)
                                ? 'Đã lưu'
                                : 'Chưa trả lời'}
                    </span>
                </div>
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
            </fieldset>
        </form>
    );
}
