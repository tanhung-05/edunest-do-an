import { Form } from '@inertiajs/react';
import { useId, useState } from 'react';
import { Field, textareaClass } from '@/components/page';
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
import { Input } from '@/components/ui/input';
import type { RouteFormDefinition } from '@/wayfinder';

export function ActionError({ message }: { message?: string | null }) {
    return message ? (
        <p role="alert" className="text-sm wrap-anywhere text-destructive">
            {message}
        </p>
    ) : null;
}

export function ReasonDialog({
    url,
    label,
    subject,
    revoke = false,
}: {
    url: string;
    label: string;
    subject: string;
    revoke?: boolean;
}) {
    const [open, setOpen] = useState(false);
    const [busy, setBusy] = useState(false);
    const [requestError, setRequestError] = useState('');
    const id = useId();
    const fieldLabel = revoke ? 'Lý do thu hồi' : 'Lý do cấp thêm lượt';
    return (
        <Dialog
            open={open}
            onOpenChange={(next) => {
                if (!busy) {
                    setOpen(next);
                    setRequestError('');
                }
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
                    <DialogTitle>
                        {revoke ? 'Thu hồi chứng nhận' : 'Cấp thêm một lượt'}
                    </DialogTitle>
                    <DialogDescription className="wrap-anywhere">
                        {subject}
                    </DialogDescription>
                </DialogHeader>
                <Form
                    action={url}
                    method="post"
                    options={{ preserveScroll: true }}
                    onStart={() => {
                        setBusy(true);
                        setRequestError('');
                    }}
                    onFinish={() => setBusy(false)}
                    onSuccess={() => setOpen(false)}
                    onNetworkError={() => {
                        setRequestError(
                            'Chưa xác nhận được kết quả. Hãy tải lại danh sách.',
                        );
                        return false;
                    }}
                    className="grid gap-4"
                >
                    {({ errors, processing }) => (
                        <>
                            <Field label={fieldLabel} htmlFor={id}>
                                <textarea
                                    id={id}
                                    name="reason"
                                    required
                                    maxLength={500}
                                    className={textareaClass}
                                    aria-invalid={Boolean(errors.reason)}
                                    aria-describedby={
                                        errors.reason
                                            ? id + '-error'
                                            : undefined
                                    }
                                />
                                {errors.reason && (
                                    <p
                                        id={id + '-error'}
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.reason}
                                    </p>
                                )}
                            </Field>
                            <ActionError
                                message={errors.general ?? requestError}
                            />
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
                                <Button
                                    type="submit"
                                    variant={revoke ? 'destructive' : 'default'}
                                    disabled={processing}
                                >
                                    {processing
                                        ? 'Đang gửi…'
                                        : revoke
                                          ? 'Xác nhận thu hồi'
                                          : 'Xác nhận cấp lượt'}
                                </Button>
                            </DialogFooter>
                        </>
                    )}
                </Form>
            </DialogContent>
        </Dialog>
    );
}

export function RecordSearch({
    form,
    label,
    value,
}: {
    form: RouteFormDefinition<'get'>;
    label: string;
    value: string;
}) {
    const id = useId();
    return (
        <Form
            {...form}
            className="grid max-w-lg items-end gap-3 sm:grid-cols-[minmax(0,1fr)_auto]"
        >
            {({ processing, errors }) => (
                <>
                    <Field label={label} htmlFor={id} error={errors.q}>
                        <Input id={id} name="q" defaultValue={value} />
                    </Field>
                    <Button type="submit" disabled={processing}>
                        {processing ? 'Đang tìm…' : 'Tìm'}
                    </Button>
                </>
            )}
        </Form>
    );
}
