import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { OrderConfirm } from '@/components/order/confirm';
import { OrderItems, OrderSummary, Payments } from '@/components/order/details';
import {
    OrderCountdown,
    OrderReload,
    useOrderPolling,
} from '@/components/order/refresh';
import { OrderTimeline } from '@/components/order/timeline';
import { ConfirmAction } from '@/components/confirm-action';
import { Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/academic/orders';
import { regrant } from '@/routes/academic/orders/items';
import { acceptLate, markRefunded } from '@/routes/academic/orders/payments';
import { money } from '@/types/course';
import type { OrderAudit, OrderDetail } from '@/types/order';

const detailProps = ['order', 'audit'];

export default function AcademicOrder({
    order,
    audit,
}: {
    order: OrderDetail;
    audit: OrderAudit[];
}) {
    const waiting = order.status === 'CHO_THANH_TOAN';
    const [actionError, setActionError] = useState<string | null>(null);
    useOrderPolling(waiting || order.status === 'DANG_CAP_QUYEN', detailProps);
    const { url } = usePage();
    const [backQuery] = useState(() => {
        const search = new URL(url, 'http://localhost').searchParams;
        return {
            q: search.get('q'),
            status: search.get('status'),
            page: search.get('page'),
            attention_page: search.get('attention_page'),
            refund_page: search.get('refund_page'),
        };
    });

    useEffect(() => {
        const hash = window.location.hash;
        if (/^#khoan-\d+$/.test(hash)) {
            const payment = document.getElementById(hash.slice(1));
            payment?.focus({ preventScroll: true });
            payment?.scrollIntoView({ block: 'nearest' });
        }
    }, [order.code]);

    return (
        <Page
            title={`Đơn ${order.code}`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={index({ query: backQuery })}>Về đơn hàng</Link>
                </Button>
            }
        >
            <Head title={`Đơn ${order.code}`} />
            {actionError && (
                <p role="alert" className="text-sm text-destructive">
                    {actionError}
                </p>
            )}
            <div className="grid items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)]">
                <div className="grid min-w-0 gap-4">
                    <OrderSummary order={order}>
                        {waiting && (
                            <OrderCountdown expiresAt={order.expires_at} />
                        )}
                    </OrderSummary>
                    {order.stop_refund && (
                        <ConfirmAction
                            form={{ action: order.stop_refund, method: 'post' }}
                            label="Dừng cấp và hoàn tiền"
                            title="Dừng cấp quyền và chuyển khoản thu sang Cần hoàn?"
                            description="Đơn sẽ không cấp lại nữa. Sau khi đánh dấu đã hoàn, đơn chuyển Đã hủy (hủy sau hoàn)."
                            confirmLabel="Dừng cấp và hoàn"
                            variant="destructive"
                            reasonField={{
                                name: 'reason',
                                label: 'Lý do',
                                required: true,
                            }}
                            hidden={{ confirmed: '1' }}
                        />
                    )}
                    <OrderItems
                        items={order.items}
                        actions={(item) =>
                            item.can_regrant && (
                                <OrderConfirm
                                    form={regrant.form({
                                        order: order.code,
                                        item: item.id,
                                    })}
                                    label="Cấp lại"
                                    subject={item.title}
                                    description="Xác nhận cấp lại quyền học cho dòng đơn này."
                                    onResult={setActionError}
                                />
                            )
                        }
                    />
                </div>
                <div aria-live="polite">
                    <Payments
                        payments={order.payments}
                        academic
                        actions={(payment) => {
                            const target = {
                                order: order.code,
                                payment: payment.id,
                            };
                            const subject = `${payment.code} · ${money(payment.received_amount)}`;
                            return (
                                <div className="flex flex-wrap items-start gap-2">
                                    {payment.can_accept && (
                                        <OrderConfirm
                                            form={acceptLate.form(target)}
                                            label="Cấp quyền"
                                            subject={subject}
                                            description="Chấp nhận khoản thu muộn này để cấp quyền học cho đơn."
                                            onResult={setActionError}
                                        />
                                    )}
                                    {payment.can_refund && (
                                        <details className="grid max-w-full text-sm">
                                            <summary className="cursor-pointer rounded-md px-2 py-2 font-medium underline underline-offset-4 focus-visible:outline-ring">
                                                Thao tác khác
                                            </summary>
                                            <OrderConfirm
                                                form={markRefunded.form(target)}
                                                label="Đã hoàn"
                                                subject={subject}
                                                description="Ghi nhận khoản tiền đã được hoàn cho học viên."
                                                onResult={setActionError}
                                                refund
                                            />
                                        </details>
                                    )}
                                </div>
                            );
                        }}
                    />
                </div>
            </div>
            <OrderTimeline audit={audit} />
            <OrderReload
                only={detailProps}
                label="Tải lại chi tiết đơn"
                failure="Không tải được chi tiết đơn."
            />
        </Page>
    );
}
