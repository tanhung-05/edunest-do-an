import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft, Flag, Hash, Heart, MessageCircle } from 'lucide-react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import { PostMeta } from '@/components/community/post-meta';
import type { PostCard } from '@/components/community/post-meta';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Props = {
    post: PostCard & {
        body: string;
        liked: boolean;
        reported: boolean;
        can_edit: boolean;
    };
    comments: {
        id: number;
        author: string | null;
        body: string;
        created_at: string | null;
        hidden_reason: string | null;
    }[];
};

/** MH-HV chi tiết bài cộng đồng: thích, báo cáo, bình luận, chủ bài đã xác minh sửa bài đang hiện (CR-177). */
export default function CommunityShow({ post, comments }: Props) {
    const [editing, setEditing] = useState(false);
    const [reporting, setReporting] = useState(false);
    const visible = post.hidden_reason === null;

    return (
        <Page
            title={post.title}
            description={`${post.author ?? 'Học viên'}${post.created_at ? `, ${vnTime(post.created_at)}` : ''}${post.course ? ` · khóa ${post.course}` : ''}`}
            stats={[
                { label: 'Chủ đề', value: post.topic, icon: Hash },
                { label: 'Lượt thích', value: post.like_count, icon: Heart },
                {
                    label: 'Bình luận',
                    value: comments.length,
                    icon: MessageCircle,
                },
            ]}
            actions={
                <Button variant="outline" asChild>
                    <Link href={student.feed.index()}>
                        <ArrowLeft aria-hidden />
                        Bảng tin
                    </Link>
                </Button>
            }
        >
            <Head title={post.title} />
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)]">
                <Block className="grid gap-3 p-4 sm:p-6">
                    <PostMeta p={post} />
                    <p className="wrap-anywhere whitespace-pre-line">
                        {post.body}
                    </p>
                    {post.image_url && (
                        <img
                            src={post.image_url}
                            alt={`Ảnh của bài ${post.title}`}
                            className="max-h-[480px] w-auto max-w-full rounded-lg border object-contain"
                        />
                    )}
                    {visible && (
                        <div className="flex flex-wrap items-center gap-2 border-t pt-3">
                            <Form
                                {...student.feed.like.form(post.id)}
                                options={{ preserveScroll: true }}
                            >
                                {({ processing }) => (
                                    <>
                                        <input
                                            type="hidden"
                                            name="liked"
                                            value={post.liked ? '0' : '1'}
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                            aria-pressed={post.liked}
                                            disabled={processing}
                                        >
                                            <Heart
                                                aria-hidden
                                                className={cn(
                                                    post.liked &&
                                                        'fill-rose-500 text-rose-500',
                                                )}
                                            />
                                            Thích · {post.like_count}
                                        </Button>
                                    </>
                                )}
                            </Form>
                            {!post.is_mine && !post.reported && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setReporting((v) => !v)}
                                >
                                    <Flag aria-hidden />
                                    Báo cáo vi phạm
                                </Button>
                            )}
                            {post.reported && (
                                <span className="text-sm text-muted-foreground">
                                    Bạn đã báo cáo bài này.
                                </span>
                            )}
                            {post.can_edit && (
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() => setEditing((v) => !v)}
                                >
                                    {editing ? 'Đóng sửa' : 'Sửa bài'}
                                </Button>
                            )}
                        </div>
                    )}
                    {reporting && (
                        <Form
                            {...student.feed.report.form(post.id)}
                            options={{ preserveScroll: true }}
                            onSuccess={() => setReporting(false)}
                            className="flex flex-wrap items-start gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Input
                                        name="reason"
                                        required
                                        maxLength={500}
                                        placeholder="Lý do báo cáo"
                                        aria-label="Lý do báo cáo"
                                        className="max-w-md"
                                    />
                                    <Button
                                        type="submit"
                                        size="sm"
                                        disabled={processing}
                                    >
                                        Gửi báo cáo
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
                    {editing && (
                        <Form
                            {...student.feed.update.form(post.id)}
                            encType="multipart/form-data"
                            options={{ preserveScroll: true }}
                            onSuccess={() => setEditing(false)}
                            className="grid gap-3 border-t pt-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Input
                                        name="title"
                                        required
                                        maxLength={200}
                                        defaultValue={post.title}
                                        aria-label="Tiêu đề"
                                    />
                                    <textarea
                                        name="body"
                                        required
                                        rows={6}
                                        maxLength={10000}
                                        defaultValue={post.body}
                                        aria-label="Nội dung"
                                        className="rounded-md border bg-background px-3 py-2"
                                    />
                                    <Input
                                        type="file"
                                        name="image"
                                        accept="image/*"
                                        aria-label="Ảnh mới"
                                    />
                                    <div className="grid justify-items-start gap-1">
                                        <Button
                                            type="submit"
                                            disabled={processing}
                                        >
                                            Lưu bài
                                        </Button>
                                        {Object.values(errors)[0] && (
                                            <p className="text-sm text-destructive">
                                                {Object.values(errors)[0]}
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    )}
                </Block>
                {visible && (
                    <Block className="grid gap-3 p-4 sm:p-6">
                        <h2 className="font-semibold">
                            Bình luận ({comments.length})
                        </h2>
                        <ul className="grid gap-2">
                            {comments.map((c) => (
                                <li
                                    key={c.id}
                                    className="grid gap-1 rounded-lg border p-3 text-sm"
                                >
                                    <p className="text-xs text-muted-foreground">
                                        {c.author}
                                        {c.created_at &&
                                            ` · ${vnTime(c.created_at)}`}
                                    </p>
                                    <p className="wrap-anywhere whitespace-pre-line">
                                        {c.body}
                                    </p>
                                    {c.hidden_reason && (
                                        <p className="text-destructive">
                                            Bình luận của bạn đã bị ẩn. Lý do:{' '}
                                            {c.hidden_reason}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                        <Form
                            {...student.feed.comments.store.form(post.id)}
                            options={{ preserveScroll: true }}
                            resetOnSuccess
                            className="grid gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <textarea
                                        name="body"
                                        required
                                        rows={3}
                                        maxLength={2000}
                                        aria-label="Bình luận"
                                        placeholder="Viết bình luận"
                                        className="rounded-md border bg-background px-3 py-2 text-sm"
                                    />
                                    <div className="grid justify-items-start gap-1">
                                        <Button
                                            type="submit"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Gửi bình luận
                                        </Button>
                                        {errors.body && (
                                            <p className="text-sm text-destructive">
                                                {errors.body}
                                            </p>
                                        )}
                                    </div>
                                </>
                            )}
                        </Form>
                    </Block>
                )}
            </div>
        </Page>
    );
}
