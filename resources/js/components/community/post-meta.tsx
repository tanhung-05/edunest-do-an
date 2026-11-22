import { vnTime } from '@/components/commerce';

export type PostCard = {
    id: number;
    title: string;
    topic: string;
    course: string | null;
    author: string | null;
    created_at: string | null;
    edited: boolean;
    like_count: number;
    is_mine: boolean;
    hidden_reason: string | null;
    image_url: string | null;
};

/** Dòng thông tin bài cộng đồng: chủ đề, thẻ khóa, tác giả, giờ; tác giả thấy lý do khi bài bị ẩn (E47). */
export function PostMeta({ p }: { p: PostCard }) {
    return (
        <div className="grid gap-1">
            <p className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-muted-foreground">
                <span className="rounded-full bg-muted px-2 py-0.5 font-medium text-foreground">
                    {p.topic}
                </span>
                {p.course && <span>#{p.course}</span>}
                <span>
                    {p.author}
                    {p.is_mine && ' (bạn)'}
                </span>
                {p.created_at && <span>{vnTime(p.created_at)}</span>}
                {p.edited && <span>đã sửa</span>}
            </p>
            {p.hidden_reason && (
                <p className="rounded-md border border-destructive/40 bg-destructive/5 px-3 py-2 text-sm text-destructive">
                    Bài của bạn đã bị ẩn. Lý do: {p.hidden_reason}
                </p>
            )}
        </div>
    );
}
