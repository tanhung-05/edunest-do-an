import { Form, router } from '@inertiajs/react';
import { useId, useState } from 'react';
import { textareaClass } from '@/components/page';
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
import type { RouteFormDefinition } from '@/wayfinder';

export function OrderConfirm({
    form,
    label,
    subject,
    description,
    onResult,
    refund = false,
}: {
    form: RouteFormDefinition<'post'>;
    label: string;
    subject: string;
    description: string;
    onResult: (error: string | null) => void;
    refund?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const id = useId();

    function refreshAfterError(message: string) {
        onResult(message);
        setOpen(false);
        router.reload({ only: ['order', 'audit'] });
    }

    function requestFailed() {
        refreshAfterError(
            'Chưa xác nhận được kết quả. Hãy kiểm tra lại chi tiết đơn.',
        );
        return false;
    }

    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!busy) setOpen(next);
            }}
        >
            <DialogTrigger asChild>
                <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    className="h-auto min-h-9 max-w-full whitespace-normal"
                >
                    {label}
                </Button>
            </DialogTrigger>
            <DialogContent className="max-h-[calc(100dvh-2rem)] overflow-y-auto [&>button]:hidden">
                <DialogHeader>
                    <DialogTitle>{label}?</DialogTitle>
                    <DialogDescription>{description}</DialogDescription>
                </DialogHeader>
                <p className="rounded-md bg-secondary p-3 text-sm font-semibold wrap-anywhere">
                    {subject}
                </p>
                <Form
                    {...form}
                    options={{ preserveScroll: true }}
                    onStart={() => {
                        setBusy(true);
                        onResult(null);
                    }}
                    onFinish={() => setBusy(false)}
                    onSuccess={() => setOpen(false)}
                    onError={(errors) => {
                        if (errors.general) {
                            refreshAfterError(errors.general);
                        }
                    }}
                    onNetworkError={requestFailed}
                    onHttpException={requestFailed}
                    className="grid gap-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-2">
                                <label
                                    htmlFor={id + '-reason'}
                                    className="text-sm font-medium"
                                >
                                    {refund ? 'Lý do hoàn khoản' : 'Lý do'}
                                </label>
                                <textarea
                                    id={id + '-reason'}
                                    name="reason"
                                    className={textareaClass}
                                    required
                                    maxLength={500}
                                    disabled={processing}
                                    aria-invalid={Boolean(errors.reason)}
                                    aria-describedby={
                                        errors.reason
                                            ? id + '-reason-error'
                                            : undefined
                                    }
                                />
                                {errors.reason && (
                                    <p
                                        id={id + '-reason-error'}
                                        className="text-sm text-destructive"
                                        role="alert"
                                    >
                                        {errors.reason}
                                    </p>
                                )}
                            </div>
                            {refund && (
                                <div className="grid gap-2">
                                    <label className="flex items-start gap-2 text-sm">
                                        <input
                                            type="checkbox"
                                            name="confirmed"
                                            value="1"
                                            required
                                            disabled={processing}
                                            className="mt-1 size-4 shrink-0 accent-primary"
                                            aria-invalid={Boolean(
                                                errors.confirmed,
                                            )}
                                            aria-describedby={
                                                errors.confirmed
                                                    ? id + '-confirm-error'
                                                    : undefined
                                            }
                                        />
                                        Tôi xác nhận đã hoàn khoản này.
                                    </label>
                                    {errors.confirmed && (
                                        <p
                                            id={id + '-confirm-error'}
                                            className="text-sm text-destructive"
                                            role="alert"
                                        >
                                            {errors.confirmed}
                                        </p>
                                    )}
                                </div>
                            )}
                            <DialogFooter>
                                <DialogClose asChild>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        disabled={processing}
                                    >
                                        Hủy
                                    </Button>
                                </DialogClose>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Đang gửi…' : 'Xác nhận'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}
