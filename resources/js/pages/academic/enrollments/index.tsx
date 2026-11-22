import { Head } from '@inertiajs/react';
import { RecordSearch } from '@/components/learning/actions';
import { EnrollmentRecords } from '@/components/learning/enrollments';
import { Pagination } from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { index } from '@/routes/academic/enrollments';
import type { EnrollmentRow, Paginator } from '@/types/learning';

export default function Enrollments({
    enrollments,
    q,
}: {
    enrollments: Paginator<EnrollmentRow>;
    q: string;
}) {
    return (
        <Page
            title="Học viên và ghi danh"
            description="Ghi danh của mọi khóa kèm tiến độ bài bắt buộc. Mở hồ sơ học viên để xem chi tiết và lịch sử."
            className="min-w-0"
        >
            <Head title="Học viên và ghi danh" />
            <Block className="p-4 sm:p-6">
                <RecordSearch
                    form={index.form()}
                    label="Tìm học viên theo tên hoặc email"
                    value={q}
                />
            </Block>
            <EnrollmentRecords rows={enrollments.data} directory />
            <Pagination page={enrollments} />
            <OrderReload
                only={['enrollments']}
                label="Tải lại ghi danh"
                failure="Không tải được ghi danh."
            />
        </Page>
    );
}
