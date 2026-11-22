import { Form, Head, Link, router } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import {
    Headset,
    Hourglass,
    MessageSquareDot,
    Phone,
    SendHorizontal,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { newRequestKey } from '@/lib/http';
import { cn } from '@/lib/utils';
import academic from '@/routes/academic';

type Thread = {
    id: number;
    status: 'CHO' | 'DA_TRA_LOI' | 'DONG';
    user: string;
    email: string | null;
    phone: string | null;
    is_guest: boolean;
    staff_unread: number;
    assigned_to: string | null;
    last_message_at: string | null;
};

type Message = {
    id: number;
    from_staff: boolean;
    sender: string;
    body: string;
    at: string;
};

type Props = {
    filter: 'mo' | 'cho' | 'dong' | 'tat-ca';
    threads: Thread[];
    active: Pick<
        Thread,
        'id' | 'status' | 'user' | 'email' | 'phone' | 'is_guest'
    > | null;
    messages: Message[];
};

const STATUS: Record<Thread['status'], string> = {
    CHO: 'Chờ trả lời',
    DA_TRA_LOI: 'Đã trả lời',
    DONG: 'Đã đóng',
};

const FILTERS: { key: Props['filter']; label: string }[] = [
    { key: 'mo', label: 'Đang mở' },
    { key: 'cho', label: 'Chờ trả lời' },
    { key: 'dong', label: 'Đã đóng' },
    { key: 'tat-ca', label: 'Tất cả' },
];

/**
 * P10 M29: hộp thư chat hỗ trợ của Giáo vụ. Tin mới đến qua kênh `support.staff` (Reverb) thì tải lại danh sách và
 * khung đang mở; không có Reverb thì tải lại mỗi 15 giây.
 */
export default function SupportInbox({
    filter,
    threads,
    active,
    messages,
}: Props) {
    const [requestKey, setRequestKey] = useState(newRequestKey);
    const list = useRef<HTMLOListElement>(null);
    const reload = () =>
        router.reload({ only: ['threads', 'messages', 'active'] });

    useEffect(() => {
        list.current?.scrollTo({ top: list.current.scrollHeight });
    }, [messages.length, active?.id]);

    useEffect(() => {
        if (echoIsConfigured()) {
            echo()
                .private('support.staff')
                .listen('.support.message', () =>
                    router.reload({ only: ['threads', 'messages', 'active'] }),
                );

            return () => echo().leave('support.staff');
        }

        const id = window.setInterval(() => {
            if (!document.hidden) {
                router.reload({ only: ['threads', 'messages', 'active'] });
            }
        }, 15_000);

        return () => window.clearInterval(id);
    }, []);

    const href = (q: Record<string, string | number>) =>
        academic.support.index({ query: { loc: filter, ...q } });

    return (
        <Page
            title="Chat hỗ trợ"
            description="Tin nhắn trực tiếp từ học viên, phụ huynh và khách chưa đăng nhập (có số điện thoại để gọi lại). Trả lời ở khung bên phải; đóng khi đã xử lý xong, người hỏi nhắn lại thì tự mở lại."
            stats={[
                {
                    label: 'Chờ trả lời',
                    value: threads.filter((t) => t.status === 'CHO').length,
                    icon: Hourglass,
                    attention: threads.some((t) => t.status === 'CHO'),
                },
                {
                    label: 'Tin chưa đọc',
                    value: threads.reduce((n, t) => n + t.staff_unread, 0),
                    icon: MessageSquareDot,
                },
                {
                    label: 'Khách chưa đăng nhập',
                    value: threads.filter((t) => t.is_guest).length,
                    icon: Phone,
                },
            ]}
        >
            <Head title="Chat hỗ trợ" />
            <nav
                aria-label="Lọc cuộc trò chuyện"
                className="flex flex-wrap gap-2"
            >
                {FILTERS.map((f) => (
                    <Link
                        key={f.key}
                        href={academic.support.index({
                            query: { loc: f.key },
                        })}
                        className={cn(
                            'rounded-full border px-3 py-1.5 text-sm',
                            filter === f.key
                                ? 'border-slate-950 bg-slate-950 text-white'
                                : 'hover:bg-accent',
                        )}
                    >
                        {f.label}
                    </Link>
                ))}
            </nav>
            <div className="grid gap-4 lg:grid-cols-[20rem_1fr]">
                <Block className="p-0">
                    {threads.length === 0 ? (
                        <p className="p-4 text-sm text-muted-foreground">
                            Chưa có cuộc trò chuyện nào ở mục này.
                        </p>
                    ) : (
                        <ul className="divide-y">
                            {threads.map((t) => (
                                <li key={t.id}>
                                    <Link
                                        href={href({ thread: t.id })}
                                        preserveScroll
                                        className={cn(
                                            'grid gap-1 px-4 py-3 text-sm hover:bg-accent',
                                            active?.id === t.id && 'bg-accent',
                                        )}
                                    >
                                        <span className="flex items-center justify-between gap-2">
                                            <span className="flex min-w-0 items-center gap-2">
                                                <span className="truncate font-medium">
                                                    {t.user}
                                                </span>
                                                {t.is_guest && (
                                                    <span className="shrink-0 rounded-full bg-amber-100 px-2 text-[11px] leading-5 font-semibold text-amber-800 dark:bg-amber-950 dark:text-amber-300">
                                                        Khách
                                                    </span>
                                                )}
                                            </span>
                                            {t.staff_unread > 0 && (
                                                <span className="rounded-full bg-destructive px-2 text-xs leading-5 font-bold text-white">
                                                    {t.staff_unread}
                                                </span>
                                            )}
                                        </span>
                                        <span className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                            <span
                                                className={cn(
                                                    t.status === 'CHO' &&
                                                        'font-medium text-amber-700 dark:text-amber-400',
                                                )}
                                            >
                                                {STATUS[t.status]}
                                            </span>
                                            <span>
                                                {vnTime(t.last_message_at)}
                                            </span>
                                            {t.assigned_to && (
                                                <span>
                                                    Phụ trách: {t.assigned_to}
                                                </span>
                                            )}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Block>
                <Block className="flex min-h-[28rem] flex-col p-0">
                    {active === null ? (
                        <div className="grid flex-1 place-items-center gap-2 p-8 text-center text-sm text-muted-foreground">
                            <Headset className="size-8" aria-hidden />
                            Chọn một cuộc trò chuyện bên trái để xem và trả lời.
                        </div>
                    ) : (
                        <>
                            <header className="flex flex-wrap items-center justify-between gap-2 border-b px-4 py-3">
                                <div className="min-w-0">
                                    <p className="truncate font-medium">
                                        {active.user}
                                    </p>
                                    <p className="flex flex-wrap items-center gap-x-2 text-xs text-muted-foreground">
                                        {active.is_guest && (
                                            <span className="font-semibold text-amber-700 dark:text-amber-400">
                                                Khách chưa đăng nhập
                                            </span>
                                        )}
                                        {active.email && (
                                            <span>{active.email}</span>
                                        )}
                                        {active.phone && (
                                            <a
                                                href={`tel:${active.phone}`}
                                                className="font-semibold text-foreground underline underline-offset-4"
                                            >
                                                Gọi {active.phone}
                                            </a>
                                        )}
                                        <span>{STATUS[active.status]}</span>
                                    </p>
                                </div>
                                {active.status !== 'DONG' && (
                                    <Form
                                        action={academic.support.close.url(
                                            active.id,
                                        )}
                                        method="post"
                                        options={{ preserveScroll: true }}
                                    >
                                        {({ processing }) => (
                                            <Button
                                                type="submit"
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Đóng cuộc trò chuyện
                                            </Button>
                                        )}
                                    </Form>
                                )}
                            </header>
                            <ol
                                ref={list}
                                aria-label="Tin nhắn"
                                aria-live="polite"
                                className="grid max-h-[60vh] flex-1 content-start gap-3 overflow-y-auto p-4"
                            >
                                {messages.map((m) => (
                                    <li
                                        key={m.id}
                                        className={cn(
                                            'grid max-w-[80%] gap-1 rounded-2xl px-3 py-2 text-sm',
                                            m.from_staff
                                                ? 'justify-self-end bg-slate-950 text-white'
                                                : 'justify-self-start border bg-background',
                                        )}
                                    >
                                        <span
                                            className={cn(
                                                'text-xs font-medium',
                                                m.from_staff
                                                    ? 'text-white/70'
                                                    : 'text-muted-foreground',
                                            )}
                                        >
                                            {m.sender} · {vnTime(m.at)}
                                        </span>
                                        <p className="wrap-anywhere whitespace-pre-line">
                                            {m.body}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                            <Form
                                action={academic.support.reply.url(active.id)}
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess={['body']}
                                onSuccess={() => {
                                    setRequestKey(newRequestKey());
                                    reload();
                                }}
                                className="grid gap-1.5 border-t p-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="request_key"
                                            value={requestKey}
                                        />
                                        <div className="flex items-end gap-2">
                                            <textarea
                                                name="body"
                                                required
                                                maxLength={2000}
                                                rows={2}
                                                aria-label="Trả lời học viên"
                                                placeholder="Nhập câu trả lời"
                                                className="min-h-11 flex-1 resize-y rounded-lg border bg-background px-3 py-2 text-sm"
                                            />
                                            <Button
                                                type="submit"
                                                size="icon"
                                                disabled={processing}
                                                aria-label="Gửi trả lời"
                                            >
                                                <SendHorizontal aria-hidden />
                                            </Button>
                                        </div>
                                        {(errors.body ??
                                            errors.request_key) && (
                                            <p className="text-sm text-destructive">
                                                {errors.body ??
                                                    errors.request_key}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>
                        </>
                    )}
                </Block>
            </div>
        </Page>
    );
}
