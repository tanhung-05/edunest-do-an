import { Head, router } from '@inertiajs/react';
import { Award, BadgeCheck, FileDown } from 'lucide-react';
import { useState } from 'react';
import { ActionError } from '@/components/learning/actions';
import { LearningBadge, Records } from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import type { Certificate } from '@/types/learning';
import { localDate, pdfLabels } from '@/types/learning';

type Row = Certificate & { download_url: string | null };

export default function Certificates({
    certificates,
}: {
    certificates: Row[];
}) {
    const [expired, setExpired] = useState('');
    return (
        <Page
            title="Chứng nhận của tôi"
            description="Chứng nhận được cấp khi bạn hoàn thành khóa. Mỗi chứng nhận có mã số để người khác tra cứu."
            className="min-w-0"
            stats={[
                {
                    label: 'Chứng nhận',
                    value: certificates.length,
                    icon: Award,
                },
                {
                    label: 'Còn hiệu lực',
                    value: certificates.filter((c) => c.status !== 'Đã thu hồi')
                        .length,
                    icon: BadgeCheck,
                },
                {
                    label: 'Tải được PDF',
                    value: certificates.filter((c) => c.download_url).length,
                    icon: FileDown,
                },
            ]}
        >
            <Head title="Chứng nhận của tôi" />
            <div aria-live="polite">
                <Records
                    title="Chứng nhận của tôi"
                    rows={certificates}
                    rowKey={(row) => row.number}
                    empty="Bạn chưa có chứng nhận."
                    columns={[
                        {
                            label: 'Số chứng nhận',
                            cell: (row) => <strong>{row.number}</strong>,
                        },
                        { label: 'Khóa học', cell: (row) => row.course },
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
                            cell: (row) => {
                                const url = row.download_url;
                                return (
                                    <div className="grid justify-items-start gap-2">
                                        <span>
                                            {row.pdf_status === 'Lỗi'
                                                ? 'Chưa có PDF'
                                                : (pdfLabels[row.pdf_status] ??
                                                  row.pdf_status)}
                                        </span>
                                        {url && (
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                asChild
                                            >
                                                <a
                                                    href={url}
                                                    aria-label={`Tải PDF chứng nhận ${row.number}`}
                                                    onClick={(event) => {
                                                        const expiry = Number(
                                                            new URL(
                                                                url,
                                                            ).searchParams.get(
                                                                'expires',
                                                            ),
                                                        );
                                                        if (
                                                            expiry &&
                                                            Date.now() >=
                                                                expiry * 1_000
                                                        ) {
                                                            event.preventDefault();
                                                            setExpired(
                                                                row.number,
                                                            );
                                                            router.reload({
                                                                only: [
                                                                    'certificates',
                                                                ],
                                                                onSuccess: () =>
                                                                    setExpired(
                                                                        '',
                                                                    ),
                                                                onNetworkError:
                                                                    () => false,
                                                            });
                                                        }
                                                    }}
                                                >
                                                    Tải PDF
                                                </a>
                                            </Button>
                                        )}
                                        {expired === row.number && (
                                            <ActionError message="Liên kết tải đã hết hạn. Tải lại trang." />
                                        )}
                                    </div>
                                );
                            },
                        },
                    ]}
                />
            </div>
            <OrderReload
                only={['certificates']}
                label="Tải lại danh sách"
                failure="Không tải được danh sách chứng nhận."
            />
        </Page>
    );
}
