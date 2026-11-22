import { Head } from '@inertiajs/react';
import { RevenuePanel } from '@/components/learning/revenue';
import { Page } from '@/components/page';
import type { ReportSection, Revenue, Snapshot } from '@/types/learning';

export default function RevenuePage({
    revenue,
}: {
    revenue: ReportSection<Snapshot<Revenue>>;
}) {
    return (
        <Page
            title="Doanh thu"
            description="Doanh thu đã ghi nhận theo khóa và theo kỳ, đối chiếu với số đơn hoàn tất và khoản đã hoàn."
        >
            <Head title="Doanh thu" />
            <div className="mx-auto w-full max-w-2xl min-w-0">
                <RevenuePanel revenue={revenue} paymentDates />
            </div>
        </Page>
    );
}
