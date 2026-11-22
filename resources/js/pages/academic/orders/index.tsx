import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import {
    OrderList,
    OrderPagination,
    RefundList,
} from '@/components/order/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/academic/orders';
import type {
    OrderPage,
    OrderRow,
    OrderStatus,
    PaymentRow,
} from '@/types/order';

type Props = {
    orders: OrderPage<OrderRow>;
    needsAttention: OrderPage<OrderRow>;
    refunds: OrderPage<PaymentRow>;
    filters: { q: string; status: OrderStatus | null };
    statuses: { value: OrderStatus; label: string }[];
};

export default function AcademicOrders({
    orders,
    needsAttention,
    refunds,
    filters,
    statuses,
}: Props) {
    const [searchFailed, setSearchFailed] = useState(false);

    function reportSearchFailure() {
        setSearchFailed(true);
        return false;
    }

    const detailQuery = {
        q: filters.q || null,
        status: filters.status,
        page: orders.current_page > 1 ? orders.current_page : null,
        attention_page:
            needsAttention.current_page > 1
                ? needsAttention.current_page
                : null,
        refund_page: refunds.current_page > 1 ? refunds.current_page : null,
    };

    return (
        <Page
            title="Đơn hàng"
            description="Đơn cần xử lý nằm trên cùng (cấp quyền lỗi, tiền về muộn, cần hoàn). Tra cứu mọi đơn theo mã, tên hoặc email học viên."
        >
            <Head title="Đơn hàng" />
            <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                <h2 className="text-lg font-semibold">Đơn cần xử lý</h2>
                <div aria-live="polite">
                    <OrderList
                        rows={needsAttention.data}
                        label="Đơn cần xử lý"
                        empty="Không có đơn cần xử lý."
                        academic
                        queue
                        detailQuery={detailQuery}
                    />
                </div>
                <OrderPagination
                    page={needsAttention}
                    label="Phân trang đơn cần xử lý"
                />
                <OrderReload
                    only={['needsAttention']}
                    label="Tải lại đơn cần xử lý"
                    failure="Không tải được đơn cần xử lý."
                />
            </Block>
            <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                <h2 className="text-lg font-semibold">Khoản cần hoàn</h2>
                <div aria-live="polite">
                    <RefundList rows={refunds.data} detailQuery={detailQuery} />
                </div>
                <OrderPagination
                    page={refunds}
                    label="Phân trang khoản cần hoàn"
                />
                <OrderReload
                    only={['refunds']}
                    label="Tải lại khoản cần hoàn"
                    failure="Không tải được khoản cần hoàn."
                />
            </Block>
            <Block className="grid min-w-0 gap-4 p-4 sm:p-6">
                <h2 className="text-lg font-semibold">Tra đơn</h2>
                <Form
                    {...index.form()}
                    options={{ preserveScroll: true }}
                    onStart={() => setSearchFailed(false)}
                    onNetworkError={reportSearchFailure}
                    onHttpException={reportSearchFailure}
                    className="grid items-end gap-3 md:grid-cols-[minmax(0,1fr)_minmax(0,13rem)_auto]"
                >
                    {({ processing, errors }) => (
                        <>
                            {needsAttention.current_page > 1 && (
                                <input
                                    type="hidden"
                                    name="attention_page"
                                    value={needsAttention.current_page}
                                />
                            )}
                            {refunds.current_page > 1 && (
                                <input
                                    type="hidden"
                                    name="refund_page"
                                    value={refunds.current_page}
                                />
                            )}
                            <div className="grid gap-2">
                                <label
                                    htmlFor="order-query"
                                    className="text-sm font-medium"
                                >
                                    Mã đơn hoặc học viên
                                </label>
                                <Input
                                    id="order-query"
                                    name="q"
                                    defaultValue={filters.q}
                                    maxLength={200}
                                    aria-invalid={Boolean(errors.q)}
                                    aria-describedby={
                                        errors.q
                                            ? 'order-query-error'
                                            : undefined
                                    }
                                />
                                {errors.q && (
                                    <p
                                        id="order-query-error"
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.q}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-2">
                                <label
                                    htmlFor="order-status"
                                    className="text-sm font-medium"
                                >
                                    Trạng thái đơn
                                </label>
                                <select
                                    id="order-status"
                                    name="status"
                                    defaultValue={filters.status ?? ''}
                                    className={selectClass}
                                    aria-invalid={Boolean(errors.status)}
                                    aria-describedby={
                                        errors.status
                                            ? 'order-status-error'
                                            : undefined
                                    }
                                >
                                    <option value="">Mọi trạng thái</option>
                                    {statuses.map((status) => (
                                        <option
                                            value={status.value}
                                            key={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ))}
                                </select>
                                {errors.status && (
                                    <p
                                        id="order-status-error"
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.status}
                                    </p>
                                )}
                            </div>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Đang tra…' : 'Tra đơn'}
                            </Button>
                            {errors.general && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive md:col-span-full"
                                >
                                    {errors.general}
                                </p>
                            )}
                        </>
                    )}
                </Form>
                {searchFailed && (
                    <p role="alert" className="text-sm text-destructive">
                        Không tải được kết quả tra đơn.
                    </p>
                )}
                <div aria-live="polite">
                    <OrderList
                        rows={orders.data}
                        label="Kết quả tra đơn"
                        empty="Không tìm thấy đơn phù hợp."
                        academic
                        detailQuery={detailQuery}
                    />
                </div>
                <OrderPagination
                    page={orders}
                    label="Phân trang kết quả tra đơn"
                />
                <OrderReload
                    only={['orders']}
                    label="Tải lại kết quả tra đơn"
                    failure="Không tải được kết quả tra đơn."
                />
            </Block>
        </Page>
    );
}
