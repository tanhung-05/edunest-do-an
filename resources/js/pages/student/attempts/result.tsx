import { Head, Link } from '@inertiajs/react';
import { AttemptAnswers } from '@/components/learning/attempt-answers';
import { LearningBadge } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import type { Attempt, Question } from '@/types/learning';
import { localTime, percentage } from '@/types/learning';

type Props = {
    attempt: Attempt;
    best_percent: string | null;
    answers_visible: boolean;
    questions: Question[];
    quiz_url: string;
    history_url: string;
    next_lesson_url: string | null;
    can_start: boolean;
};

export default function Result({
    attempt,
    best_percent,
    answers_visible,
    questions,
    quiz_url,
    history_url,
    next_lesson_url,
    can_start,
}: Props) {
    return (
        <Page title={`Kết quả lượt ${attempt.attempt_no}`} className="min-w-0">
            <Head title="Kết quả quiz" />
            <div
                className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(15rem,20rem)_minmax(0,1fr)]"
                aria-live="polite"
            >
                <div className="grid min-w-0 gap-4">
                    <Block tone="surface" className="grid gap-3 p-4 sm:p-6">
                        <div className="flex flex-wrap gap-2">
                            <LearningBadge>
                                {attempt.status_label}
                            </LearningBadge>
                            {attempt.passed !== null && (
                                <LearningBadge attention={!attempt.passed}>
                                    {attempt.passed ? 'Đạt' : 'Chưa đạt'}
                                </LearningBadge>
                            )}
                        </div>
                        <dl className="grid gap-3 text-sm">
                            <OrderFact label="Điểm lượt này">
                                <span className="text-3xl font-bold">
                                    {percentage(attempt.percent)}
                                </span>
                            </OrderFact>
                            {attempt.score_earned !== null &&
                                attempt.score_max !== null && (
                                    <OrderFact label="Điểm trên tổng">
                                        {Number(
                                            attempt.score_earned,
                                        ).toLocaleString('vi-VN')}{' '}
                                        /{' '}
                                        {Number(
                                            attempt.score_max,
                                        ).toLocaleString('vi-VN')}
                                    </OrderFact>
                                )}
                            <OrderFact label="Điểm ghi nhận">
                                {percentage(best_percent)}
                            </OrderFact>
                            {attempt.correct_count !== null && (
                                <OrderFact label="Số câu đúng">
                                    {attempt.correct_count}/
                                    {attempt.question_count}
                                </OrderFact>
                            )}
                            <OrderFact label="Thời điểm chốt">
                                {localTime(attempt.closed_at)}
                            </OrderFact>
                            {attempt.close_reason && (
                                <OrderFact label="Lý do chốt">
                                    {attempt.close_reason}
                                </OrderFact>
                            )}
                        </dl>
                    </Block>
                    <div className="flex flex-wrap gap-2">
                        {can_start && (
                            <Button asChild>
                                <Link href={quiz_url}>Làm lượt mới</Link>
                            </Button>
                        )}
                        {next_lesson_url && (
                            <Button asChild>
                                <Link href={next_lesson_url}>
                                    Bài tiếp theo
                                </Link>
                            </Button>
                        )}
                        <Button asChild variant="outline">
                            <Link href={history_url}>Lịch sử lượt</Link>
                        </Button>
                    </div>
                </div>
                <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                    <h2 className="font-semibold">Đáp án và lời giải</h2>
                    {answers_visible ? (
                        <AttemptAnswers questions={questions} />
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Bạn có thể xem đáp án sau khi đạt quiz hoặc đã chốt
                            hết lượt.
                        </p>
                    )}
                </Block>
            </div>
            <OrderReload
                only={[
                    'attempt',
                    'best_percent',
                    'questions',
                    'answers_visible',
                    'can_start',
                    'next_lesson_url',
                ]}
                label="Tải lại kết quả"
                failure="Không tải được kết quả."
            />
        </Page>
    );
}
