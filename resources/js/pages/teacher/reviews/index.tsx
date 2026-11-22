import { Form, Head } from '@inertiajs/react';
import { MessageSquare, Star, ThumbsUp } from 'lucide-react';
import { useState } from 'react';
import { Stars, vnTime } from '@/components/commerce';
import { Block, Page, textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import reviews from '@/routes/teacher/reviews';

type Rating = {
    id: number;
    student: string;
    course: string;
    stars: number;
    comment: string | null;
    reply: string | null;
    created_at: string | null;
};

export default function TeacherReviews({ ratings }: { ratings: Rating[] }) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <Page
            title="Đánh giá khóa học"
            description="Đánh giá đang hiển thị của các khóa bạn được giao. Mỗi đánh giá có một câu trả lời, sửa được."
            stats={[
                { label: 'Đánh giá', value: ratings.length, icon: Star },
                {
                    label: 'Điểm trung bình',
                    value: ratings.length
                        ? (
                              ratings.reduce((n, r) => n + r.stars, 0) /
                              ratings.length
                          ).toFixed(1)
                        : '—',
                    icon: ThumbsUp,
                },
                {
                    label: 'Chưa trả lời',
                    value: ratings.filter((r) => !r.reply).length,
                    icon: MessageSquare,
                    attention: ratings.some((r) => !r.reply),
                },
            ]}
        >
            <Head title="Đánh giá khóa học" />
            <Block>
                <ul className="divide-y divide-border">
                    {ratings.map((r) => (
                        <li
                            key={r.id}
                            className="space-y-2 py-4 first:pt-0 last:pb-0"
                        >
                            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                                <Stars value={r.stars} />
                                <span className="font-medium">{r.student}</span>
                                <span className="text-sm text-muted-foreground">
                                    {r.course} · {vnTime(r.created_at, false)}
                                </span>
                            </div>
                            {r.comment && (
                                <p className="wrap-anywhere">{r.comment}</p>
                            )}
                            {r.reply && editing !== r.id && (
                                <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                                    <span className="font-medium">
                                        Bạn đã trả lời:{' '}
                                    </span>
                                    {r.reply}
                                </p>
                            )}
                            {editing === r.id ? (
                                <Form
                                    {...reviews.reply.form(r.id)}
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setEditing(null)}
                                    className="grid gap-2"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <label
                                                htmlFor={`reply-${r.id}`}
                                                className="sr-only"
                                            >
                                                Trả lời
                                            </label>
                                            <textarea
                                                id={`reply-${r.id}`}
                                                name="reply"
                                                required
                                                maxLength={2000}
                                                defaultValue={r.reply ?? ''}
                                                className={textareaClass}
                                            />
                                            {errors.reply && (
                                                <p className="text-sm text-destructive">
                                                    {errors.reply}
                                                </p>
                                            )}
                                            <div className="flex gap-2">
                                                <Button
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Gửi trả lời
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setEditing(null)
                                                    }
                                                >
                                                    Hủy
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEditing(r.id)}
                                >
                                    {r.reply ? 'Sửa trả lời' : 'Trả lời'}
                                </Button>
                            )}
                        </li>
                    ))}
                    {ratings.length === 0 && (
                        <li className="text-muted-foreground">
                            Chưa có đánh giá nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
