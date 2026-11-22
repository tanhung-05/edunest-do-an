import { Head, Link } from '@inertiajs/react';
import { LearningBadge, Records } from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Page, Stat } from '@/components/page';
import type { Attempt } from '@/types/learning';
import { localTime, percentage } from '@/types/learning';

export default function History({
    title,
    attempts,
    best_percent,
}: {
    title: string | null;
    attempts: Attempt[];
    best_percent: string | null;
}) {
    return (
        <Page
            title="Lịch sử lượt"
            description={title ?? undefined}
            className="min-w-0 wrap-anywhere"
        >
            <Head title="Lịch sử lượt" />
            <div className="grid gap-4" aria-live="polite">
                <Stat
                    label="Điểm ghi nhận"
                    value={percentage(best_percent)}
                    tone="surface"
                />
                <Records
                    title="Các lượt đã chốt"
                    rows={attempts}
                    rowKey={(row) => row.id}
                    empty="Chưa có lượt đã chốt."
                    columns={[
                        {
                            label: 'Lượt',
                            cell: (row) => (
                                <Link
                                    href={row.url}
                                    className="font-semibold underline underline-offset-4"
                                >
                                    Lượt {row.attempt_no}
                                </Link>
                            ),
                        },
                        {
                            label: 'Thời điểm chốt',
                            cell: (row) => localTime(row.closed_at),
                        },
                        {
                            label: 'Trạng thái',
                            cell: (row) => (
                                <>
                                    <LearningBadge>
                                        {row.status_label}
                                    </LearningBadge>
                                    {row.close_reason && (
                                        <p className="mt-1">
                                            {row.close_reason}
                                        </p>
                                    )}
                                </>
                            ),
                        },
                        {
                            label: 'Điểm',
                            cell: (row) => percentage(row.percent),
                        },
                        {
                            label: 'Kết quả',
                            cell: (row) =>
                                row.passed === null
                                    ? '—'
                                    : row.passed
                                      ? 'Đạt'
                                      : 'Chưa đạt',
                        },
                    ]}
                />
            </div>
            <OrderReload
                only={['attempts', 'best_percent']}
                label="Tải lại lịch sử"
                failure="Không tải được lịch sử lượt."
            />
        </Page>
    );
}
