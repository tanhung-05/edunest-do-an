import { Form, Head } from '@inertiajs/react';
import { MonitorSmartphone } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import devices from '@/routes/account/devices';

type Device = {
    id: number;
    browser: string;
    os: string;
    last_active_at: string;
    active: boolean;
    current: boolean;
};

export default function Devices({
    devices: list,
    limit,
}: {
    devices: Device[];
    limit: number | null;
}) {
    const activeCount = list.filter((d) => d.active).length;

    return (
        <Page
            title="Thiết bị của tôi"
            description={
                limit === null
                    ? 'Tài khoản nhân sự không giới hạn số thiết bị.'
                    : `Đang dùng ${activeCount}/${limit} thiết bị. Thiết bị không hoạt động quá 30 ngày không tính vào giới hạn.`
            }
        >
            <Head title="Thiết bị của tôi" />
            <Block>
                <ul className="divide-y divide-border">
                    {list.map((d) => (
                        <li
                            key={d.id}
                            className="flex flex-wrap items-center gap-4 py-4 first:pt-0 last:pb-0"
                        >
                            <MonitorSmartphone
                                aria-hidden
                                className="size-6 text-muted-foreground"
                            />
                            <div className="min-w-0 flex-1">
                                <p className="font-medium">
                                    {d.browser} trên {d.os}
                                    {d.current && (
                                        <span className="ml-2 text-sm text-success">
                                            Thiết bị này
                                        </span>
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    Hoạt động lần cuối{' '}
                                    {vnTime(d.last_active_at)}
                                    {!d.active &&
                                        ' · không còn tính vào giới hạn'}
                                </p>
                            </div>
                            {!d.current && (
                                <Form
                                    {...devices.destroy.form(d.id)}
                                    options={{ preserveScroll: true }}
                                    onBefore={() =>
                                        window.confirm(
                                            'Đăng xuất thiết bị này? Phiên và liên kết học trên thiết bị đó mất hiệu lực ngay.',
                                        )
                                    }
                                >
                                    <Button variant="outline" size="sm">
                                        Đăng xuất
                                    </Button>
                                </Form>
                            )}
                        </li>
                    ))}
                    {list.length === 0 && (
                        <li className="text-muted-foreground">
                            Chưa ghi nhận thiết bị nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
