import { Form, Link } from '@inertiajs/react';
import { useState } from 'react';
import {
    clock,
    DiscussionThread,
} from '@/components/learning/discussion-thread';
import type { DiscussionRow } from '@/components/learning/discussion-thread';
import { textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type Community = {
    can_write: boolean;
    ask_url: string;
    note_url: string;
    discussions: DiscussionRow[];
    notes: {
        id: number;
        body: string;
        second: number | null;
        update_url: string;
        delete_url: string;
    }[];
};

/** Video đang phát trong trình học (để gắn mốc giây và tua). */
function lessonVideo() {
    return document.querySelector<HTMLVideoElement>(
        'section[aria-label="Nội dung bài"] video',
    );
}

export function seekVideo(second: number) {
    const video = lessonVideo();
    if (!video) return;
    video.currentTime = second;
    video.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function SecondToggle({ isVideo }: { isVideo: boolean }) {
    const [second, setSecond] = useState<number | null>(null);
    if (!isVideo) return null;

    return (
        <label className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                className="size-4 accent-sky-600"
                checked={second !== null}
                onChange={(e) =>
                    setSecond(
                        e.target.checked
                            ? Math.floor(lessonVideo()?.currentTime ?? 0)
                            : null,
                    )
                }
            />
            Gắn mốc video{' '}
            {second !== null && (
                <span className="tabular-nums">({clock(second)})</span>
            )}
            {second !== null && (
                <input type="hidden" name="second" value={second} />
            )}
        </label>
    );
}

/** MH-HV-03 tab "Hỏi đáp": hỏi kèm mốc giây, "Cũng thắc mắc", chấp nhận câu trả lời (QT-18). */
export function QnaPanel({
    community,
    isVideo,
}: {
    community: Community;
    isVideo: boolean;
}) {
    return (
        <div className="grid max-w-3xl gap-5">
            {community.can_write ? (
                <Form
                    action={community.ask_url}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <label
                                htmlFor="ask-body"
                                className="text-sm font-medium"
                            >
                                Đặt câu hỏi cho giáo viên
                            </label>
                            <textarea
                                id="ask-body"
                                name="body"
                                required
                                maxLength={2000}
                                className={cn(textareaClass, 'min-h-20')}
                            />
                            <SecondToggle isVideo={isVideo} />
                            {(errors.body || errors.video_second) && (
                                <p className="text-sm text-destructive">
                                    {errors.body ?? errors.video_second}
                                </p>
                            )}
                            <Button
                                size="sm"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Gửi câu hỏi
                            </Button>
                        </>
                    )}
                </Form>
            ) : (
                <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                    Quyền học đã hết hạn: bạn vẫn đọc được hỏi đáp nhưng không
                    đặt câu hỏi mới.
                </p>
            )}
            <ul className="grid gap-5">
                {community.discussions.map((d) => (
                    <li
                        key={d.id}
                        className="border-t border-border pt-4 first:border-0 first:pt-0"
                    >
                        <DiscussionThread
                            d={d}
                            onSeek={isVideo ? seekVideo : undefined}
                        />
                    </li>
                ))}
                {community.discussions.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Chưa có câu hỏi nào ở bài này.
                    </li>
                )}
            </ul>
        </div>
    );
}

/** MH-HV-03 tab "Ghi chú": ghi chú riêng theo giây, bấm mốc để tua. */
export function NotesPanel({
    community,
    isVideo,
}: {
    community: Community;
    isVideo: boolean;
}) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <div className="grid max-w-3xl gap-5">
            {community.can_write && (
                <Form
                    action={community.note_url}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <label
                                htmlFor="note-body"
                                className="text-sm font-medium"
                            >
                                Ghi chú mới (chỉ bạn thấy)
                            </label>
                            <textarea
                                id="note-body"
                                name="body"
                                required
                                maxLength={2000}
                                className={cn(textareaClass, 'min-h-16')}
                            />
                            <SecondToggle isVideo={isVideo} />
                            {(errors.body || errors.video_second) && (
                                <p className="text-sm text-destructive">
                                    {errors.body ?? errors.video_second}
                                </p>
                            )}
                            <Button
                                size="sm"
                                disabled={processing}
                                className="justify-self-start"
                            >
                                Lưu ghi chú
                            </Button>
                        </>
                    )}
                </Form>
            )}
            <ul className="grid gap-3">
                {community.notes.map((n) => (
                    <li
                        key={n.id}
                        className="grid gap-2 rounded-xl border border-border p-3"
                    >
                        {editing === n.id ? (
                            <Form
                                action={n.update_url}
                                method="patch"
                                options={{ preserveScroll: true }}
                                onSuccess={() => setEditing(null)}
                                className="grid gap-2"
                            >
                                {({ processing }) => (
                                    <>
                                        <label
                                            htmlFor={`note-${n.id}`}
                                            className="sr-only"
                                        >
                                            Sửa ghi chú
                                        </label>
                                        <textarea
                                            id={`note-${n.id}`}
                                            name="body"
                                            required
                                            maxLength={2000}
                                            defaultValue={n.body}
                                            className={cn(
                                                textareaClass,
                                                'min-h-16',
                                            )}
                                        />
                                        <div className="flex gap-2">
                                            <Button
                                                size="sm"
                                                disabled={processing}
                                            >
                                                Lưu
                                            </Button>
                                            <Button
                                                type="button"
                                                size="sm"
                                                variant="ghost"
                                                onClick={() => setEditing(null)}
                                            >
                                                Hủy
                                            </Button>
                                        </div>
                                    </>
                                )}
                            </Form>
                        ) : (
                            <>
                                <div className="flex items-start gap-2">
                                    {n.second !== null && (
                                        <button
                                            type="button"
                                            onClick={() =>
                                                seekVideo(n.second ?? 0)
                                            }
                                            className="shrink-0 rounded-full bg-sky-100 px-2 py-0.5 text-xs font-semibold text-sky-900 tabular-nums dark:bg-sky-400/15 dark:text-sky-200"
                                        >
                                            {clock(n.second)}
                                        </button>
                                    )}
                                    <p className="min-w-0 flex-1 wrap-anywhere whitespace-pre-wrap">
                                        {n.body}
                                    </p>
                                </div>
                                <div className="flex gap-3 text-xs">
                                    <button
                                        type="button"
                                        className="text-muted-foreground hover:text-foreground"
                                        onClick={() => setEditing(n.id)}
                                    >
                                        Sửa
                                    </button>
                                    <Link
                                        href={n.delete_url}
                                        method="delete"
                                        as="button"
                                        preserveScroll
                                        onBefore={() =>
                                            window.confirm('Xóa ghi chú này?')
                                        }
                                        className="text-muted-foreground hover:text-destructive"
                                    >
                                        Xóa
                                    </Link>
                                </div>
                            </>
                        )}
                    </li>
                ))}
                {community.notes.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Chưa có ghi chú.
                    </li>
                )}
            </ul>
        </div>
    );
}
