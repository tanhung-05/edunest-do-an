import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Block } from '@/components/page';
import { show as gateway } from '@/routes/gateway/simulated';
import { money } from '@/types/course';
import { accessDuration, orderDate } from '@/types/order';
import type { OrderDetail, OrderItem, PaymentRow } from '@/types/order';
import { itemLabels, OrderBadge, orderLabels } from './status';

export function OrderSummary({
    order,
    children,
}: {
    order: OrderDetail;
    children?: ReactNode;
}) {
    return (
        <Block tone="surface" className="grid gap-4 p-4 sm:p-6">
            <h2 className="font-semibold">Tóm tắt đơn</h2>
            <div aria-live="polite">
                <OrderBadge
                    status={order.status}
                    label={orderLabels[order.status]}
                />
                {order.status === 'DANG_CAP_QUYEN' && (
                    <p className="mt-2 text-sm">
                        Đã nhận thanh toán. Đang cấp quyền học.
                    </p>
                )}
                {order.status === 'CAN_XU_LY' && order.student === null && (
                    <p className="mt-2 text-sm">Đơn đang được Giáo vụ xử lý.</p>
                )}
            </div>
            <dl className="grid gap-3 text-sm">
                {order.discount_total > 0 && (
                    <>
                        <OrderFact label="Tạm tính">
                            {money(order.subtotal)}
                        </OrderFact>
                        <OrderFact
                            label={
                                order.coupon
                                    ? `Giảm giá (mã ${order.coupon})`
                                    : 'Giảm giá'
                            }
                        >
                            −{money(order.discount_total)}
                        </OrderFact>
                    </>
                )}
                <OrderFact label="Tổng tiền">
                    <span className="text-2xl font-bold">
                        {money(order.total)}
                    </span>
                </OrderFact>
                {order.student && (
                    <OrderFact label="Học viên">{order.student}</OrderFact>
                )}
                {order.student !== null && (
                    <OrderFact label="Nguồn đơn">{order.source}</OrderFact>
                )}
                <OrderFact label="Ngày đặt">
                    {orderDate(order.created_at)}
                </OrderFact>
                <OrderFact label="Hạn thanh toán">
                    {orderDate(order.expires_at)}
                </OrderFact>
                {order.completed_at && (
                    <OrderFact label="Hoàn tất lúc">
                        {orderDate(order.completed_at)}
                    </OrderFact>
                )}
                {order.invoice && (
                    <OrderFact label="Hóa đơn">
                        {order.invoice.url ? (
                            <a
                                href={order.invoice.url}
                                className="underline underline-offset-4"
                            >
                                Tải {order.invoice.number} (PDF)
                            </a>
                        ) : (
                            `${order.invoice.number} · ${order.invoice.status}`
                        )}
                    </OrderFact>
                )}
            </dl>
            {children}
        </Block>
    );
}

export function OrderFact({
    label,
    children,
}: {
    label: string;
    children: ReactNode;
}) {
    return (
        <div className="grid min-w-0 gap-1">
            <dt className="text-sm opacity-80">{label}</dt>
            <dd className="font-medium wrap-anywhere">{children}</dd>
        </div>
    );
}

export function OrderItems({
    items,
    actions,
}: {
    items: OrderItem[];
    actions?: (item: OrderItem) => ReactNode;
}) {
    return (
        <Block className="grid min-w-0 gap-3 p-4 sm:p-6">
            <h2 className="text-lg font-semibold">Dòng đơn</h2>
            <ul className="divide-y">
                {items.map((item) => (
                    <li key={item.id} className="grid gap-3 py-3">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h3 className="font-semibold wrap-anywhere">
                                {item.title}
                            </h3>
                            <OrderBadge
                                status={item.status}
                                label={itemLabels[item.status]}
                            />
                        </div>
                        <dl className="grid grid-cols-2 gap-3 text-sm">
                            <OrderFact label="Loại">
                                {item.kind_label}
                            </OrderFact>
                            <OrderFact label="Giá lúc đặt">
                                {item.effective_price < item.list_price && (
                                    <s className="mr-2 font-normal text-muted-foreground">
                                        {money(item.list_price)}
                                    </s>
                                )}
                                {money(item.effective_price)}
                            </OrderFact>
                            {item.discount > 0 && (
                                <OrderFact label="Giảm từ mã">
                                    −{money(item.discount)} →{' '}
                                    {money(item.amount)}
                                </OrderFact>
                            )}
                            <OrderFact label="Thời hạn truy cập">
                                {accessDuration(item.access_months)}
                            </OrderFact>
                            {item.expiry_after && (
                                <OrderFact label="Hạn truy cập">
                                    {orderDate(item.expiry_after)}
                                </OrderFact>
                            )}
                        </dl>
                        {actions?.(item)}
                    </li>
                ))}
            </ul>
        </Block>
    );
}

export function PaymentFacts({ payment }: { payment: PaymentRow }) {
    const reason =
        payment.reason === 'Thu muộn' &&
        payment.received_amount !== null &&
        payment.received_amount !== payment.requested_amount
            ? 'Thu muộn, sai số tiền'
            : payment.reason;

    return (
        <dl className="grid grid-cols-2 gap-3 text-sm">
            <OrderFact label="Kênh">{payment.channel}</OrderFact>
            <OrderFact label="Số tiền yêu cầu">
                {money(payment.requested_amount)}
            </OrderFact>
            <OrderFact label="Giao dịch">
                <OrderBadge
                    status={payment.txn_status}
                    label={payment.txn_status_label}
                />
            </OrderFact>
            <OrderFact label="Đối soát">
                <OrderBadge
                    status={payment.reconcile_status}
                    label={payment.reconcile_status_label}
                />
            </OrderFact>
            {payment.received_amount !== null && (
                <OrderFact label="Số tiền thực thu">
                    {money(payment.received_amount)}
                </OrderFact>
            )}
            {reason && <OrderFact label="Lý do cần hoàn">{reason}</OrderFact>}
            {payment.refunded_at && (
                <OrderFact label="Hoàn lại lúc">
                    {orderDate(payment.refunded_at)}
                </OrderFact>
            )}
        </dl>
    );
}

export function Payments({
    payments,
    academic = false,
    actions,
}: {
    payments: PaymentRow[];
    academic?: boolean;
    actions?: (payment: PaymentRow) => ReactNode;
}) {
    return (
        <Block className="grid min-w-0 gap-3 p-4 sm:p-6">
            <h2 className="text-lg font-semibold">Khoản thanh toán</h2>
            {payments.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Chưa có khoản thanh toán
                </p>
            )}
            <ul className="grid gap-3">
                {payments.map((payment) => (
                    <li
                        id={'khoan-' + payment.id}
                        tabIndex={-1}
                        key={payment.id}
                        className="grid scroll-mt-4 gap-3 rounded-md border p-3 outline-ring target:border-accent focus:outline-2"
                    >
                        <h3 className="text-sm font-semibold wrap-anywhere">
                            {payment.simulated && !academic ? (
                                <Link
                                    href={gateway(payment.id)}
                                    className="underline underline-offset-4"
                                >
                                    {payment.code}
                                </Link>
                            ) : (
                                payment.code
                            )}
                        </h3>
                        <PaymentFacts payment={payment} />
                        {actions?.(payment)}
                    </li>
                ))}
            </ul>
        </Block>
    );
}
