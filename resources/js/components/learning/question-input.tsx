import { ArrowDown, ArrowUp } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AnswerValue, Question } from '@/types/learning';

/**
 * Ô trả lời một câu (M04 + loại N): một/nhiều đáp án, đúng/sai, điền từ (chuỗi) và sắp xếp (thứ tự id).
 * Dùng chung cho quiz bài học và phòng thi thử.
 */
export function QuestionInput({
    question,
    value,
    onChange,
    disabled,
}: {
    question: Question;
    value: AnswerValue;
    onChange: (value: AnswerValue) => void;
    disabled?: boolean;
}) {
    if (question.type === 'DIEN_TU') {
        return (
            <div className="grid gap-1.5">
                <label
                    htmlFor={`fill-${question.id}`}
                    className="text-xs text-muted-foreground"
                >
                    Điền câu trả lời (không phân biệt hoa/thường, giữ dấu)
                </label>
                <Input
                    id={`fill-${question.id}`}
                    value={typeof value === 'string' ? value : ''}
                    maxLength={500}
                    disabled={disabled}
                    autoComplete="off"
                    onChange={({ target }) =>
                        onChange(target.value === '' ? null : target.value)
                    }
                />
            </div>
        );
    }

    if (question.type === 'SAP_XEP') {
        const order =
            Array.isArray(value) && value.length === question.choices.length
                ? value
                : question.choices.map((c) => c.id);
        const byId = new Map(question.choices.map((c) => [c.id, c.content]));
        const move = (index: number, step: number) => {
            const next = [...order];
            const [item] = next.splice(index, 1);
            next.splice(index + step, 0, item);
            onChange(next);
        };

        return (
            <div className="grid gap-2">
                <p className="text-xs text-muted-foreground">
                    Kéo thả hoặc dùng mũi tên để sắp xếp từ trên xuống theo thứ
                    tự đúng.
                </p>
                <ol className="grid gap-2">
                    {order.map((id, index) => (
                        <li
                            key={id}
                            draggable={!disabled}
                            onDragStart={(e) =>
                                e.dataTransfer.setData(
                                    'text/plain',
                                    String(index),
                                )
                            }
                            onDragOver={(e) => e.preventDefault()}
                            onDrop={(e) => {
                                e.preventDefault();
                                const from = Number(
                                    e.dataTransfer.getData('text/plain'),
                                );
                                if (!Number.isInteger(from) || from === index)
                                    return;
                                move(from, index - from);
                            }}
                            className="flex min-h-12 cursor-grab items-center gap-3 rounded-xl border border-border p-2.5 pl-3.5 text-sm font-medium active:cursor-grabbing"
                        >
                            <span className="w-5 shrink-0 text-muted-foreground tabular-nums">
                                {index + 1}.
                            </span>
                            <span className="min-w-0 flex-1 wrap-anywhere">
                                {byId.get(id)}
                            </span>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={`Đưa "${byId.get(id)}" lên`}
                                disabled={disabled || index === 0}
                                onClick={() => move(index, -1)}
                            >
                                <ArrowUp className="size-4" />
                            </Button>
                            <Button
                                type="button"
                                size="icon"
                                variant="ghost"
                                aria-label={`Đưa "${byId.get(id)}" xuống`}
                                disabled={
                                    disabled || index === order.length - 1
                                }
                                onClick={() => move(index, 1)}
                            >
                                <ArrowDown className="size-4" />
                            </Button>
                        </li>
                    ))}
                </ol>
                {!Array.isArray(value) && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="justify-self-start"
                        disabled={disabled}
                        onClick={() => onChange(order)}
                    >
                        Chọn thứ tự đang hiển thị
                    </Button>
                )}
            </div>
        );
    }

    const multiple = question.type === 'NHIEU_DAP_AN';
    const selected = Array.isArray(value) ? value : [];

    return (
        <div className="grid gap-2">
            <p className="text-xs text-muted-foreground">
                {multiple ? 'Chọn các đáp án' : 'Chọn một đáp án'}
            </p>
            {question.choices.map((choice) => (
                <label
                    key={choice.id}
                    className="flex min-h-12 cursor-pointer items-start gap-3 rounded-xl border border-border p-3.5 text-sm font-medium transition-colors hover:bg-accent/60 has-[:checked]:border-sky-500 has-[:checked]:bg-sky-50 has-[:checked]:ring-1 has-[:checked]:ring-sky-500 dark:has-[:checked]:bg-sky-400/10"
                >
                    <input
                        type={multiple ? 'checkbox' : 'radio'}
                        name={`question-${question.id}`}
                        checked={selected.includes(choice.id)}
                        disabled={disabled}
                        className="mt-0.5 size-4 shrink-0 accent-sky-600"
                        onChange={({ target }) => {
                            const values = !multiple
                                ? [choice.id]
                                : target.checked
                                  ? [...selected, choice.id]
                                  : selected.filter((id) => id !== choice.id);
                            onChange(
                                values.length
                                    ? values.toSorted((a, b) => a - b)
                                    : null,
                            );
                        }}
                    />
                    <span className="min-w-0 wrap-anywhere whitespace-pre-wrap">
                        {choice.content}
                    </span>
                </label>
            ))}
        </div>
    );
}

/** Câu đã có trả lời (dùng cho nhãn "Đã lưu"). */
export const hasAnswer = (value: AnswerValue) =>
    typeof value === 'string' ? value !== '' : (value?.length ?? 0) > 0;

/** Hiển thị câu trả lời đã nộp ở trang kết quả. */
export function answerText(question: Question): string {
    const value = question.answer;
    if (value === null || value === undefined) return 'Bỏ trống';
    if (typeof value === 'string') return value;
    const byId = new Map(question.choices.map((c) => [c.id, c.content]));

    return value
        .map((id) => byId.get(id) ?? '?')
        .join(question.type === 'SAP_XEP' ? ' → ' : ', ');
}
