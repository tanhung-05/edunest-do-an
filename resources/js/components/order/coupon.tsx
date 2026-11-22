import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Block } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

/** Ô nhập mã giảm giá ở đơn chờ thanh toán (MH-HV-09, QT-13 b7). Một mã mỗi đơn, áp trước khi tạo thanh toán. */
export function CouponForm({ url }: { url: string }) {
    const [code, setCode] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    return (
        <Block className="p-4 sm:p-6">
            <form
                className="grid gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.post(
                        url,
                        { code },
                        {
                            preserveScroll: true,
                            onStart: () => setBusy(true),
                            onFinish: () => setBusy(false),
                            onError: (err) =>
                                setError(
                                    err.code ??
                                        err.general ??
                                        'Không áp được mã.',
                                ),
                            onSuccess: () => setError(null),
                        },
                    );
                }}
            >
                <label htmlFor="coupon-code" className="text-sm font-medium">
                    Mã giảm giá
                </label>
                <div className="flex gap-2">
                    <Input
                        id="coupon-code"
                        value={code}
                        onChange={(e) => setCode(e.target.value)}
                        maxLength={64}
                        autoComplete="off"
                        className="font-mono uppercase"
                        aria-invalid={error !== null}
                        aria-describedby={error ? 'coupon-error' : undefined}
                    />
                    <Button
                        type="submit"
                        variant="outline"
                        disabled={busy || code.trim() === ''}
                    >
                        Áp dụng
                    </Button>
                </div>
                {error && (
                    <p
                        id="coupon-error"
                        role="alert"
                        className="text-sm text-destructive"
                    >
                        {error}
                    </p>
                )}
            </form>
        </Block>
    );
}
