import { OrderReload } from '@/components/order/refresh';
import { Block } from '@/components/page';
import { money } from '@/types/course';
import type { ReportSection, Revenue, Snapshot } from '@/types/learning';
import { localDate, localTime } from '@/types/learning';

export function RevenuePanel({
    revenue,
    paymentDates = false,
}: {
    revenue: ReportSection<Snapshot<Revenue>>;
    paymentDates?: boolean;
}) {
    const snapshot = revenue.data;
    const days = Object.entries(
        snapshot?.value.by_payment_date ?? {},
    ).reverse();
    return (
        <Block
            tone="surface"
            className="grid min-w-0 content-start gap-4 p-4 sm:p-6"
        >
            <div aria-live="polite" className="grid gap-2">
                <h2 className="font-semibold">Doanh thu mô phỏng</h2>
                {snapshot ? (
                    <>
                        <p className="text-3xl font-bold wrap-anywhere">
                            {money(snapshot.value.total)}
                        </p>
                        <p className="text-sm">
                            Cập nhật lúc {localTime(snapshot.computed_at)}
                        </p>
                    </>
                ) : (
                    <p
                        role={revenue.state === 'error' ? 'alert' : 'status'}
                        className="text-sm"
                    >
                        {revenue.state === 'error'
                            ? 'Không tải được doanh thu.'
                            : 'Chưa có số liệu tổng hợp'}
                    </p>
                )}
            </div>
            {paymentDates && days.length > 0 && (
                <details className="rounded-md border border-current/30 p-3 text-sm">
                    <summary className="cursor-pointer font-medium">
                        Ngày thanh toán
                    </summary>
                    <dl className="mt-2 grid divide-y divide-current/25">
                        {days.map(([day, total]) => (
                            <div
                                key={day}
                                className="flex flex-wrap justify-between gap-2 py-2"
                            >
                                <dt>{localDate(day)}</dt>
                                <dd className="font-semibold">
                                    {money(total)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                </details>
            )}
            <div className="text-foreground">
                <OrderReload
                    only={['revenue']}
                    label="Tải lại doanh thu"
                    failure="Không tải được doanh thu."
                />
            </div>
        </Block>
    );
}
