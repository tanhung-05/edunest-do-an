import { Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { Inbox } from 'lucide-react';
import { Block } from '@/components/page';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import type { Paginator, Progress } from '@/types/learning';
import { percentage } from '@/types/learning';

type Column<T> = { label: string; cell: (row: T) => ReactNode };

export function Records<T>({
    title,
    rows,
    columns,
    rowKey,
    empty,
}: {
    title: string;
    rows: T[];
    columns: Column<T>[];
    rowKey: (row: T) => string | number;
    empty: string;
}) {
    if (rows.length === 0)
        return (
            <Block className="flex items-center gap-4 border-dashed p-5 shadow-none">
                <span
                    aria-hidden
                    className="grid size-11 shrink-0 place-items-center rounded-xl bg-accent text-muted-foreground"
                >
                    <Inbox className="size-5" />
                </span>
                <p className="text-sm text-muted-foreground">{empty}</p>
            </Block>
        );

    return (
        <table
            role="table"
            className="block w-full min-w-0 table-fixed text-sm lg:table lg:border-separate lg:border-spacing-0 lg:overflow-hidden lg:rounded-2xl lg:border lg:border-border lg:bg-card lg:shadow-soft"
        >
            <caption className="sr-only">{title}</caption>
            <thead
                role="rowgroup"
                className="sr-only lg:not-sr-only lg:table-header-group"
            >
                <tr role="row" className="bg-accent/70">
                    {columns.map(({ label }) => (
                        <th
                            role="columnheader"
                            scope="col"
                            key={label}
                            className="px-4 py-3 text-left text-xs font-semibold wrap-anywhere text-muted-foreground"
                        >
                            {label}
                        </th>
                    ))}
                </tr>
            </thead>
            <tbody
                role="rowgroup"
                className="grid min-w-0 gap-3 lg:table-row-group"
            >
                {rows.map((row) => (
                    <tr
                        role="row"
                        key={rowKey(row)}
                        className="grid min-w-0 gap-3 rounded-2xl border border-border bg-card p-4 shadow-soft lg:table-row lg:rounded-none lg:border-0 lg:shadow-none lg:transition-colors lg:hover:bg-accent/50"
                    >
                        {columns.map(({ label, cell }) => (
                            <td
                                role="cell"
                                key={label}
                                className="grid min-w-0 gap-1 align-top wrap-anywhere lg:table-cell lg:border-t lg:border-border lg:px-4 lg:py-3.5"
                            >
                                <span
                                    aria-hidden="true"
                                    className="text-xs font-medium text-muted-foreground lg:hidden"
                                >
                                    {label}
                                </span>
                                <div className="min-w-0">{cell(row)}</div>
                            </td>
                        ))}
                    </tr>
                ))}
            </tbody>
        </table>
    );
}

export function Pagination({ page }: { page: Paginator<unknown> }) {
    if (!page.prev_page_url && !page.next_page_url) return null;
    return (
        <nav
            aria-label="Phân trang"
            className="flex flex-wrap items-center gap-2"
        >
            {page.prev_page_url && (
                <Button asChild variant="outline">
                    <Link href={page.prev_page_url}>Trang trước</Link>
                </Button>
            )}
            <span className="px-2 text-sm">
                Trang {page.current_page}/{page.last_page}
            </span>
            {page.next_page_url && (
                <Button asChild variant="outline">
                    <Link href={page.next_page_url}>Trang sau</Link>
                </Button>
            )}
        </nav>
    );
}

export function LearningProgress({ progress }: { progress: Progress }) {
    if (progress.required === 0) return null;
    return (
        <div className="grid min-w-0 gap-2 text-sm">
            <div className="flex flex-wrap justify-between gap-x-3 gap-y-1">
                <span>
                    {progress.completed}/{progress.required} bài bắt buộc
                </span>
                <strong>{percentage(progress.percent)}</strong>
            </div>
            <div
                role="progressbar"
                aria-label="Tiến độ bài bắt buộc"
                aria-valuemin={0}
                aria-valuemax={progress.required}
                aria-valuenow={progress.completed}
                className="h-2 overflow-hidden rounded-full bg-accent"
            >
                <div
                    className="h-full rounded-full bg-emerald-500 transition-[width] duration-700"
                    style={{
                        width: `${(100 * progress.completed) / progress.required}%`,
                    }}
                />
            </div>
        </div>
    );
}

export function LearningBadge({
    children,
    attention = false,
}: {
    children: ReactNode;
    attention?: boolean;
}) {
    return (
        <Badge
            variant={attention ? 'default' : 'secondary'}
            className={
                attention
                    ? 'max-w-full bg-amber-100 px-2.5 py-1 whitespace-normal text-amber-900 ring-1 ring-amber-300 dark:bg-amber-400/15 dark:text-amber-200'
                    : 'max-w-full bg-white/95 px-2.5 py-1 whitespace-normal text-slate-800 ring-1 ring-slate-200 dark:bg-slate-800 dark:text-slate-100'
            }
        >
            {children}
        </Badge>
    );
}
