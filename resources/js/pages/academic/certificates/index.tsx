import { Form, Head } from '@inertiajs/react';
import {
    ActionError,
    ReasonDialog,
    RecordSearch,
} from '@/components/learning/actions';
import {
    LearningBadge,
    Pagination,
    Records,
} from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/academic/certificates';
import type { Certificate, Paginator } from '@/types/learning';
import { localDate, pdfLabels } from '@/types/learning';

type Row = Certificate & {
    holder: string;
    revoke_url: string | null;
    retry_url: string | null;
};

export default function Certificates({
    certificates,
    q,
}: {
    certificates: Paginator<Row>;
    q: string;
}) {
    return (
        <Page
            title="Quản lý chứng nhận"
            description="Tra cứu theo mã hoặc tên học viên, tải lại PDF bị lỗi và thu hồi chứng nhận cấp sai."
            className="min-w-0"
        >
            <Head title="Quản lý chứng nhận" />
            <Block className="p-4 sm:p-6">
                <RecordSearch
                    form={index.form()}
                    label="Tìm theo số chứng nhận hoặc họ tên"
                    value={q}
                />
            </Block>
            <Records
                title="Chứng nhận"
                rows={certificates.data}
                rowKey={(row) => row.number}
                empty="Chưa có chứng nhận."
                columns={[
                    {
                        label: 'Số chứng nhận',
                        cell: (row) => <strong>{row.number}</strong>,
                    },
                    {
                        label: 'Họ tên và khóa học',
                        cell: (row) => (
                            <>
                                <strong>{row.holder}</strong>
                                <p>{row.course}</p>
                            </>
                        ),
                    },
                    {
                        label: 'Ngày cấp',
                        cell: (row) => localDate(row.issued_on),
                    },
                    {
                        label: 'Trạng thái',
                        cell: (row) => (
                            <LearningBadge
                                attention={row.status === 'Đã thu hồi'}
                            >
                                {row.status}
                            </LearningBadge>
                        ),
                    },
                    {
                        label: 'PDF',
                        cell: (row) =>
                            pdfLabels[row.pdf_status] ?? row.pdf_status,
                    },
                    {
                        label: 'Thao tác',
                        cell: (row) =>
                            row.revoke_url || row.retry_url ? (
                                <details>
                                    <summary className="cursor-pointer font-medium">
                                        Thao tác khác
                                    </summary>
                                    <div className="mt-2 grid justify-items-start gap-2">
                                        {row.retry_url && (
                                            <Form
                                                action={row.retry_url}
                                                method="post"
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                            >
                                                {({ processing, errors }) => (
                                                    <div className="grid gap-2">
                                                        <Button
                                                            variant="outline"
                                                            size="sm"
                                                            type="submit"
                                                            disabled={
                                                                processing
                                                            }
                                                        >
                                                            {processing
                                                                ? 'Đang gửi…'
                                                                : 'Tạo lại PDF'}
                                                        </Button>
                                                        <ActionError
                                                            message={
                                                                errors.general
                                                            }
                                                        />
                                                    </div>
                                                )}
                                            </Form>
                                        )}
                                        {row.revoke_url && (
                                            <ReasonDialog
                                                url={row.revoke_url}
                                                label="Thu hồi"
                                                subject={`${row.number} · ${row.holder}`}
                                                revoke
                                            />
                                        )}
                                    </div>
                                </details>
                            ) : (
                                '—'
                            ),
                    },
                ]}
            />
            <Pagination page={certificates} />
            <OrderReload
                only={['certificates']}
                label="Tải lại chứng nhận"
                failure="Không tải được danh sách chứng nhận."
            />
        </Page>
    );
}
