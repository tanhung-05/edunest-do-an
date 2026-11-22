import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, EyeOff, MessageCircleQuestion } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import discussions from '@/routes/academic/discussions';

type Row = {
    id: number;
    body: string;
    author: string | null;
    course: string | null;
    lesson: string | null;
    status_label: string;
    reason: string | null;
    created_at: string | null;
    hide_url: string | null;
    unhide_url: string | null;
};

/** QT-18 HD-4: Giáo vụ ẩn câu hỏi không phù hợp (có lý do) và hiện lại. */
export default function AcademicDiscussions({
    tab,
    discussions: rows,
}: {
    tab: 'visible' | 'hidden';
    discussions: Row[];
}) {
    return (
        <Page
            title="Kiểm duyệt hỏi đáp"
            description="Ẩn câu hỏi không phù hợp ở mọi khóa. Hiện lại sẽ trả về trạng thái trước khi ẩn."
            stats={[
                {
                    label: tab === 'hidden' ? 'Đang ẩn' : 'Đang hiển thị',
                    value: rows.length,
                    icon: tab === 'hidden' ? EyeOff : MessageCircleQuestion,
                },
                {
                    label: 'Khóa liên quan',
                    value: new Set(rows.map((r) => r.course)).size,
                    icon: BookOpen,
                },
            ]}
        >
            <Head title="Kiểm duyệt hỏi đáp" />
            <nav className="flex gap-2" aria-label="Lọc">
                {(
                    [
                        ['visible', 'Đang hiển thị'],
                        ['hidden', 'Đã ẩn'],
                    ] as const
                ).map(([key, label]) => (
                    <Link
                        key={key}
                        href={discussions.index({ query: { tab: key } }).url}
                        className={cn(
                            'rounded-full px-4 py-1.5 text-sm font-medium',
                            tab === key
                                ? 'bg-navy text-white dark:bg-white/15'
                                : 'bg-muted hover:bg-accent',
                        )}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <Block className="p-0 md:p-0">
                <ul className="divide-y divide-border">
                    {rows.map((d) => (
                        <li key={d.id} className="grid gap-2 px-5 py-4 md:px-6">
                            <p className="text-xs text-muted-foreground">
                                {d.course} · {d.lesson} · {d.author} ·{' '}
                                {vnTime(d.created_at)} · {d.status_label}
                            </p>
                            <p className="wrap-anywhere whitespace-pre-wrap">
                                {d.body}
                            </p>
                            {d.reason && (
                                <p className="text-sm text-muted-foreground">
                                    Lý do ẩn: {d.reason}
                                </p>
                            )}
                            {d.hide_url && (
                                <Form
                                    action={d.hide_url}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                    className="flex flex-wrap items-start gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <label
                                                htmlFor={`reason-${d.id}`}
                                                className="sr-only"
                                            >
                                                Lý do ẩn
                                            </label>
                                            <Input
                                                id={`reason-${d.id}`}
                                                name="reason"
                                                required
                                                maxLength={500}
                                                placeholder="Lý do ẩn"
                                                className="max-w-sm"
                                            />
                                            <Button
                                                size="sm"
                                                variant="outline"
                                                disabled={processing}
                                            >
                                                Ẩn
                                            </Button>
                                            {errors.reason && (
                                                <p className="w-full text-sm text-destructive">
                                                    {errors.reason}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                            )}
                            {d.unhide_url && (
                                <Form
                                    action={d.unhide_url}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Hiện lại
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </li>
                    ))}
                    {rows.length === 0 && (
                        <li className="px-6 py-8 text-center text-muted-foreground">
                            Không có câu hỏi.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
