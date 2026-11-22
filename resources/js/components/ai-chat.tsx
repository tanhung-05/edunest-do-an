import { Form, Link } from '@inertiajs/react';
import { Bot, SendHorizontal } from 'lucide-react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type AiChatMessage = {
    id: number;
    role: 'USER' | 'AI';
    content: string;
    status: 'OK' | 'LOI';
    found?: boolean | null;
    courses?: { title: string; url: string }[];
};

function newKey(): string {
    if (typeof crypto !== 'undefined' && 'randomUUID' in crypto) {
        return crypto.randomUUID();
    }

    return `${Date.now()}-${Math.random().toString(36).slice(2, 12)}`;
}

/**
 * M25: khung hội thoại AI dùng chung cho hỏi trong bài và tư vấn khóa. Mỗi lần gửi mang một khóa yêu cầu;
 * gửi lại khi lỗi mạng dùng lại khóa cũ nên không tính thêm lượt (CR-170). Gửi thành công mới đổi khóa.
 */
export function AiChat({
    messages,
    action,
    disabledReason,
    remaining,
    limit,
    placeholder,
    empty,
}: {
    messages: AiChatMessage[];
    action: string;
    disabledReason: string | null;
    remaining: number;
    limit: number;
    placeholder: string;
    empty: string;
}) {
    const [requestKey, setRequestKey] = useState(newKey);

    return (
        <div className="grid gap-4">
            <ol
                className="grid max-h-[60vh] gap-3 overflow-y-auto rounded-xl border bg-muted/30 p-3 sm:p-4"
                aria-label="Lịch sử hội thoại"
                aria-live="polite"
            >
                {messages.length === 0 && (
                    <li className="text-sm text-muted-foreground">{empty}</li>
                )}
                {messages.map((m) => (
                    <li
                        key={m.id}
                        className={cn(
                            'grid max-w-[85%] gap-2 rounded-2xl px-4 py-3 text-sm leading-relaxed',
                            m.role === 'USER'
                                ? 'justify-self-end bg-slate-950 text-white'
                                : 'justify-self-start border bg-background',
                            m.status === 'LOI' &&
                                'border-destructive/40 text-destructive',
                        )}
                    >
                        {m.role === 'AI' && (
                            <span className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground">
                                <Bot className="size-3.5" aria-hidden />
                                Trợ lý AI
                            </span>
                        )}
                        <p className="wrap-anywhere whitespace-pre-line">
                            {m.content}
                        </p>
                        {m.courses && m.courses.length > 0 && (
                            <ul className="grid gap-1">
                                {m.courses.map((c) => (
                                    <li key={c.url}>
                                        <Link
                                            href={c.url}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            {c.title}
                                        </Link>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </li>
                ))}
            </ol>
            {disabledReason ? (
                <p className="rounded-lg border bg-muted/40 p-3 text-sm text-muted-foreground">
                    {disabledReason}
                </p>
            ) : (
                <Form
                    action={action}
                    method="post"
                    options={{ preserveScroll: true }}
                    resetOnSuccess={['question']}
                    onSuccess={() => setRequestKey(newKey())}
                    className="grid gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <input
                                type="hidden"
                                name="request_key"
                                value={requestKey}
                            />
                            <div className="flex items-end gap-2">
                                <textarea
                                    name="question"
                                    required
                                    maxLength={1000}
                                    rows={2}
                                    aria-label="Câu hỏi cho AI"
                                    placeholder={placeholder}
                                    className="min-h-11 flex-1 resize-y rounded-lg border bg-background px-3 py-2 text-sm"
                                />
                                <Button
                                    type="submit"
                                    size="icon"
                                    disabled={processing || remaining <= 0}
                                    aria-label="Gửi câu hỏi"
                                >
                                    <SendHorizontal aria-hidden />
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Còn {remaining}/{limit} lượt hỏi hôm nay. AI chỉ
                                trả lời dựa trên nội dung có sẵn, có thể chưa
                                chính xác.
                            </p>
                            {(errors.question || errors.request_key) && (
                                <p className="text-sm text-destructive">
                                    {errors.question ?? errors.request_key}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}
