import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { show } from '@/routes/public/courses';
import { store } from '@/routes/student/orders';
import { money } from '@/types/course';
import { accessDuration } from '@/types/order';
import type { CheckoutCourse } from '@/types/order';

export default function Checkout({
    course,
    selling,
    ref = null,
}: {
    course: CheckoutCourse;
    selling: boolean;
    ref?: string | null;
}) {
    const [requestFailed, setRequestFailed] = useState(false);

    function reportFailure() {
        setRequestFailed(true);
        return false;
    }

    return (
        <Page
            title="Xác nhận đặt mua"
            className="mx-auto w-full max-w-2xl"
            actions={
                <Button variant="outline" asChild>
                    <Link href={show(course.slug)}>Về trang khóa học</Link>
                </Button>
            }
        >
            <Head title="Xác nhận đặt mua" />
            <Block tone="surface" className="grid gap-4 p-4 sm:p-6">
                <h2 className="text-xl font-semibold wrap-anywhere">
                    {course.title}
                </h2>
                <dl className="grid grid-cols-2 gap-3 text-sm">
                    <OrderFact label="Tổng tiền">
                        <span className="text-2xl font-bold">
                            {money(course.price)}
                        </span>
                    </OrderFact>
                    <OrderFact label="Thời hạn truy cập">
                        {accessDuration(course.access_months)}
                    </OrderFact>
                </dl>
            </Block>
            <Block className="grid gap-3 p-4 sm:p-6">
                {selling ? (
                    <Form
                        {...store.form(course.slug)}
                        className="grid gap-3"
                        onNetworkError={reportFailure}
                        onHttpException={reportFailure}
                    >
                        {({ processing, errors }) => (
                            <>
                                {ref && (
                                    <input
                                        type="hidden"
                                        name="ref"
                                        value={ref}
                                    />
                                )}
                                <p className="text-sm text-muted-foreground">
                                    Sau khi đặt đơn, bạn có thể thanh toán bằng
                                    cổng mô phỏng.
                                </p>
                                {errors.general && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.general}
                                    </p>
                                )}
                                <Button
                                    type="submit"
                                    disabled={processing || requestFailed}
                                >
                                    {processing ? 'Đang đặt đơn…' : 'Đặt đơn'}
                                </Button>
                            </>
                        )}
                    </Form>
                ) : (
                    <p role="status">Khóa học đã ngừng bán.</p>
                )}
                {requestFailed && (
                    <>
                        <p role="alert" className="text-sm text-destructive">
                            Chưa xác nhận được kết quả đặt đơn. Hãy kiểm tra lại
                            trước khi thử lại.
                        </p>
                        <OrderReload
                            only={['course', 'selling']}
                            label="Kiểm tra lại đơn"
                            failure="Không tải được thông tin đặt đơn."
                            onSuccess={() => setRequestFailed(false)}
                        />
                    </>
                )}
            </Block>
        </Page>
    );
}
