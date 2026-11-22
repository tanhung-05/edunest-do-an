import { Head, Link } from '@inertiajs/react';
import { AcademicStats } from '@/components/academic-stats';
import type { DashboardStats } from '@/components/academic-stats';
import { OrderList, RefundList } from '@/components/order/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import academic from '@/routes/academic';
import type { CourseRow } from '@/types/course';
import { dateVi } from '@/types/course';
import type { OrderRow, PaymentRow } from '@/types/order';

type QueueItem = {
    version_id: number;
    course_id: number;
    title: string;
    version_no: number;
    submitted_at: string | null;
};

export default function AcademicDashboard({
    teacherCount,
    reviewQueue,
    overdueCourses,
    ordersNeedingAttention,
    paymentsNeedingRefund,
    stats,
    export_url,
}: {
    stats: DashboardStats;
    export_url: string;
    teacherCount: number;
    reviewQueue: QueueItem[];
    overdueCourses: CourseRow[];
    ordersNeedingAttention: OrderRow[];
    paymentsNeedingRefund: PaymentRow[];
}) {
    return (
        <Page
            title="Tổng quan Giáo vụ"
            description="Doanh thu, đơn, ghi danh và tỷ lệ hoàn thành theo khoảng ngày; các hàng chờ cần xử lý trong ngày."
        >
            <Head title="Tổng quan Giáo vụ" />
            <AcademicStats
                stats={stats}
                exportUrl={export_url}
                baseUrl={academic.dashboard().url}
            />
            <div className="grid gap-4 md:grid-cols-4">
                <Stat
                    label="Chờ duyệt"
                    value={reviewQueue.length}
                    tone="surface"
                />
                <Stat
                    label="Quá hạn soạn"
                    value={overdueCourses.length}
                    tone={overdueCourses.length > 0 ? 'accent' : 'plain'}
                />
                <Stat label="Giáo viên" value={teacherCount} />
                <Block>
                    <h2 className="text-lg font-bold">Tài khoản giáo viên</h2>
                    <Link
                        href={academic.teachers.index()}
                        className="mt-2 inline-block font-medium underline"
                    >
                        Cấp tài khoản, lập hồ sơ
                    </Link>
                </Block>
            </div>
            <div className="grid gap-4">
                <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Đơn cần xử lý</h2>
                    <div aria-live="polite">
                        <OrderList
                            rows={ordersNeedingAttention}
                            label="Đơn cần xử lý"
                            empty="Không có đơn cần xử lý."
                            academic
                            queue
                        />
                    </div>
                    <div className="flex flex-wrap items-start gap-2">
                        <Button variant="outline" size="sm" asChild>
                            <Link href={academic.orders.index()}>
                                Xem hàng chờ đơn hàng
                            </Link>
                        </Button>
                        <OrderReload
                            only={['ordersNeedingAttention']}
                            label="Tải lại đơn cần xử lý"
                            failure="Không tải được đơn cần xử lý."
                        />
                    </div>
                </Block>
                <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Khoản cần hoàn</h2>
                    <div aria-live="polite">
                        <RefundList rows={paymentsNeedingRefund} />
                    </div>
                    <OrderReload
                        only={['paymentsNeedingRefund']}
                        label="Tải lại khoản cần hoàn"
                        failure="Không tải được khoản cần hoàn."
                    />
                </Block>
            </div>
            <div className="grid gap-4 lg:grid-cols-2">
                <Block>
                    <h2 className="mb-3 text-lg font-bold">Hàng chờ duyệt</h2>
                    {reviewQueue.length === 0 && (
                        <p className="text-muted-foreground">
                            Không có bản nào chờ duyệt.
                        </p>
                    )}
                    <ul className="divide-y">
                        {reviewQueue.map((v) => (
                            <li
                                key={v.version_id}
                                className="flex justify-between gap-2 py-2"
                            >
                                <Link
                                    href={academic.courseVersions.show(
                                        v.version_id,
                                    )}
                                    className="font-medium underline"
                                >
                                    {v.title} · Bản {v.version_no}
                                </Link>
                                <span className="text-sm text-muted-foreground">
                                    {dateVi(v.submitted_at)}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Block>
                <Block>
                    <h2 className="mb-3 text-lg font-bold">
                        Khóa quá hạn soạn
                    </h2>
                    {overdueCourses.length === 0 && (
                        <p className="text-muted-foreground">
                            Không có khóa quá hạn.
                        </p>
                    )}
                    <ul className="divide-y">
                        {overdueCourses.map((c) => (
                            <li
                                key={c.id}
                                className="flex justify-between gap-2 py-2"
                            >
                                <Link
                                    href={academic.courses.show(c.id)}
                                    className="font-medium underline"
                                >
                                    {c.title}
                                </Link>
                                <span className="text-sm text-muted-foreground">
                                    {c.author} · hạn {c.due_date}
                                </span>
                            </li>
                        ))}
                    </ul>
                </Block>
            </div>
        </Page>
    );
}
