import { Form } from '@inertiajs/react';
import { MoreHorizontal } from 'lucide-react';
import { useState } from 'react';
import { textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import type { RouteFormDefinition } from '@/wayfinder';

type Variant = 'default' | 'destructive' | 'secondary' | 'outline';

export type ConfirmSpec = {
    form: RouteFormDefinition<'post'> | RouteFormDefinition<'get'>;
    label: string;
    title: string;
    description?: string;
    confirmLabel: string;
    variant?: Variant;
    reasonField?: { name: string; label: string; required?: boolean };
    /** Trường ẩn gửi kèm (ví dụ `confirmed=1`, mã dòng). */
    hidden?: Record<string, string>;
};

/** Hộp xác nhận điều khiển từ ngoài. "Hủy" đóng hộp, không gửi yêu cầu (BR-HT-03). */
export function ConfirmDialog({
    spec,
    open,
    onOpenChange,
}: {
    spec: ConfirmSpec | null;
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent>
                {spec && (
                    <Form
                        {...spec.form}
                        options={{ preserveScroll: true }}
                        onSuccess={() => onOpenChange(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <DialogHeader>
                                    <DialogTitle>{spec.title}</DialogTitle>
                                    {spec.description && (
                                        <DialogDescription>
                                            {spec.description}
                                        </DialogDescription>
                                    )}
                                </DialogHeader>
                                {Object.entries(spec.hidden ?? {}).map(
                                    ([name, value]) => (
                                        <input
                                            key={name}
                                            type="hidden"
                                            name={name}
                                            value={value}
                                        />
                                    ),
                                )}
                                {spec.reasonField && (
                                    <div className="grid gap-2">
                                        <label
                                            htmlFor={spec.reasonField.name}
                                            className="text-sm font-medium"
                                        >
                                            {spec.reasonField.label}
                                        </label>
                                        <textarea
                                            id={spec.reasonField.name}
                                            name={spec.reasonField.name}
                                            required={spec.reasonField.required}
                                            maxLength={2000}
                                            className={textareaClass}
                                        />
                                    </div>
                                )}
                                {Object.values(errors).map((e) => (
                                    <p
                                        key={e}
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {e}
                                    </p>
                                ))}
                                <DialogFooter>
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => onOpenChange(false)}
                                    >
                                        Hủy
                                    </Button>
                                    <Button
                                        type="submit"
                                        variant={
                                            spec.variant === 'destructive'
                                                ? 'destructive'
                                                : 'default'
                                        }
                                        disabled={processing}
                                    >
                                        {spec.confirmLabel}
                                    </Button>
                                </DialogFooter>
                            </>
                        )}
                    </Form>
                )}
            </DialogContent>
        </Dialog>
    );
}

/** Nút thao tác cần xác nhận (gỡ vai, xóa…). */
export function ConfirmAction({
    size = 'default',
    ...spec
}: ConfirmSpec & { size?: 'default' | 'sm' }) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                variant={spec.variant ?? 'default'}
                size={size}
                onClick={() => setOpen(true)}
            >
                {spec.label}
            </Button>
            <ConfirmDialog spec={spec} open={open} onOpenChange={setOpen} />
        </>
    );
}

/** Menu "Thao tác khác": gom thao tác nguy hiểm, mỗi mục mở hộp xác nhận (BR-HT-03, CR-11). */
export function MoreActions({ actions }: { actions: ConfirmSpec[] }) {
    const [current, setCurrent] = useState<ConfirmSpec | null>(null);

    if (actions.length === 0) {
        return null;
    }

    return (
        <>
            <DropdownMenu>
                <DropdownMenuTrigger asChild>
                    <Button variant="outline">
                        <MoreHorizontal />
                        Thao tác khác
                    </Button>
                </DropdownMenuTrigger>
                <DropdownMenuContent align="end">
                    {actions.map((a) => (
                        <DropdownMenuItem
                            key={a.label}
                            onSelect={() => setCurrent(a)}
                            className={
                                a.variant === 'destructive'
                                    ? 'text-destructive'
                                    : undefined
                            }
                        >
                            {a.label}
                        </DropdownMenuItem>
                    ))}
                </DropdownMenuContent>
            </DropdownMenu>
            <ConfirmDialog
                spec={current}
                open={current !== null}
                onOpenChange={(o) => !o && setCurrent(null)}
            />
        </>
    );
}
