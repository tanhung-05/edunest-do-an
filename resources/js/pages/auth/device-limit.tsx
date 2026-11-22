import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import devicesRoute from '@/routes/login/devices';

type Device = {
    id: number;
    browser: string;
    os: string;
    last_active_at: string;
};

export default function DeviceLimit({ devices }: { devices: Device[] }) {
    const [chosen, setChosen] = useState<number | null>(null);

    return (
        <>
            <Head title="Đã đủ số thiết bị" />
            <Form {...devicesRoute.choose.form()} className="space-y-5">
                {({ processing, errors }) => (
                    <>
                        <fieldset className="space-y-2">
                            <legend className="mb-2 text-sm font-medium">
                                Chọn thiết bị sẽ bị đăng xuất
                            </legend>
                            {devices.map((d) => (
                                <label
                                    key={d.id}
                                    className="flex cursor-pointer items-center gap-3 rounded-xl border border-border p-3 has-checked:border-primary has-checked:bg-accent"
                                >
                                    <input
                                        type="radio"
                                        name="device"
                                        value={d.id}
                                        checked={chosen === d.id}
                                        onChange={() => setChosen(d.id)}
                                        className="size-4"
                                    />
                                    <span>
                                        <span className="block font-medium">
                                            {d.browser} trên {d.os}
                                        </span>
                                        <span className="block text-sm text-muted-foreground">
                                            Hoạt động lần cuối{' '}
                                            {vnTime(d.last_active_at)}
                                        </span>
                                    </span>
                                </label>
                            ))}
                        </fieldset>
                        <InputError message={errors.device} />
                        <Button
                            className="w-full"
                            disabled={processing || chosen === null}
                        >
                            {processing && <Spinner />}
                            Đăng xuất thiết bị đã chọn và tiếp tục
                        </Button>
                        <a
                            href={login().url}
                            className="block text-center text-sm text-muted-foreground underline"
                        >
                            Hủy, quay lại đăng nhập
                        </a>
                    </>
                )}
            </Form>
        </>
    );
}

DeviceLimit.layout = {
    title: 'Đã đủ số thiết bị',
    description:
        'Tài khoản học viên chỉ dùng được trên một số thiết bị cùng lúc. Chọn một thiết bị cũ để đăng xuất.',
};
