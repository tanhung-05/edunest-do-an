import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { BankTransfer } from '@/components/order/bank-transfer';
import type { TransferInfo } from '@/components/order/bank-transfer';
import { OrderItems, OrderSummary, Payments } from '@/components/order/details';
import {
    OrderCountdown,
    OrderReload,
    useOrderPolling,
} from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { index as courses } from '@/routes/student/courses';
import { cancel, index } from '@/routes/student/orders';
import { store } from '@/routes/student/orders/payments';
import { CouponForm } from '@/components/order/coupon';
import { notice } from '@/routes/verification';
import type { OrderDetail } from '@/types/order';

export default function StudentOrder({
    order,
    transfer = null,
    transfer_simulator = false,
}: {
    order: OrderDetail;
    transfer?: TransferInfo | null;
    transfer_simulator?: boolean;
}) {
    const payment = useForm({ terms: false });
    const cancellation = useForm({});
    const [confirming, setConfirming] = useState(false);
    const [requestFailure, setRequestFailure] = useState(false);
    const [generalError, setGeneralError] = useState<string | null>(null);
    const waiting = order.status === 'CHO_THANH_TOAN';
    const busy = payment.processing || cancellation.processing;
    const retry = order.payments.some(
        (entry) =>
            entry.txn_status === 'THAT_BAI' ||
            entry.reconcile_status === 'CAN_HOAN' ||
            entry.reconcile_status === 'DA_HOAN_LAI',
    );
    useOrderPolling(!busy && (waiting || order.status === 'DANG_CAP_QUYEN'), [
        'order',
    ]);

    function reloadOrder() {
        router.reload({ only: ['order'] });
    }

    function requestFailed() {
        setRequestFailure(true);
        reloadOrder();
        return false;
    }

    return (
        <Page
            title={`Đơn ${order.code}`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={index()}>Về đơn hàng</Link>
                </Button>
            }
        >
            <Head title={`Đơn ${order.code}`} />
            <div className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(17rem,22rem)] lg:grid-rows-[auto_1fr]">
                <div className="grid min-w-0 gap-4 lg:col-start-2 lg:row-start-1">
                    <OrderSummary order={order}>
                        {waiting && (
                            <OrderCountdown expiresAt={order.expires_at} />
                        )}
                    </OrderSummary>
                    {order.status === 'HOAN_TAT' && (
                        <Button asChild>
                            <Link href={courses()}>Khóa của tôi</Link>
                        </Button>
                    )}
                </div>
                <div className="grid min-w-0 gap-4 lg:col-start-1 lg:row-span-2 lg:row-start-1">
                    <OrderItems items={order.items} />
                    <Payments payments={order.payments} />
                </div>
                <div className="grid min-w-0 gap-4 lg:col-start-2 lg:row-start-2">
                    {order.coupon_url && <CouponForm url={order.coupon_url} />}
                    {waiting && order.can_pay && (
                        <BankTransfer
                            code={order.code}
                            transfer={transfer}
                            simulator={transfer_simulator}
                        />
                    )}
                    {waiting && (
                        <Block className="grid gap-4 p-4 sm:p-6">
                            {order.can_pay ? (
                                <form
                                    className="grid gap-3"
                                    onSubmit={(event) => {
                                        event.preventDefault();
                                        setRequestFailure(false);
                                        setGeneralError(null);
                                        payment.submit(store(order.code), {
                                            onError: (errors) => {
                                                if (errors.general) {
                                                    setGeneralError(
                                                        errors.general,
                                                    );
                                                    reloadOrder();
                                                }
                                            },
                                            onNetworkError: requestFailed,
                                            onHttpException: requestFailed,
                                        });
                                    }}
                                >
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            required
                                            checked={payment.data.terms}
                                            onChange={(event) => {
                                                payment.setData(
                                                    'terms',
                                                    event.target.checked,
                                                );
                                                payment.clearErrors('terms');
                                            }}
                                            className="mt-1 size-4 shrink-0 accent-primary"
                                            aria-invalid={Boolean(
                                                payment.errors.terms,
                                            )}
                                            aria-describedby={
                                                payment.errors.terms
                                                    ? 'payment-terms-error'
                                                    : undefined
                                            }
                                        />
                                        Tôi đồng ý với điều khoản “Khóa học số,
                                        không hoàn tiền sau khi kích hoạt”.
                                    </label>
                                    {payment.errors.terms && (
                                        <p
                                            id="payment-terms-error"
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {payment.errors.terms}
                                        </p>
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={busy || !payment.data.terms}
                                    >
                                        {payment.processing
                                            ? 'Đang gửi…'
                                            : retry
                                              ? 'Thanh toán lại'
                                              : 'Thanh toán'}
                                    </Button>
                                </form>
                            ) : (
                                <div className="grid justify-items-start gap-2">
                                    <p className="text-sm">
                                        Hãy xác minh email trước khi thanh toán.
                                    </p>
                                    <Button variant="outline" asChild>
                                        <Link href={notice()}>
                                            Xác minh email
                                        </Link>
                                    </Button>
                                </div>
                            )}
                            {order.can_cancel && (
                                <Dialog
                                    open={confirming}
                                    onOpenChange={(next) => {
                                        if (!busy) setConfirming(next);
                                    }}
                                >
                                    <DialogTrigger asChild>
                                        <Button
                                            variant="outline"
                                            disabled={busy}
                                        >
                                            Hủy đơn
                                        </Button>
                                    </DialogTrigger>
                                    <DialogContent className="[&>button]:hidden">
                                        <DialogHeader>
                                            <DialogTitle>
                                                Hủy đơn {order.code}?
                                            </DialogTitle>
                                            <DialogDescription>
                                                Sau khi hủy, đơn này sẽ không
                                                thể thanh toán.
                                            </DialogDescription>
                                        </DialogHeader>
                                        <DialogFooter>
                                            <DialogClose asChild>
                                                <Button
                                                    type="button"
                                                    variant="outline"
                                                    disabled={busy}
                                                >
                                                    Giữ đơn
                                                </Button>
                                            </DialogClose>
                                            <Button
                                                type="button"
                                                disabled={busy}
                                                onClick={() => {
                                                    setRequestFailure(false);
                                                    setGeneralError(null);
                                                    payment.clearErrors();
                                                    cancellation.submit(
                                                        cancel(order.code),
                                                        {
                                                            preserveScroll: true,
                                                            onSuccess: () =>
                                                                setConfirming(
                                                                    false,
                                                                ),
                                                            onError: (
                                                                errors,
                                                            ) => {
                                                                setGeneralError(
                                                                    errors.general ??
                                                                        'Đơn đã đổi trạng thái hoặc đã hết hạn. Tải lại chi tiết đơn.',
                                                                );
                                                                setConfirming(
                                                                    false,
                                                                );
                                                                reloadOrder();
                                                            },
                                                            onNetworkError:
                                                                () => {
                                                                    setConfirming(
                                                                        false,
                                                                    );
                                                                    return requestFailed();
                                                                },
                                                            onHttpException:
                                                                () => {
                                                                    setConfirming(
                                                                        false,
                                                                    );
                                                                    return requestFailed();
                                                                },
                                                        },
                                                    );
                                                }}
                                            >
                                                {cancellation.processing
                                                    ? 'Đang hủy…'
                                                    : 'Xác nhận hủy đơn'}
                                            </Button>
                                        </DialogFooter>
                                    </DialogContent>
                                </Dialog>
                            )}
                        </Block>
                    )}
                    <div
                        aria-live="polite"
                        className="text-sm text-destructive"
                    >
                        {requestFailure && (
                            <p>
                                Chưa xác nhận được kết quả. Hãy tải lại chi tiết
                                đơn.
                            </p>
                        )}
                        {generalError && <p>{generalError}</p>}
                    </div>
                    <OrderReload
                        only={['order']}
                        label="Tải lại chi tiết đơn"
                        failure="Không tải được chi tiết đơn."
                    />
                </div>
            </div>
        </Page>
    );
}
