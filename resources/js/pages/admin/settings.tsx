import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { update as updateAi } from '@/routes/admin/settings/ai';
import { update } from '@/routes/admin/settings/grant-failure-mode';

type Setting = { key: string; value: unknown; editable: boolean };

const labels: Record<string, { name: string; unit?: string }> = {
    'order.payment_timeout_minutes': {
        name: 'Hạn thanh toán đơn',
        unit: 'phút',
    },
    'grant.tries': { name: 'Số lần thử cấp quyền học', unit: 'lần' },
    'device.max_active': {
        name: 'Số thiết bị hoạt động tối đa',
        unit: 'thiết bị',
    },
    'cert.lookup_per_minute': {
        name: 'Giới hạn tra cứu chứng nhận',
        unit: 'lần/phút',
    },
    'ai.daily_limit_user': {
        name: 'Lượt hỏi AI mỗi ngày (tài khoản)',
        unit: 'lượt',
    },
    'community.posts_per_hour': {
        name: 'Số bài cộng đồng tối đa mỗi học viên trong 60 phút',
        unit: 'bài',
    },
    'ai.daily_limit_guest': {
        name: 'Lượt tư vấn AI mỗi ngày (Khách, theo IP)',
        unit: 'lượt',
    },
};

function show(value: unknown): string {
    if (typeof value === 'boolean') {
        return value ? 'Bật' : 'Tắt';
    }

    if (typeof value === 'number') {
        return value.toLocaleString('vi-VN');
    }

    return typeof value === 'string' ? value : '—';
}

export default function AdminSettings({
    settings,
    canToggleGrantFailure,
    grantFailureMode,
    aiEnabled,
}: {
    settings: Setting[];
    canToggleGrantFailure: boolean;
    grantFailureMode: boolean;
    aiEnabled: boolean;
}) {
    const [saveFailed, setSaveFailed] = useState(false);

    function reportSaveFailure() {
        setSaveFailed(true);
        return false;
    }

    return (
        <Page
            title="Cấu hình"
            description="Các thông số vận hành: hạn thanh toán, giới hạn thiết bị, trợ lý AI, cộng đồng. Mỗi lần đổi đều ghi nhật ký."
        >
            <Head title="Cấu hình" />
            <Block className="grid gap-4 p-4 sm:p-6">
                <h2 className="text-lg font-semibold">Tham số hệ thống</h2>
                {settings.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Chưa có tham số cấu hình.
                    </p>
                )}
                <dl className="divide-y">
                    {settings.map((setting) => {
                        const label = labels[setting.key];
                        return (
                            <div
                                className="grid gap-1 py-3 sm:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]"
                                key={setting.key}
                            >
                                <dt className="wrap-anywhere">
                                    {label?.name ?? setting.key}
                                </dt>
                                <dd className="font-semibold wrap-anywhere">
                                    {show(setting.value)} {label?.unit}
                                </dd>
                            </div>
                        );
                    })}
                </dl>
            </Block>
            <Block className="grid gap-4 p-4 sm:p-6">
                <div
                    className="flex flex-wrap items-center gap-2"
                    aria-live="polite"
                >
                    <h2 className="text-lg font-semibold">Trợ lý AI</h2>
                    <Badge variant={aiEnabled ? 'default' : 'secondary'}>
                        {aiEnabled ? 'Bật' : 'Tắt'}
                    </Badge>
                </div>
                <p className="text-sm text-muted-foreground">
                    Hỏi AI trong bài, tư vấn khóa học và gợi ý câu hỏi. Bản xây
                    dựng dùng bộ giả lập, không gửi dữ liệu ra ngoài. Tắt thì
                    yêu cầu mới bị từ chối và không tính lượt.
                </p>
                <Form
                    {...updateAi.form()}
                    options={{ preserveScroll: true }}
                    className="grid justify-items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="enabled"
                                value={aiEnabled ? '0' : '1'}
                            />
                            <Button
                                type="submit"
                                role="switch"
                                aria-label="Trợ lý AI"
                                aria-checked={aiEnabled}
                                disabled={processing}
                            >
                                {aiEnabled ? 'Tắt trợ lý AI' : 'Bật trợ lý AI'}
                            </Button>
                            {(errors.enabled || errors.general) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.enabled ?? errors.general}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </Block>
            {canToggleGrantFailure && (
                <Block className="grid gap-4 border-t-4 border-t-accent p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Mô phỏng</h2>
                    <div
                        className="flex flex-wrap items-center gap-2"
                        aria-live="polite"
                    >
                        <h3 className="font-medium">Cấp quyền lỗi</h3>
                        <Badge
                            variant={grantFailureMode ? 'default' : 'secondary'}
                        >
                            {grantFailureMode ? 'Bật' : 'Tắt'}
                        </Badge>
                    </div>
                    <Form
                        {...update.form()}
                        className="grid justify-items-start gap-2"
                        onStart={() => setSaveFailed(false)}
                        onNetworkError={reportSaveFailure}
                        onHttpException={reportSaveFailure}
                    >
                        {({ processing, errors }) => (
                            <>
                                <input
                                    type="hidden"
                                    name="enabled"
                                    value={grantFailureMode ? '0' : '1'}
                                />
                                <Button
                                    type="submit"
                                    role="switch"
                                    aria-label="Cấp quyền lỗi"
                                    aria-checked={grantFailureMode}
                                    aria-invalid={Boolean(errors.enabled)}
                                    aria-describedby={
                                        errors.enabled
                                            ? 'grant-failure-error'
                                            : undefined
                                    }
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Đang lưu…'
                                        : grantFailureMode
                                          ? 'Tắt cấp quyền lỗi'
                                          : 'Bật cấp quyền lỗi'}
                                </Button>
                                {errors.enabled && (
                                    <p
                                        id="grant-failure-error"
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.enabled}
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
                            </>
                        )}
                    </Form>
                    {saveFailed && (
                        <p role="alert" className="text-sm text-destructive">
                            Chưa xác nhận được trạng thái đã lưu. Hãy tải lại
                            cấu hình.
                        </p>
                    )}
                </Block>
            )}
            <OrderReload
                only={[
                    'settings',
                    'grantFailureMode',
                    'canToggleGrantFailure',
                    'aiEnabled',
                ]}
                label="Tải lại cấu hình"
                failure="Không tải được cấu hình."
            />
        </Page>
    );
}
