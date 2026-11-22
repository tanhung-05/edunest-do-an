import { Form, Link } from '@inertiajs/react';
import { CheckCircle2 } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type DiscussionRow = {
    id: number;
    body: string;
    second: number | null;
    status: string;
    status_label: string;
    author: string | null;
    mine: boolean;
    created_at: string | null;
    follower_count: number;
    following: boolean;
    follow_url: string | null;
    unaccept_url: string | null;
    replies: {
        id: number;
        body: string;
        author: string | null;
        accepted: boolean;
        created_at: string | null;
        accept_url: string | null;
    }[];
};

export const clock = (s: number) =>
    `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`;

/** Một câu hỏi hỏi đáp và các trả lời (QT-18). `onSeek` cho phép bấm mốc giây để tua video. */
export function DiscussionThread({
    d,
    onSeek,
    children,
}: {
    d: DiscussionRow;
    onSeek?: (second: number) => void;
    children?: React.ReactNode;
}) {
    return (
        <article className="grid gap-2">
            <header className="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
                <span className="font-medium">{d.author}</span>
                <span className="text-muted-foreground">
                    {vnTime(d.created_at)}
                </span>
                {d.second !== null &&
                    (onSeek ? (
                        <button
                            type="button"
                            onClick={() => onSeek(d.second ?? 0)}
                            className="rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900 tabular-nums dark:bg-sky-400/15 dark:text-sky-200"
                        >
                            {clock(d.second)}
                        </button>
                    ) : (
                        <span className="rounded-full bg-muted px-2 py-0.5 text-xs tabular-nums">
                            {clock(d.second)}
                        </span>
                    ))}
                <span
                    className={cn(
                        'rounded-full px-2 py-0.5 text-xs font-semibold',
                        d.status === 'DA_CHAP_NHAN'
                            ? 'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-200'
                            : d.status === 'CHUA_TRA_LOI'
                              ? 'bg-amber-soft text-navy'
                              : 'bg-muted',
                    )}
                >
                    {d.status_label}
                </span>
            </header>
            <p className="wrap-anywhere whitespace-pre-wrap">{d.body}</p>
            {d.replies.length > 0 && (
                <ul className="grid gap-2 border-l-2 border-border pl-3">
                    {d.replies.map((r) => (
                        <li
                            key={r.id}
                            className={cn(
                                'grid gap-1 rounded-lg p-2.5 text-sm',
                                r.accepted
                                    ? 'bg-emerald-50 ring-1 ring-emerald-500/40 dark:bg-emerald-400/10'
                                    : 'bg-muted/60',
                            )}
                        >
                            <div className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">{r.author}</span>
                                <span className="text-xs text-muted-foreground">
                                    {vnTime(r.created_at)}
                                </span>
                                {r.accepted && (
                                    <span className="inline-flex items-center gap-1 text-xs font-semibold text-emerald-700 dark:text-emerald-300">
                                        <CheckCircle2 className="size-3.5" />{' '}
                                        Được chấp nhận
                                    </span>
                                )}
                            </div>
                            <p className="wrap-anywhere whitespace-pre-wrap">
                                {r.body}
                            </p>
                            {r.accept_url && (
                                <Form
                                    action={r.accept_url}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            size="sm"
                                            variant="ghost"
                                            disabled={processing}
                                            className="h-7 px-2"
                                        >
                                            Chấp nhận câu trả lời này
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </li>
                    ))}
                </ul>
            )}
            <div className="flex flex-wrap items-center gap-2">
                {d.follow_url && (
                    <Form
                        action={d.follow_url}
                        method="post"
                        options={{ preserveScroll: true }}
                    >
                        {({ processing }) => (
                            <Button
                                size="sm"
                                variant={d.following ? 'secondary' : 'outline'}
                                disabled={processing}
                            >
                                Cũng thắc mắc · {d.follower_count}
                            </Button>
                        )}
                    </Form>
                )}
                {!d.follow_url && d.follower_count > 0 && (
                    <span className="text-xs text-muted-foreground">
                        {d.follower_count} người cũng thắc mắc
                    </span>
                )}
                {d.unaccept_url && (
                    <Link
                        href={d.unaccept_url}
                        method="delete"
                        as="button"
                        preserveScroll
                        className="text-xs text-muted-foreground underline-offset-4 hover:underline"
                    >
                        Bỏ chấp nhận
                    </Link>
                )}
            </div>
            {children}
        </article>
    );
}
