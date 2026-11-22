import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import notifications from '@/routes/account/notifications';

type Item = {
    id: string;
    kind: string;
    data: { title?: string; body?: string; url?: string | null };
    read: boolean;
    created_at: string | null;
};

type Kind = { code: string; label: string; mutable: boolean; muted: boolean };

type Paged = {
    data: Item[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function Notifications({
    notifications: page,
    kinds,
}: {
    notifications: Paged;
    kinds: Kind[];
}) {
    const [muted, setMuted] = useState<string[]>(
        kinds.filter((k) => k.muted).map((k) => k.code),
    );
    const [saving, setSaving] = useState(false);
    const open = (n: Item) => router.post(notifications.read(n.id).url);
    const save = () =>
        router.put(
            notifications.preferences().url,
            { muted },
            {
                preserveScroll: true,
                onStart: () => setSaving(true),
                onFinish: () => setSaving(false),
            },
        );

    return (
        <Page
            title="Thông báo"
            actions={
                <Form {...notifications.readAll.form()}>
                    <Button variant="outline" size="sm">
                        Đánh dấu đã đọc tất cả
                    </Button>
                </Form>
            }
        >
            <Head title="Thông báo" />
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <Block>
                    <ul className="divide-y divide-border">
                        {page.data.map((n) => (
                            <li
                                key={n.id}
                                className="py-3 first:pt-0 last:pb-0"
                            >
                                <button
                                    type="button"
                                    onClick={() => open(n)}
                                    className="flex w-full gap-3 rounded-lg p-2 text-left hover:bg-accent"
                                >
                                    <span
                                        aria-hidden
                                        className={cn(
                                            'mt-2 size-2 shrink-0 rounded-full',
                                            n.read
                                                ? 'bg-transparent'
                                                : 'bg-primary',
                                        )}
                                    />
                                    <span className="min-w-0 flex-1">
                                        <span
                                            className={cn(
                                                'block',
                                                !n.read && 'font-semibold',
                                            )}
                                        >
                                            {n.data.title}
                                            {!n.read && (
                                                <span className="sr-only">
                                                    {' '}
                                                    (chưa đọc)
                                                </span>
                                            )}
                                        </span>
                                        <span className="block text-sm wrap-anywhere text-muted-foreground">
                                            {n.data.body}
                                        </span>
                                        <span className="block text-xs text-muted-foreground">
                                            {vnTime(n.created_at)}
                                        </span>
                                    </span>
                                </button>
                            </li>
                        ))}
                        {page.data.length === 0 && (
                            <li className="text-muted-foreground">
                                Chưa có thông báo.
                            </li>
                        )}
                    </ul>
                    {page.last_page > 1 && (
                        <nav
                            aria-label="Phân trang thông báo"
                            className="mt-4 flex justify-between text-sm"
                        >
                            {page.prev_page_url ? (
                                <Link href={page.prev_page_url}>
                                    Trang trước
                                </Link>
                            ) : (
                                <span />
                            )}
                            <span>
                                {page.current_page}/{page.last_page}
                            </span>
                            {page.next_page_url ? (
                                <Link href={page.next_page_url}>Trang sau</Link>
                            ) : (
                                <span />
                            )}
                        </nav>
                    )}
                </Block>
                <Block className="h-fit">
                    <h2 className="mb-3 font-semibold">Loại thông báo nhận</h2>
                    <div className="grid gap-3">
                        {kinds.map((k) => (
                            <label
                                key={k.code}
                                className="flex items-center gap-2 text-sm"
                            >
                                <input
                                    type="checkbox"
                                    checked={!muted.includes(k.code)}
                                    disabled={!k.mutable}
                                    onChange={(e) =>
                                        setMuted((m) =>
                                            e.target.checked
                                                ? m.filter((c) => c !== k.code)
                                                : [...m, k.code],
                                        )
                                    }
                                    className="size-4"
                                />
                                {k.label}
                                {!k.mutable && (
                                    <span className="text-xs text-muted-foreground">
                                        (bắt buộc)
                                    </span>
                                )}
                            </label>
                        ))}
                        <Button
                            size="sm"
                            className="w-fit"
                            disabled={saving}
                            onClick={save}
                        >
                            Lưu lựa chọn
                        </Button>
                    </div>
                </Block>
            </div>
        </Page>
    );
}
