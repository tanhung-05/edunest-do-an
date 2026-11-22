import { Form, Head, Link } from '@inertiajs/react';
import { EyeOff, Flag, MessageCircle, MessagesSquare } from 'lucide-react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import academic from '@/routes/academic';

type Comment = {
    id: number;
    author: string | null;
    body: string;
    status: 'HIEN' | 'DA_AN';
    hidden_reason: string | null;
};

type Post = {
    id: number;
    title: string;
    body: string;
    author: string | null;
    status: 'HIEN' | 'DA_AN';
    hidden_reason: string | null;
    created_at: string | null;
    image_url: string | null;
    reports: string[];
    comments: Comment[];
};

const filters = [
    { value: 'reported', label: 'Có báo cáo' },
    { value: 'hidden', label: 'Đang ẩn' },
    { value: 'all', label: 'Mới nhất' },
];

/** MH-GVU kiểm duyệt cộng đồng (QT-29 bước 6–9, A4): ẩn hoặc hiện lại bài và bình luận kèm lý do. */
export default function CommunityModeration({
    filter,
    posts,
}: {
    filter: string;
    posts: Post[];
}) {
    return (
        <Page
            title="Kiểm duyệt cộng đồng"
            description="Ẩn nội dung vi phạm kèm lý do; tác giả thấy lý do, người khác không thấy nội dung. Hiện lại cũng cần lý do và được ghi nhật ký."
            stats={[
                {
                    label: 'Bài trong danh sách',
                    value: posts.length,
                    icon: MessagesSquare,
                },
                {
                    label: 'Bị báo cáo',
                    value: posts.filter((p) => p.reports.length > 0).length,
                    icon: Flag,
                    attention: posts.some(
                        (p) => p.reports.length > 0 && p.status === 'HIEN',
                    ),
                },
                {
                    label: 'Đã ẩn',
                    value: posts.filter((p) => p.status === 'DA_AN').length,
                    icon: EyeOff,
                },
                {
                    label: 'Bình luận',
                    value: posts.reduce((n, p) => n + p.comments.length, 0),
                    icon: MessageCircle,
                },
            ]}
        >
            <Head title="Kiểm duyệt cộng đồng" />
            <nav aria-label="Lọc" className="flex flex-wrap gap-2 text-sm">
                {filters.map((f) => (
                    <Link
                        key={f.value}
                        href={academic.community.index({
                            query: { filter: f.value },
                        })}
                        className={cn(
                            'rounded-full border px-3 py-1.5 font-medium',
                            filter === f.value &&
                                'border-slate-950 bg-slate-950 text-white',
                        )}
                    >
                        {f.label}
                    </Link>
                ))}
            </nav>
            <ul className="grid gap-3">
                {posts.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Không có nội dung nào cần xem.
                    </li>
                )}
                {posts.map((p) => (
                    <li key={p.id}>
                        <Block className="grid gap-3 p-4 sm:p-5">
                            <div className="grid gap-1">
                                <p className="text-xs text-muted-foreground">
                                    {p.author}
                                    {p.created_at &&
                                        ` · ${vnTime(p.created_at)}`}
                                    {' · '}
                                    {p.status === 'HIEN'
                                        ? 'Đang hiện'
                                        : `Đã ẩn: ${p.hidden_reason}`}
                                </p>
                                <p className="font-semibold wrap-anywhere">
                                    {p.title}
                                </p>
                                <p className="text-sm wrap-anywhere whitespace-pre-line">
                                    {p.body}
                                </p>
                                {p.image_url && (
                                    <a
                                        href={p.image_url}
                                        target="_blank"
                                        rel="noopener"
                                        className="text-sm underline underline-offset-4"
                                    >
                                        Xem ảnh đính kèm
                                    </a>
                                )}
                            </div>
                            {p.reports.length > 0 && (
                                <div className="rounded-lg bg-muted/50 p-3 text-sm">
                                    <p className="font-medium">
                                        {p.reports.length} báo cáo
                                    </p>
                                    <ul className="mt-1 list-disc pl-5">
                                        {p.reports.map((r, i) => (
                                            <li
                                                key={i}
                                                className="wrap-anywhere"
                                            >
                                                {r}
                                            </li>
                                        ))}
                                    </ul>
                                </div>
                            )}
                            <Moderate
                                form={academic.community.posts.moderate.form(
                                    p.id,
                                )}
                                hidden={p.status === 'DA_AN'}
                                noun="bài"
                            />
                            {p.comments.length > 0 && (
                                <details className="border-t pt-3 text-sm">
                                    <summary className="cursor-pointer font-medium">
                                        Bình luận ({p.comments.length})
                                    </summary>
                                    <ul className="mt-2 grid gap-2">
                                        {p.comments.map((c) => (
                                            <li
                                                key={c.id}
                                                className="grid gap-2 rounded-lg border p-3"
                                            >
                                                <p className="text-xs text-muted-foreground">
                                                    {c.author} ·{' '}
                                                    {c.status === 'HIEN'
                                                        ? 'Đang hiện'
                                                        : `Đã ẩn: ${c.hidden_reason}`}
                                                </p>
                                                <p className="wrap-anywhere">
                                                    {c.body}
                                                </p>
                                                <Moderate
                                                    form={academic.community.comments.moderate.form(
                                                        c.id,
                                                    )}
                                                    hidden={
                                                        c.status === 'DA_AN'
                                                    }
                                                    noun="bình luận"
                                                />
                                            </li>
                                        ))}
                                    </ul>
                                </details>
                            )}
                        </Block>
                    </li>
                ))}
            </ul>
        </Page>
    );
}

function Moderate({
    form,
    hidden,
    noun,
}: {
    form: { action: string; method: 'post' };
    hidden: boolean;
    noun: string;
}) {
    const [open, setOpen] = useState(false);

    if (!open) {
        return (
            <div>
                <Button
                    size="sm"
                    variant="outline"
                    onClick={() => setOpen(true)}
                >
                    {hidden ? `Hiện lại ${noun}` : `Ẩn ${noun}`}
                </Button>
            </div>
        );
    }

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            onSuccess={() => setOpen(false)}
            className="flex flex-wrap items-start gap-2"
        >
            {({ processing, errors }) => (
                <>
                    <input
                        type="hidden"
                        name="hide"
                        value={hidden ? '0' : '1'}
                    />
                    <Input
                        name="reason"
                        required
                        maxLength={500}
                        placeholder="Lý do"
                        aria-label={`Lý do ${hidden ? 'hiện lại' : 'ẩn'} ${noun}`}
                        className="max-w-sm"
                    />
                    <Button type="submit" size="sm" disabled={processing}>
                        {hidden ? 'Hiện lại' : 'Ẩn'}
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => setOpen(false)}
                    >
                        Thôi
                    </Button>
                    {errors.reason && (
                        <p className="w-full text-sm text-destructive">
                            {errors.reason}
                        </p>
                    )}
                </>
            )}
        </Form>
    );
}
