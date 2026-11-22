import { AnalyticsPanel } from '@/components/exams';
import type { Analytics } from '@/types/exams';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionError, ReasonDialog } from '@/components/learning/actions';
import { Records } from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Field, Page, selectClass } from '@/components/page';
import type { CourseOption, ReportSection, Snapshot } from '@/types/learning';
import { localTime, percentage } from '@/types/learning';

type Student = {
    id: number;
    name: string | null;
    used: number;
    limit: number;
    attempts: { no: number; percent: string | null; url: string }[];
    grant_url: string | null;
};
type QuestionMetric = {
    quiz_question_id: number;
    position: number;
    finalized_attempts: number;
    incorrect_attempts: number;
    incorrect_percent: string;
    blank_attempts: number;
};
type Summary = {
    average_percent: string | null;
    most_incorrect_questions: QuestionMetric[];
};
type Props = {
    title: string;
    url: string;
    versions: CourseOption[];
    quizzes: { id: number; title: string | null }[];
    version: number | null;
    quiz: number | null;
    students: Student[];
    summary: ReportSection<Snapshot<Summary>>;
    analytics: Analytics | null;
    export_url: string | null;
};

export default function QuizReport({
    title,
    url,
    versions,
    quizzes,
    version,
    quiz,
    students,
    summary,
    analytics,
    export_url,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const failure = 'Không tải được báo cáo quiz.';
    const snapshot = summary.data;
    const quizTitle = quizzes.find((item) => item.id === quiz)?.title;
    function filter(params: { version: number; quiz?: number }) {
        setLoading(true);
        setLoadError(false);
        router.get(url, params, {
            only: [
                'version',
                'quiz',
                'quizzes',
                'summary',
                'students',
                'analytics',
                'export_url',
            ],
            preserveScroll: true,
            preserveState: true,
            onFinish: () => setLoading(false),
            onNetworkError: () => {
                setLoadError(true);
                return false;
            },
        });
    }

    return (
        <Page
            title="Báo cáo quiz"
            description={title}
            className="min-w-0 wrap-anywhere"
        >
            <Head title="Báo cáo quiz" />
            <Block className="grid min-w-0 gap-4 p-4 sm:p-6 lg:grid-cols-2">
                <Field label="Phiên bản" htmlFor="quiz-report-version">
                    <select
                        id="quiz-report-version"
                        className={selectClass + ' w-full'}
                        value={version ?? ''}
                        disabled={loading || versions.length === 0}
                        onChange={({ target }) =>
                            filter({ version: Number(target.value) })
                        }
                    >
                        {versions.length === 0 && (
                            <option value="">Chưa có phiên bản</option>
                        )}
                        {versions.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.title}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Quiz" htmlFor="quiz-report-quiz">
                    <select
                        id="quiz-report-quiz"
                        className={selectClass + ' w-full'}
                        value={quiz ?? ''}
                        disabled={loading || !version || quizzes.length === 0}
                        onChange={({ target }) => {
                            if (version)
                                filter({ version, quiz: Number(target.value) });
                        }}
                    >
                        {quizzes.length === 0 && (
                            <option value="">Chưa có quiz</option>
                        )}
                        {quizzes.map((item) => (
                            <option key={item.id} value={item.id}>
                                {item.title ?? `Quiz ${item.id}`}
                            </option>
                        ))}
                    </select>
                </Field>
            </Block>
            <div
                className="grid min-w-0 gap-4"
                aria-live="polite"
                aria-busy={loading}
            >
                {loading && <p role="status">Đang tải báo cáo quiz…</p>}
                {(loadError || summary.state === 'error') && (
                    <ActionError message={failure} />
                )}
                {!loading && !quiz && (
                    <Block>Phiên bản này chưa có quiz bài học.</Block>
                )}
                {!loading && quiz && summary.state !== 'error' && (
                    <>
                        <Block tone="surface" className="grid gap-2 p-4 sm:p-6">
                            <h2 className="font-semibold">Điểm trung bình</h2>
                            <p className="text-2xl font-bold">
                                {snapshot?.value.average_percent == null
                                    ? 'Chưa đủ dữ liệu'
                                    : percentage(
                                          snapshot.value.average_percent,
                                      )}
                            </p>
                            {snapshot && (
                                <p className="text-sm">
                                    Cập nhật lúc{' '}
                                    {localTime(snapshot.computed_at)}
                                </p>
                            )}
                        </Block>
                        <section className="grid min-w-0 gap-3">
                            <h2 className="font-semibold">
                                Câu sai nhiều nhất
                            </h2>
                            <Records
                                title="Câu sai nhiều nhất"
                                rows={
                                    snapshot?.value.most_incorrect_questions ??
                                    []
                                }
                                rowKey={(row) => row.quiz_question_id}
                                empty="Chưa đủ dữ liệu."
                                columns={[
                                    {
                                        label: 'Câu hỏi',
                                        cell: (row) => (
                                            <strong>Câu {row.position}</strong>
                                        ),
                                    },
                                    {
                                        label: 'Tổng lượt chốt',
                                        cell: (row) => row.finalized_attempts,
                                    },
                                    {
                                        label: 'Số lượt sai',
                                        cell: (row) => row.incorrect_attempts,
                                    },
                                    {
                                        label: 'Tỷ lệ sai',
                                        cell: (row) =>
                                            percentage(row.incorrect_percent),
                                    },
                                    {
                                        label: 'Số lượt bỏ trống',
                                        cell: (row) => row.blank_attempts,
                                    },
                                ]}
                            />
                        </section>
                    </>
                )}
            </div>
            {!loading && quiz && (
                <section className="grid min-w-0 gap-3">
                    <h2 className="font-semibold">
                        Học viên, kết quả lượt và cấp thêm lượt
                    </h2>
                    <Records
                        title="Học viên và số lượt"
                        rows={students}
                        rowKey={(row) => row.id}
                        empty="Không có học viên đủ điều kiện cấp thêm lượt."
                        columns={[
                            {
                                label: 'Học viên',
                                cell: (row) => row.name ?? '—',
                            },
                            {
                                label: 'Đã dùng / tối đa',
                                cell: (row) => `${row.used}/${row.limit} lượt`,
                            },
                            {
                                label: 'Lượt đã chốt',
                                cell: (row) =>
                                    row.attempts.length ? (
                                        <span className="flex flex-wrap gap-2">
                                            {row.attempts.map((item) => (
                                                <Link
                                                    key={item.no}
                                                    href={item.url}
                                                    className="underline underline-offset-4"
                                                >
                                                    Lượt {item.no}:{' '}
                                                    {percentage(item.percent)}
                                                </Link>
                                            ))}
                                        </span>
                                    ) : (
                                        '—'
                                    ),
                            },
                            {
                                label: 'Thao tác',
                                cell: (row) =>
                                    row.grant_url ? (
                                        <ReasonDialog
                                            url={row.grant_url}
                                            label="Cấp thêm lượt"
                                            subject={[row.name, quizTitle]
                                                .filter(Boolean)
                                                .join(' · ')}
                                        />
                                    ) : (
                                        <span className="text-muted-foreground">
                                            Chưa đủ điều kiện
                                        </span>
                                    ),
                            },
                        ]}
                    />
                </section>
            )}
            {analytics && (
                <AnalyticsPanel data={analytics} exportUrl={export_url} />
            )}
            <OrderReload
                only={['summary', 'students', 'analytics']}
                label="Tải lại báo cáo"
                failure={failure}
                onSuccess={() => setLoadError(false)}
            />
        </Page>
    );
}
