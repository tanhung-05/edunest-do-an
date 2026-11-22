import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Button } from '@/components/ui/button';
import { show as academicOrder } from '@/routes/academic/orders';
import { show as studentOrder } from '@/routes/student/orders';
import { money } from '@/types/course';
import { orderDate } from '@/types/order';
import type { RouteQueryOptions } from '@/wayfinder';
import type { OrderPage, OrderRow, PaymentRow } from '@/types/order';
import { OrderBadge, orderLabels } from './status';

type Column<T> = { label: string; cell: (row: T) => ReactNode };

export function OrderRecords<T>({
    rows,
    rowKey,
    columns,
    label,
    empty,
}: {
    rows: T[];
    rowKey: (row: T) => string | number;
    columns: Column<T>[];
    label: string;
    empty: string;
}) {
    if (rows.length === 0) {
        return <p className="text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <>
            <ul aria-label={label} className="grid gap-3 lg:hidden">
                {rows.map((row) => (
                    <li
                        key={rowKey(row)}
                        className="rounded-md border bg-card p-3"
                    >
                        <dl className="grid gap-x-3 gap-y-2 text-sm">
                            {columns.map(({ label, cell }) => (
                                <div
                                    key={label}
                                    className="grid grid-cols-[minmax(0,6.5rem)_minmax(0,1fr)] gap-2"
                                >
                                    <dt className="text-muted-foreground">
                                        {label}
                                    </dt>
                                    <dd className="wrap-anywhere">
                                        {cell(row)}
                                    </dd>
                                </div>
                            ))}
                        </dl>
                    </li>
                ))}
            </ul>
            <table
                aria-label={label}
                className="hidden w-full table-fixed overflow-hidden rounded-md text-left text-sm lg:table"
            >
                <thead className="bg-surface text-surface-ink">
                    <tr>
                        {columns.map(({ label }) => (
                            <th
                                scope="col"
                                key={label}
                                className="px-3 py-2 font-semibold wrap-anywhere"
                            >
                                {label}
                            </th>
                        ))}
                    </tr>
                </thead>
                <tbody className="divide-y">
                    {rows.map((row) => (
                        <tr key={rowKey(row)}>
                            {columns.map(({ label, cell }) => (
                                <td
                                    key={label}
                                    className="px-3 py-3 align-top wrap-anywhere"
                                >
                                    {cell(row)}
                                </td>
                            ))}
                        </tr>
                    ))}
                </tbody>
            </table>
        </>
    );
}

export function OrderList({
    rows,
    label,
    empty,
    academic = false,
    queue = false,
    detailQuery,
}: {
    rows: OrderRow[];
    label: string;
    empty: string;
    academic?: boolean;
    queue?: boolean;
    detailQuery?: RouteQueryOptions['query'];
}) {
    const columns: Column<OrderRow>[] = [
        {
            label: 'Mã đơn',
            cell: (order) => (
                <Link
                    href={
                        academic
                            ? academicOrder(order.code, { query: detailQuery })
                            : studentOrder(order.code)
                    }
                    className="font-semibold underline underline-offset-4"
                >
                    {order.code}
                </Link>
            ),
        },
        academic
            ? { label: 'Học viên', cell: (order) => order.student ?? '—' }
            : { label: 'Khóa học', cell: (order) => order.courses.join(', ') },
        { label: 'Tổng tiền', cell: (order) => money(order.total) },
        {
            label: 'Trạng thái',
            cell: (order) => (
                <OrderBadge
                    status={order.status}
                    label={orderLabels[order.status]}
                />
            ),
        },
    ];
    if (!queue) {
        columns.splice(1, 0, {
            label: 'Ngày đặt',
            cell: (order) => orderDate(order.created_at),
        });
    }

    return (
        <OrderRecords
            rows={rows}
            rowKey={(order) => order.code}
            columns={columns}
            label={label}
            empty={empty}
        />
    );
}

export function RefundList({
    rows,
    detailQuery,
}: {
    rows: PaymentRow[];
    detailQuery?: RouteQueryOptions['query'];
}) {
    return (
        <OrderRecords
            rows={rows}
            rowKey={(payment) => payment.id}
            label="Khoản cần hoàn"
            empty="Không có khoản cần hoàn."
            columns={[
                {
                    label: 'Mã giao dịch',
                    cell: (payment) => (
                        <Link
                            className="font-semibold underline underline-offset-4"
                            href={
                                academicOrder.url(payment.order_code, {
                                    query: detailQuery,
                                }) +
                                '#khoan-' +
                                payment.id
                            }
                            preserveScroll={false}
                        >
                            {payment.code}
                        </Link>
                    ),
                },
                { label: 'Mã đơn', cell: (payment) => payment.order_code },
                {
                    label: 'Số tiền',
                    cell: (payment) => money(payment.received_amount),
                },
                {
                    label: 'Trạng thái đơn',
                    cell: (payment) => payment.order_status,
                },
                { label: 'Lý do', cell: (payment) => payment.reason ?? '—' },
            ]}
        />
    );
}

export function OrderPagination({
    page,
    label,
}: {
    page: OrderPage<unknown>;
    label: string;
}) {
    if (page.last_page <= 1) {
        return null;
    }

    return (
        <nav
            aria-label={label}
            className="flex flex-wrap items-center gap-2 text-sm"
        >
            {page.prev_page_url && (
                <Button variant="outline" asChild>
                    <Link href={page.prev_page_url} preserveScroll>
                        Trang trước
                    </Link>
                </Button>
            )}
            <span className="px-2 text-muted-foreground">
                Trang {page.current_page} / {page.last_page}
            </span>
            {page.next_page_url && (
                <Button variant="outline" asChild>
                    <Link href={page.next_page_url} preserveScroll>
                        Trang sau
                    </Link>
                </Button>
            )}
        </nav>
    );
}
