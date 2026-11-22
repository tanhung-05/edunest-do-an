import { Head, Link } from '@inertiajs/react';
import { CircleCheck, Clock, Receipt, Wallet } from 'lucide-react';
import { OrderList, OrderPagination } from '@/components/order/records';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import publicRoutes from '@/routes/public';
import type { OrderPage, OrderRow } from '@/types/order';

type Summary = { total: number; done: number; open: number; spent: number };

/** MH-HV đơn hàng của tôi. P10: dải đầu trang có số đơn, đơn đang mở và tổng đã thanh toán. */
export default function StudentOrders({
    orders,
    summary,
}: {
    orders: OrderPage<OrderRow>;
    summary: Summary;
}) {
    return (
        <Page
            title="Đơn hàng của tôi"
            description="Theo dõi trạng thái thanh toán, cấp quyền học và hóa đơn của từng đơn. Đơn chờ thanh toán quá hạn sẽ tự hủy."
            actions={
                <Button asChild variant="secondary">
                    <Link href={publicRoutes.courses.index()}>
                        Mua thêm khóa học
                    </Link>
                </Button>
            }
            stats={[
                { label: 'Tổng số đơn', value: summary.total, icon: Receipt },
                { label: 'Hoàn tất', value: summary.done, icon: CircleCheck },
                {
                    label: 'Đang mở',
                    value: summary.open,
                    icon: Clock,
                    attention: summary.open > 0,
                },
                {
                    label: 'Đã thanh toán',
                    value: `${summary.spent.toLocaleString('vi-VN')}đ`,
                    icon: Wallet,
                },
            ]}
        >
            <Head title="Đơn hàng của tôi" />
            <Block className="grid gap-4 p-4 sm:p-6">
                <div aria-live="polite">
                    <OrderList
                        rows={orders.data}
                        label="Đơn hàng của tôi"
                        empty="Bạn chưa có đơn hàng. Chọn một khóa ở danh mục để bắt đầu."
                    />
                </div>
                <OrderPagination page={orders} label="Phân trang đơn hàng" />
            </Block>
            <OrderReload
                only={['orders', 'summary']}
                label="Tải lại danh sách"
                failure="Không tải được danh sách đơn hàng."
            />
        </Page>
    );
}
