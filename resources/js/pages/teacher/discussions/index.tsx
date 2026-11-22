import { Form, Head, Link } from '@inertiajs/react';
import { BookOpen, List, MessageCircleQuestion } from 'lucide-react';
import { DiscussionThread } from '@/components/learning/discussion-thread';
import type { DiscussionRow } from '@/components/learning/discussion-thread';
import { Block, Page, textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import discussions from '@/routes/teacher/discussions';

type Row = DiscussionRow & {
    course: string | null;
    lesson: string | null;
    reply_url: string;
};

/** MH-GV-12: hàng chờ hỏi đáp — câu chưa trả lời, chờ lâu nhất trước. */
export default function TeacherDiscussions({
    tab,
    waiting_count,
    discussions: rows,
}: {
    tab: 'waiting' | 'answered';
    waiting_count: number;
    discussions: Row[];
}) {
    return (
        <Page
            title="Hỏi đáp"
            description="Câu hỏi của học viên trong các khóa bạn đang được giao."
            stats={[
                {
                    label: 'Chờ trả lời',
                    value: waiting_count,
                    icon: MessageCircleQuestion,
                    attention: waiting_count > 0,
                },
                { label: 'Trong danh sách', value: rows.length, icon: List },
                {
                    label: 'Khóa có câu hỏi',
                    value: new Set(rows.map((r) => r.course)).size,
                    icon: BookOpen,
                },
            ]}
        >
            <Head title="Hỏi đáp" />
            <nav className="flex gap-2" aria-label="Lọc câu hỏi">
                {(
                    [
                        ['waiting', `Chờ trả lời (${waiting_count})`],
                        ['answered', 'Đã trả lời'],
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
                        aria-current={tab === key ? 'page' : undefined}
                    >
                        {label}
                    </Link>
                ))}
            </nav>
            <div className="grid gap-4">
                {rows.map((d) => (
                    <Block key={d.id} className="grid gap-3">
                        <p className="text-xs text-muted-foreground">
                            {d.course} · {d.lesson}
                        </p>
                        <DiscussionThread d={d}>
                            <Form
                                action={d.reply_url}
                                method="post"
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="grid gap-2"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <label
                                            htmlFor={`reply-${d.id}`}
                                            className="sr-only"
                                        >
                                            Trả lời
                                        </label>
                                        <textarea
                                            id={`reply-${d.id}`}
                                            name="body"
                                            required
                                            maxLength={2000}
                                            className={cn(
                                                textareaClass,
                                                'min-h-20',
                                            )}
                                            placeholder="Viết câu trả lời…"
                                        />
                                        {errors.body && (
                                            <p className="text-sm text-destructive">
                                                {errors.body}
                                            </p>
                                        )}
                                        <Button
                                            size="sm"
                                            disabled={processing}
                                            className="justify-self-start"
                                        >
                                            Gửi trả lời
                                        </Button>
                                    </>
                                )}
                            </Form>
                        </DiscussionThread>
                    </Block>
                ))}
                {rows.length === 0 && (
                    <Block className="text-center text-muted-foreground">
                        Không có câu hỏi nào ở mục này.
                    </Block>
                )}
            </div>
        </Page>
    );
}
