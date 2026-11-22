import { router } from '@inertiajs/react';
import { useState } from 'react';
import { vnd } from '@/components/commerce';
import { Button } from '@/components/ui/button';

/** Nút Gia hạn (MH-HV-02, QT-15): tạo đơn GIA_HAN một dòng rồi chuyển sang trang đơn. */
export function RenewButton({
    url,
    price,
    expired,
}: {
    url: string;
    price: number | null;
    expired: boolean;
}) {
    const [busy, setBusy] = useState(false);

    return (
        <Button
            variant={expired ? 'default' : 'outline'}
            disabled={busy}
            onClick={() =>
                router.post(
                    url,
                    {},
                    {
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                    },
                )
            }
        >
            {expired ? 'Gia hạn để học tiếp' : 'Gia hạn thêm'}
            {price !== null && ` · ${vnd(price)}`}
        </Button>
    );
}
