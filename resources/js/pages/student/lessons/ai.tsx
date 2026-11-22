import { Form, Head, Link } from '@inertiajs/react';
import { ArrowLeft } from 'lucide-react';
import { AiChat } from '@/components/ai-chat';
import type { AiChatMessage } from '@/components/ai-chat';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';

type Props = {
    course: { id: number; title: string };
    lesson: { id: number; title: string };
    enabled: boolean;
    can_ask: boolean;
    remaining: number;
    limit: number;
    messages: AiChatMessage[];
    conversation_id: number | null;
    handed_off: boolean;
    ask_url: string;
    hand_off_url: string | null;
    lesson_url: string;
};

/** MH-HV AI trong bài (QT-27b): hỏi theo nội dung bài, lịch sử của riêng mình, chuyển câu hỏi cho giáo viên. */
export default function LessonAi({
    course,
    lesson,
    enabled,
    can_ask,
    remaining,
    limit,
    messages,
    handed_off,
    ask_url,
    hand_off_url,
    lesson_url,
}: Props) {
    const disabledReason = !enabled
        ? 'Trợ lý AI đang tắt.'
        : !can_ask
          ? 'Quyền học của khóa không còn hiệu lực nên không hỏi AI được. Bạn vẫn xem được lịch sử.'
          : remaining <= 0
            ? `Bạn đã dùng hết ${limit} lượt hỏi AI hôm nay.`
            : null;
    const lastFound = [...messages].reverse().find((m) => m.role === 'AI');

    return (
        <Page
            title="Hỏi AI về bài học"
            eyebrow={`${course.title} · ${lesson.title}`}
            actions={
                <Button variant="outline" asChild>
                    <Link href={lesson_url}>
                        <ArrowLeft aria-hidden />
                        Về bài học
                    </Link>
                </Button>
            }
        >
            <Head title={`Hỏi AI · ${lesson.title}`} />
            <div className="grid gap-5 lg:grid-cols-[minmax(0,2fr)_minmax(0,1fr)]">
                <Block className="grid content-start gap-3 p-4 sm:p-6">
                    <AiChat
                        messages={messages}
                        action={ask_url}
                        disabledReason={disabledReason}
                        remaining={remaining}
                        limit={limit}
                        placeholder="Ví dụ: Phản ứng oxi hóa khử là gì?"
                        empty="AI trả lời dựa trên nội dung của bài này. Câu hỏi ngoài bài sẽ được báo là không có căn cứ."
                    />
                </Block>
                <Block className="grid content-start gap-3 p-4 sm:p-6">
                    <h2 className="font-semibold">Cần giáo viên trả lời?</h2>
                    <p className="text-sm text-muted-foreground">
                        Chuyển câu hỏi gần nhất của bạn thành câu hỏi trong mục
                        Hỏi đáp của bài. Mỗi hội thoại chuyển một lần.
                    </p>
                    {handed_off ? (
                        <p className="text-sm font-medium">
                            Đã chuyển cho giáo viên. Xem trả lời ở mục Hỏi đáp
                            của bài.
                        </p>
                    ) : hand_off_url && can_ask ? (
                        <Form
                            action={hand_off_url}
                            method="post"
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <div className="grid justify-items-start gap-1">
                                    <Button
                                        type="submit"
                                        variant={
                                            lastFound?.found === false
                                                ? 'default'
                                                : 'outline'
                                        }
                                        disabled={processing}
                                    >
                                        Chuyển cho giáo viên
                                    </Button>
                                    {Object.values(errors)[0] && (
                                        <p className="text-sm text-destructive">
                                            {Object.values(errors)[0]}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Hỏi AI trước rồi mới chuyển được.
                        </p>
                    )}
                </Block>
            </div>
        </Page>
    );
}
