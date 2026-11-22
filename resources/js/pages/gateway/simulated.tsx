import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { PaymentFacts } from '@/components/order/details';
import { OrderReload, useOrderPolling } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { confirm, resend } from '@/routes/gateway/simulated';
import { show } from '@/routes/student/orders';
import type { SimulatedPayment } from '@/types/order';

const gatewayProps = ['payment', 'selectedResult', 'canConfirm'];

export default function PaymentGateway({
    payment,
    selectedResult,
    canConfirm,
}: SimulatedPayment) {
    const waiting = payment.order_status === 'Chờ thanh toán';
    const granting = payment.order_status === 'Đang cấp quyền';
    useOrderPolling(waiting || granting, gatewayProps);

    const orderLink = show(payment.order_code);
    const [requestError, setRequestError] = useState<string | null>(null);

    function sendFailed() {
        setRequestError('Không gửi được xác nhận. Thử lại.');
        return false;
    }

    function resendFailed() {
        setRequestError('Không gửi lại được xác nhận.');
        return false;
    }

    return (
        <Page
            title="Cổng thanh toán mô phỏng"
            className="mx-auto w-full max-w-2xl"
        >
            <Head title="Cổng thanh toán mô phỏng" />
            <Block tone="surface" className="grid gap-3 p-4 sm:p-6">
                <Badge className="bg-accent text-accent-foreground">
                    Mô phỏng
                </Badge>
                <div>
                    <p className="text-sm text-surface-muted">Mã giao dịch</p>
                    <h2 className="font-semibold wrap-anywhere">
                        {payment.code}
                    </h2>
                </div>
                <p className="text-sm">
                    Mã đơn{' '}
                    <Link
                        href={orderLink}
                        className="font-medium underline underline-offset-4"
                    >
                        {payment.order_code}
                    </Link>
                </p>
            </Block>
            <Block className="grid gap-4 p-4 sm:p-6">
                <div aria-live="polite" className="grid gap-3">
                    <PaymentFacts payment={payment} />
                    <p className="text-sm">
                        Trạng thái đơn <strong>{payment.order_status}</strong>
                    </p>
                    {selectedResult && (
                        <p className="rounded-md bg-secondary p-3 text-sm">
                            Đã nhận lựa chọn mô phỏng{' '}
                            <strong>{selectedResult.toLowerCase()}</strong>.{' '}
                            {payment.txn_status === 'KHOI_TAO' &&
                                'Đang chờ kết quả giao dịch.'}
                        </p>
                    )}
                    {payment.txn_status === 'THAT_BAI' && waiting && (
                        <p className="text-sm">
                            Về chi tiết đơn hàng để thanh toán lại bằng khoản
                            mới.
                        </p>
                    )}
                </div>
                {canConfirm && (
                    <Form
                        {...confirm.form(payment.id)}
                        className="grid gap-3"
                        onStart={() => setRequestError(null)}
                        onNetworkError={sendFailed}
                        onHttpException={sendFailed}
                    >
                        {({ processing, errors }) => (
                            <>
                                <fieldset
                                    className="grid gap-2"
                                    disabled={processing}
                                    aria-describedby={
                                        errors.result
                                            ? 'simulation-result-error'
                                            : undefined
                                    }
                                >
                                    <legend className="mb-2 text-sm font-semibold">
                                        Kết quả mô phỏng
                                    </legend>
                                    {[
                                        ['THANH_CONG', 'Thành công'],
                                        ['THAT_BAI', 'Thất bại'],
                                    ].map(([value, label]) => (
                                        <label
                                            key={value}
                                            className="flex min-h-11 items-center gap-2 rounded-md border px-3 text-sm"
                                        >
                                            <input
                                                type="radio"
                                                name="result"
                                                value={value}
                                                required
                                                className="size-4 accent-primary"
                                                aria-invalid={Boolean(
                                                    errors.result,
                                                )}
                                            />
                                            Mô phỏng {label.toLowerCase()}
                                        </label>
                                    ))}
                                </fieldset>
                                {errors.result && (
                                    <p
                                        id="simulation-result-error"
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.result}
                                    </p>
                                )}
                                {errors.general && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.general}
                                    </p>
                                )}
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Đang gửi…' : 'Gửi xác nhận'}
                                </Button>
                            </>
                        )}
                    </Form>
                )}
                {selectedResult && (
                    <Form
                        {...resend.form(payment.id)}
                        className="grid justify-items-start gap-2"
                        onStart={() => setRequestError(null)}
                        onNetworkError={resendFailed}
                        onHttpException={resendFailed}
                    >
                        {({ processing, errors }) => (
                            <>
                                <Button type="submit" disabled={processing}>
                                    {processing
                                        ? 'Đang gửi lại…'
                                        : 'Gửi lại xác nhận'}
                                </Button>
                                {errors.general && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.general}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                )}
                {requestError && (
                    <p role="alert" className="text-sm text-destructive">
                        {requestError}
                    </p>
                )}
                <div className="flex flex-wrap items-start gap-2">
                    <Button variant="outline" size="sm" asChild>
                        <Link href={orderLink}>Về chi tiết đơn</Link>
                    </Button>
                    <OrderReload
                        only={gatewayProps}
                        label="Cập nhật kết quả"
                        failure="Không tải được kết quả giao dịch."
                    />
                </div>
            </Block>
        </Page>
    );
}
