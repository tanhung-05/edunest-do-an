import { Head, Link } from '@inertiajs/react';
import { AttemptAnswers } from '@/components/learning/attempt-answers';
import { LearningBadge } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import type { Attempt, Question } from '@/types/learning';
import { localTime, percentage } from '@/types/learning';

type Props = {
    course: string;
    student: string | null;
    lesson: string | null;
    attempt: Attempt;
    questions: Question[];
    back_url: string;
};

export default function TeacherAttemptResult({
    course,
    student,
    lesson,
    attempt,
    questions,
    back_url,
}: Props) {
    return (
        <Page
            title={`Lượt ${attempt.attempt_no} của ${student ?? 'học viên'}`}
            className="min-w-0"
        >
            <Head title="Kết quả lượt quiz" />
            <div className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(15rem,20rem)_minmax(0,1fr)]">
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
                            <OrderFact label="Khóa học">{course}</OrderFact>
                            <OrderFact label="Bài kiểm tra">
                                {lesson ?? '—'}
                            </OrderFact>
                            <OrderFact label="Điểm lượt này">
                                <span className="text-3xl font-bold">
                                    {percentage(attempt.percent)}
                                </span>
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
                    <Button asChild variant="outline">
                        <Link href={back_url}>Về báo cáo quiz</Link>
                    </Button>
                </div>
                <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                    <h2 className="font-semibold">
                        Bài làm, đáp án và lời giải
                    </h2>
                    <AttemptAnswers questions={questions} />
                </Block>
            </div>
        </Page>
    );
}
