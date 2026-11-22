import { Form, Head, Link, router } from '@inertiajs/react';
import {
    EyeOff,
    Hash,
    Heart,
    MessageCircle,
    MessagesSquare,
} from 'lucide-react';
import { useState } from 'react';
import { PostMeta } from '@/components/community/post-meta';
import type { PostCard } from '@/components/community/post-meta';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import student from '@/routes/student';
import type { IdName, Option } from '@/types/course';

type Props = {
    topic: string | null;
    topics: Option[];
    courses: IdName[];
    posts: {
        data: (PostCard & {
            excerpt: string;
            comment_count: number;
            liked: boolean;
        })[];
        links: { url: string | null; label: string; active: boolean }[];
    };
    my_hidden_comments: { id: number; body: string; reason: string | null }[];
};

/** MH-HV cộng đồng (QT-29): bảng tin chung theo chủ đề, đăng bài kèm ảnh và thẻ khóa. */
export default function CommunityIndex({
    topic,
    topics,
    courses,
    posts,
    my_hidden_comments,
}: Props) {
    const [writing, setWriting] = useState(false);
    const filter = (value: string | null) =>
        router.get(
            student.feed.index.url({
                query: value ? { topic: value } : {},
            }),
            {},
            { preserveState: true },
        );

    return (
        <Page
            title="Cộng đồng"
            description="Trao đổi kinh nghiệm học tập với các bạn học viên. Giữ lời lẽ tôn trọng; bài vi phạm có thể bị giáo vụ ẩn."
            actions={
                <Button onClick={() => setWriting((v) => !v)}>
                    {writing ? 'Đóng' : 'Viết bài'}
                </Button>
            }
            stats={[
                {
                    label: 'Bài trên trang',
                    value: posts.data.length,
                    icon: MessagesSquare,
                },
                {
                    label: 'Bình luận',
                    value: posts.data.reduce((n, p) => n + p.comment_count, 0),
                    icon: MessageCircle,
                },
                { label: 'Chủ đề', value: topics.length, icon: Hash },
                {
                    label: 'Bình luận của bạn bị ẩn',
                    value: my_hidden_comments.length,
                    icon: EyeOff,
                    attention: my_hidden_comments.length > 0,
                },
            ]}
        >
            <Head title="Cộng đồng" />
            {writing && (
                <Block className="grid gap-3 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Bài mới</h2>
                    <Form
                        {...student.feed.store.form()}
                        encType="multipart/form-data"
                        className="grid gap-3 sm:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <label className="grid gap-1 text-sm">
                                    Chủ đề
                                    <select
                                        name="topic"
                                        required
                                        className="h-9 rounded-md border bg-background px-2"
                                    >
                                        {topics.map((t) => (
                                            <option
                                                key={t.value}
                                                value={t.value}
                                            >
                                                {t.label}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Gắn thẻ khóa (không bắt buộc)
                                    <select
                                        name="course_id"
                                        className="h-9 rounded-md border bg-background px-2"
                                    >
                                        <option value="">Không gắn</option>
                                        {courses.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm sm:col-span-2">
                                    Tiêu đề
                                    <Input
                                        name="title"
                                        required
                                        maxLength={200}
                                    />
                                </label>
                                <label className="grid gap-1 text-sm sm:col-span-2">
                                    Nội dung
                                    <textarea
                                        name="body"
                                        required
                                        rows={5}
                                        maxLength={10000}
                                        className="rounded-md border bg-background px-3 py-2"
                                    />
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Ảnh (không bắt buộc, tối đa 4 MB)
                                    <Input
                                        type="file"
                                        name="image"
                                        accept="image/*"
                                    />
                                </label>
                                <div className="grid content-end justify-items-start gap-1">
                                    <Button type="submit" disabled={processing}>
                                        Đăng bài
                                    </Button>
                                </div>
                                {Object.values(errors)[0] && (
                                    <p className="text-sm text-destructive sm:col-span-2">
                                        {Object.values(errors)[0]}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                </Block>
            )}
            <nav
                aria-label="Chủ đề"
                className="flex flex-wrap gap-2 text-sm font-medium"
            >
                {[{ value: '', label: 'Tất cả' }, ...topics].map((t) => (
                    <button
                        key={t.value}
                        type="button"
                        onClick={() => filter(t.value || null)}
                        className={cn(
                            'rounded-full border px-3 py-1.5',
                            (topic ?? '') === t.value &&
                                'border-slate-950 bg-slate-950 text-white',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </nav>
            <ul className="grid gap-3">
                {posts.data.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Chưa có bài nào. Hãy là người mở đầu.
                    </li>
                )}
                {posts.data.map((p) => (
                    <li key={p.id}>
                        <Block className="grid gap-2 p-4 sm:p-5">
                            <PostMeta p={p} />
                            <Link
                                href={student.feed.show(p.id)}
                                className="text-lg font-semibold wrap-anywhere hover:underline"
                            >
                                {p.title}
                            </Link>
                            <p className="text-sm wrap-anywhere whitespace-pre-line text-muted-foreground">
                                {p.excerpt}
                            </p>
                            <p className="flex items-center gap-4 text-sm text-muted-foreground">
                                <span className="inline-flex items-center gap-1">
                                    <Heart
                                        aria-hidden
                                        className={cn(
                                            'size-4',
                                            p.liked &&
                                                'fill-rose-500 text-rose-500',
                                        )}
                                    />
                                    {p.like_count}
                                    <span className="sr-only"> lượt thích</span>
                                </span>
                                <span className="inline-flex items-center gap-1">
                                    <MessageCircle
                                        aria-hidden
                                        className="size-4"
                                    />
                                    {p.comment_count}
                                    <span className="sr-only"> bình luận</span>
                                </span>
                            </p>
                        </Block>
                    </li>
                ))}
            </ul>
            {posts.links.length > 3 && (
                <nav className="flex flex-wrap gap-1" aria-label="Phân trang">
                    {posts.links.map((l, i) =>
                        l.url ? (
                            <Link
                                key={i}
                                href={l.url}
                                className={cn(
                                    'rounded border px-3 py-1 text-sm',
                                    l.active && 'bg-slate-950 text-white',
                                )}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ) : null,
                    )}
                </nav>
            )}
            {my_hidden_comments.length > 0 && (
                <Block className="grid gap-2 p-4 sm:p-6">
                    <h2 className="font-semibold">Bình luận của bạn bị ẩn</h2>
                    <ul className="grid gap-2 text-sm">
                        {my_hidden_comments.map((c) => (
                            <li key={c.id} className="rounded-lg border p-3">
                                <p className="wrap-anywhere">{c.body}</p>
                                <p className="mt-1 text-destructive">
                                    Lý do: {c.reason}
                                </p>
                            </li>
                        ))}
                    </ul>
                </Block>
            )}
        </Page>
    );
}
