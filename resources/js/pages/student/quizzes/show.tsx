import { Form, Head, Link } from '@inertiajs/react';
import { ActionError } from '@/components/learning/actions';
import { LearningBadge } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { percentage } from '@/types/learning';

type Props = {
    title: string | null;
    duration_minutes: number | null;
    pass_percent: number | string | null;
    used: number;
    limit: number;
    best_percent: string | null;
    continue_url: string | null;
    start_url: string | null;
    history_url: string;
};

export default function Quiz({
    title,
    duration_minutes,
    pass_percent,
    used,
    limit,
    best_percent,
    continue_url,
    start_url,
    history_url,
}: Props) {
    const remaining = Math.max(0, limit - used);
    const unavailable = !start_url && !continue_url;
    return (
        <Page title={title ?? 'Quiz'} className="min-w-0 wrap-anywhere">
            <Head title={title ?? 'Quiz'} />
            <div
                className="grid min-w-0 items-start gap-4 lg:grid-cols-[minmax(0,1fr)_minmax(16rem,22rem)]"
                aria-live="polite"
            >
                <Block className="grid gap-4 p-4 sm:p-6">
                    <h2 className="font-semibold">Thông tin quiz</h2>
                    <dl className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                        <OrderFact label="Thời lượng">
                            {duration_minutes === null
                                ? '—'
                                : `${duration_minutes} phút`}
                        </OrderFact>
                        <OrderFact label="Điểm đạt">
                            {percentage(pass_percent)}
                        </OrderFact>
                        <OrderFact label="Đã dùng">
                            {used}/{limit} lượt
                        </OrderFact>
                        <OrderFact label="Còn lại">{remaining} lượt</OrderFact>
                    </dl>
                </Block>
                <div className="grid min-w-0 gap-3">
                    <Block tone="surface" className="grid gap-2 p-4 sm:p-6">
                        <h2 className="text-sm">Điểm ghi nhận</h2>
                        <p className="text-2xl font-bold">
                            {percentage(best_percent)}
                        </p>
                        {best_percent === null && (
                            <p className="text-sm">Chưa có lượt đã chốt.</p>
                        )}
                    </Block>
                    <Block className="grid justify-items-start gap-3 p-4 sm:p-6">
                        {continue_url && (
                            <>
                                <LearningBadge attention>
                                    Đang làm
                                </LearningBadge>
                                <Button asChild>
                                    <Link href={continue_url}>
                                        Tiếp tục lượt đang làm
                                    </Link>
                                </Button>
                            </>
                        )}
                        {start_url && (
                            <Form action={start_url} method="post">
                                {({ processing, errors }) => (
                                    <div className="grid gap-2">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            {processing
                                                ? 'Đang mở lượt…'
                                                : 'Bắt đầu'}
                                        </Button>
                                        <ActionError message={errors.general} />
                                    </div>
                                )}
                            </Form>
                        )}
                        {unavailable && (
                            <p className="text-sm">
                                {remaining === 0
                                    ? 'Đã hết lượt làm.'
                                    : 'Hiện không thể bắt đầu lượt mới. Xem lịch sử để kiểm tra kết quả.'}
                            </p>
                        )}
                        <Button asChild variant="outline">
                            <Link href={history_url}>Lịch sử lượt</Link>
                        </Button>
                    </Block>
                </div>
            </div>
            <OrderReload
                only={[
                    'used',
                    'limit',
                    'best_percent',
                    'start_url',
                    'continue_url',
                ]}
                label="Tải lại thông tin quiz"
                failure="Không tải được thông tin quiz."
            />
        </Page>
    );
}
