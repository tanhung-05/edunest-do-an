import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { ActionError } from '@/components/learning/actions';
import { RevenuePanel } from '@/components/learning/revenue';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index as enrollmentIndex } from '@/routes/academic/enrollments';
import { index } from '@/routes/academic/reports';
import type {
    CourseOption,
    ReportSection,
    Revenue,
    Snapshot,
} from '@/types/learning';
import { localTime, percentage } from '@/types/learning';

type Counts = ReportSection<Record<string, number>>;
type Completion = Snapshot<{
    total: number;
    completed: number;
    percent: string | null;
}>;
type Props = {
    courses: CourseOption[];
    course: number | null;
    revenue: ReportSection<Snapshot<Revenue>>;
    orders: Counts;
    enrollments: Counts;
    completion: ReportSection<Completion>;
};

const labels: Record<string, string> = {
    CHO_THANH_TOAN: 'Chờ thanh toán',
    DANG_CAP_QUYEN: 'Đang cấp quyền học',
    HOAN_TAT: 'Hoàn tất',
    HET_HAN: 'Hết hạn',
    DA_HUY: 'Đã hủy',
    CAN_XU_LY: 'Cần xử lý',
    CON_HAN: 'Còn hạn',
    DA_THU_HOI: 'Đã thu hồi',
};

function Totals({
    title,
    section,
    only,
}: {
    title: string;
    section: Counts;
    only: string;
}) {
    const data = section.data;
    const failure =
        only === 'orders'
            ? 'Không tải được số đơn.'
            : 'Không tải được số ghi danh.';
    return (
        <Block className="grid min-w-0 content-start gap-4 p-4 sm:p-6">
            <h2 className="font-semibold">{title}</h2>
            <div aria-live="polite">
                {data ? (
                    <>
                        <p className="text-3xl font-bold">
                            {Object.values(data)
                                .reduce((sum, total) => sum + total, 0)
                                .toLocaleString('vi-VN')}
                        </p>
                        <dl className="mt-3 grid grid-cols-2 gap-3">
                            {Object.entries(data).map(([status, total]) => (
                                <OrderFact
                                    key={status}
                                    label={labels[status] ?? status}
                                >
                                    {total.toLocaleString('vi-VN')}
                                </OrderFact>
                            ))}
                        </dl>
                    </>
                ) : (
                    <ActionError message={failure} />
                )}
            </div>
            <OrderReload
                only={[only]}
                label="Tải lại số liệu"
                failure={failure}
            />
        </Block>
    );
}

export default function Reports({
    courses,
    course,
    revenue,
    orders,
    enrollments,
    completion,
}: Props) {
    const [loading, setLoading] = useState(false);
    const [loadError, setLoadError] = useState(false);
    const value = completion.data?.value;
    const failure = 'Không tải được tỷ lệ hoàn thành.';

    return (
        <Page title="Báo cáo vận hành" className="min-w-0">
            <Head title="Báo cáo vận hành" />
            <div className="grid min-w-0 gap-4 lg:grid-cols-3">
                <RevenuePanel revenue={revenue} />
                <Totals
                    title="Số đơn hiện tại"
                    section={orders}
                    only="orders"
                />
                <Totals
                    title="Số ghi danh hiện tại"
                    section={enrollments}
                    only="enrollments"
                />
            </div>
            <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                <h2 className="font-semibold">Tỷ lệ hoàn thành theo khóa</h2>
                <div className="max-w-sm">
                    <Field label="Khóa học" htmlFor="report-course">
                        <select
                            id="report-course"
                            className={selectClass + ' w-full'}
                            value={course ?? ''}
                            disabled={loading}
                            onChange={({ target }) => {
                                setLoading(true);
                                setLoadError(false);
                                router.get(
                                    index.url(),
                                    target.value
                                        ? { course: target.value }
                                        : {},
                                    {
                                        only: ['course', 'completion'],
                                        preserveScroll: true,
                                        preserveState: true,
                                        onFinish: () => setLoading(false),
                                        onNetworkError: () => {
                                            setLoadError(true);
                                            return false;
                                        },
                                    },
                                );
                            }}
                        >
                            <option value="">Chọn khóa</option>
                            {courses.map((item) => (
                                <option key={item.id} value={item.id}>
                                    {item.title}
                                </option>
                            ))}
                        </select>
                    </Field>
                </div>
                <div aria-live="polite" className="grid gap-2">
                    {loading && (
                        <p className="text-sm">Đang tải tỷ lệ hoàn thành…</p>
                    )}
                    {(loadError || completion.state === 'error') && (
                        <ActionError message={failure} />
                    )}
                    {course && !loading && completion.state !== 'error' && (
                        <>
                            <p className="font-semibold">
                                {value?.percent == null
                                    ? 'Chưa đủ dữ liệu'
                                    : percentage(value.percent)}
                            </p>
                            {completion.data && value && (
                                <>
                                    <p className="text-sm">
                                        {value.completed}/{value.total} ghi danh
                                        hoàn thành
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Cập nhật lúc{' '}
                                        {localTime(completion.data.computed_at)}
                                    </p>
                                </>
                            )}
                        </>
                    )}
                </div>
                {course && (
                    <OrderReload
                        only={['completion']}
                        label="Tải lại tỷ lệ hoàn thành"
                        failure={failure}
                        onSuccess={() => setLoadError(false)}
                    />
                )}
            </Block>
            <Button asChild variant="outline" className="self-start">
                <Link href={enrollmentIndex()}>Học viên và ghi danh</Link>
            </Button>
        </Page>
    );
}
