import { Form, Head, router, useForm } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';
import { ActionError } from '@/components/learning/actions';
import { hasAnswer, QuestionInput } from '@/components/learning/question-input';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { show, submit } from '@/routes/student/attempts';
import { update } from '@/routes/student/attempts/answers';
import type { AnswerValue, Attempt, Question } from '@/types/learning';
import { localTime, minuteSecond } from '@/types/learning';

const leaveMessage =
    'Bạn đang làm quiz. Đồng hồ vẫn chạy và câu chưa lưu sẽ bị mất. Rời trang?';

type Props = {
    attempt: Attempt;
    questions: Question[];
    submit_url: string;
    server_time: string;
};

export default function TakeQuiz(props: Props) {
    return (
        <QuizSession
            key={`${props.attempt.id}-${props.server_time}`}
            {...props}
        />
    );
}

function QuizSession({ attempt, questions, server_time }: Props) {
    const initialMs = Date.parse(attempt.deadline_at) - Date.parse(server_time);
    const [remaining, setRemaining] = useState(initialMs);
    const [ending, setEnding] = useState(false);
    const [pending, setPending] = useState<number | null>(null);
    const [submitError, setSubmitError] = useState('');
    const pendingRef = useRef<number | null>(null);
    const dirty = useRef(new Set<number>());
    const allowNavigation = useRef(false);
    const expired = remaining < 0;

    useEffect(() => {
        const startedAt = performance.now();
        let refreshSent = false;
        function readAttempt() {
            if (allowNavigation.current) return;
            allowNavigation.current = true;
            router.get(
                show(attempt.id).url,
                {},
                {
                    replace: true,
                    preserveScroll: true,
                    onStart: () => setEnding(true),
                    onFinish: () => {
                        allowNavigation.current = false;
                        setEnding(false);
                    },
                    onNetworkError: () => {
                        setSubmitError(
                            'Không tải được lượt. Hãy kết nối lại và tải lại trang.',
                        );
                        return false;
                    },
                },
            );
        }
        function tick() {
            const left = initialMs - (performance.now() - startedAt);
            setRemaining(left < 0 ? -1 : Math.ceil(left / 1_000) * 1_000);
            if (
                left < 0 &&
                !refreshSent &&
                !allowNavigation.current &&
                pendingRef.current === null
            ) {
                refreshSent = true;
                readAttempt();
            }
        }
        function reconnect() {
            if (pendingRef.current === null) readAttempt();
        }
        function beforeUnload(event: BeforeUnloadEvent) {
            if (allowNavigation.current) return;
            event.preventDefault();
            event.returnValue = '';
        }
        const unbind = router.on('before', ({ detail }) => {
            if (
                detail.visit.method === 'get' &&
                !detail.visit.prefetch &&
                !allowNavigation.current &&
                detail.visit.url.pathname !== show(attempt.id).url
            )
                return window.confirm(leaveMessage);
        });
        const interval = window.setInterval(tick, 250);
        window.addEventListener('beforeunload', beforeUnload);
        window.addEventListener('online', reconnect);
        document.addEventListener('visibilitychange', tick);
        return () => {
            unbind();
            window.clearInterval(interval);
            window.removeEventListener('beforeunload', beforeUnload);
            window.removeEventListener('online', reconnect);
            document.removeEventListener('visibilitychange', tick);
        };
    }, [attempt.id, initialMs, server_time]);

    function trackSave(id: number | null) {
        pendingRef.current = id;
        setPending(id);
    }

    return (
        <Page
            title={`Lượt quiz ${attempt.attempt_no}`}
            art={false}
            className="min-w-0"
        >
            <Head title="Làm quiz" />
            <div className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(14rem,19rem)]">
                <Block
                    tone="surface"
                    className="grid gap-2 p-4 lg:sticky lg:top-4 lg:col-start-2 lg:row-start-1"
                >
                    <h2 className="text-sm font-medium">
                        {attempt.status_label}
                    </h2>
                    <p
                        role="timer"
                        aria-label="Thời gian còn lại"
                        className="text-5xl font-extrabold tracking-tight text-amber tabular-nums"
                    >
                        {minuteSecond(
                            Math.max(0, Math.ceil(remaining / 1_000)),
                        )}
                    </p>
                    <p className="text-sm">
                        Hạn nộp {localTime(attempt.deadline_at)}
                    </p>
                    <p role="status" className="text-sm">
                        {expired
                            ? 'Đã hết giờ. Đang kiểm tra kết quả…'
                            : remaining <= 60_000
                              ? 'Còn tối đa 1 phút.'
                              : ''}
                    </p>
                </Block>
                <div className="grid min-w-0 gap-4 lg:col-start-1 lg:row-start-1">
                    {questions.map((question, index) => (
                        <Answer
                            key={question.id}
                            question={question}
                            number={index + 1}
                            attemptId={attempt.id}
                            locked={expired || ending || pending !== null}
                            onDirty={(value) => {
                                if (value) dirty.current.add(question.id);
                                else dirty.current.delete(question.id);
                            }}
                            onSave={trackSave}
                            onClosed={() => {
                                allowNavigation.current = true;
                                router.visit(attempt.url, {
                                    replace: true,
                                    onFinish: () => {
                                        allowNavigation.current = false;
                                    },
                                });
                            }}
                        />
                    ))}
                    {questions.length === 0 && <Block>Chưa có câu hỏi.</Block>}
                    <Block className="grid justify-items-start gap-3 p-4">
                        <Form
                            {...submit.form(attempt.id)}
                            options={{ preserveScroll: true }}
                            onBefore={() =>
                                pendingRef.current === null &&
                                (dirty.current.size === 0 ||
                                    window.confirm(
                                        'Một số câu trả lời chưa được lưu. Nộp các câu đã lưu?',
                                    ))
                            }
                            onStart={() => {
                                allowNavigation.current = true;
                                setEnding(true);
                                setSubmitError('');
                            }}
                            onFinish={() => {
                                allowNavigation.current = false;
                                setEnding(false);
                            }}
                            onNetworkError={() => {
                                setSubmitError(
                                    'Chưa xác nhận được kết quả nộp. Hãy tải lại lượt.',
                                );
                                return false;
                            }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid justify-items-start gap-2">
                                    <Button
                                        type="submit"
                                        disabled={
                                            expired ||
                                            ending ||
                                            pending !== null ||
                                            processing
                                        }
                                    >
                                        {processing ? 'Đang nộp…' : 'Nộp bài'}
                                    </Button>
                                    <ActionError
                                        message={errors.general ?? submitError}
                                    />
                                </div>
                            )}
                        </Form>
                        <Button
                            type="button"
                            size="sm"
                            variant="outline"
                            disabled={pending !== null || ending}
                            onClick={() => {
                                if (
                                    dirty.current.size > 0 &&
                                    !window.confirm(
                                        'Câu chưa lưu sẽ bị mất. Tải lại lượt?',
                                    )
                                )
                                    return;
                                router.get(
                                    show(attempt.id).url,
                                    {},
                                    {
                                        replace: true,
                                        preserveScroll: true,
                                        onStart: () => setEnding(true),
                                        onFinish: () => setEnding(false),
                                        onNetworkError: () => {
                                            setSubmitError(
                                                'Không tải được lượt quiz.',
                                            );
                                            return false;
                                        },
                                    },
                                );
                            }}
                        >
                            {ending ? 'Đang tải…' : 'Tải lại lượt'}
                        </Button>
                    </Block>
                </div>
            </div>
        </Page>
    );
}

function Answer({
    question,
    number,
    attemptId,
    locked,
    onDirty,
    onSave,
    onClosed,
}: {
    question: Question;
    number: number;
    attemptId: number;
    locked: boolean;
    onDirty: (dirty: boolean) => void;
    onSave: (id: number | null) => void;
    onClosed: () => void;
}) {
    const form = useForm<{ answer: AnswerValue }>({
        answer: question.answer,
    });
    const savedAnswer = useRef(question.answer);
    const [requestError, setRequestError] = useState('');
    const error = form.errors.answer ?? requestError;
    const errorId = `answer-${question.id}-error`;
    return (
        <form
            onSubmit={(event) => {
                event.preventDefault();
                if (locked || form.processing || !form.isDirty) return;
                setRequestError('');
                form.submit(
                    update({ attempt: attemptId, quizQuestion: question.id }),
                    {
                        only: ['attempt'],
                        preserveScroll: true,
                        onStart: () => onSave(question.id),
                        onFinish: () => onSave(null),
                        onSuccess: () => {
                            savedAnswer.current = form.data.answer;
                            form.setDefaults();
                            onDirty(false);
                        },
                        onError: (errors) => {
                            if (errors.answer?.includes('Lượt đã chốt'))
                                onClosed();
                        },
                        onNetworkError: () => {
                            setRequestError(
                                'Chưa lưu được câu trả lời. Hãy thử lại.',
                            );
                            return false;
                        },
                    },
                );
            }}
        >
            <fieldset
                disabled={locked || form.processing}
                aria-describedby={error ? errorId : undefined}
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
                                JSON.stringify(savedAnswer.current),
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
                    <p
                        id={errorId}
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
            </fieldset>
        </form>
    );
}
