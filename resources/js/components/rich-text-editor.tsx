import {
    Bold,
    Code2,
    Heading2,
    Italic,
    Link2,
    List,
    ListOrdered,
    Quote,
    RemoveFormatting,
    Underline,
} from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { cn } from '@/lib/utils';

type Props = {
    id?: string;
    value: string;
    onChange: (html: string) => void;
    disabled?: boolean;
    className?: string;
};

type Tool = {
    label: string;
    icon: typeof Bold;
    run: () => void;
};

/**
 * UAT 22/11: trình soạn bài đọc trực quan cho giáo viên (không cần biết HTML).
 * Dùng contentEditable và lệnh định dạng của trình duyệt; máy chủ vẫn lọc thẻ không an toàn khi lưu.
 */
export default function RichTextEditor({
    id,
    value,
    onChange,
    disabled = false,
    className,
}: Props) {
    const ref = useRef<HTMLDivElement>(null);
    const [source, setSource] = useState(false);

    // Chỉ ghi lại DOM khi giá trị đổi từ bên ngoài (đổi bài, tải lại), tránh nhảy con trỏ khi đang gõ.
    useEffect(() => {
        const el = ref.current;

        if (el && !source && el.innerHTML !== value) {
            el.innerHTML = value;
        }
    }, [value, source]);

    useEffect(() => {
        // Enter tạo đoạn <p> thay cho <div> (thẻ <div> bị lọc khi lưu).
        document.execCommand('defaultParagraphSeparator', false, 'p');
    }, []);

    const emit = () => {
        if (ref.current) {
            onChange(ref.current.innerHTML);
        }
    };

    const exec = (command: string, arg?: string) => {
        ref.current?.focus();
        document.execCommand(command, false, arg);
        emit();
    };

    const tools: Tool[] = [
        { label: 'In đậm', icon: Bold, run: () => exec('bold') },
        { label: 'In nghiêng', icon: Italic, run: () => exec('italic') },
        { label: 'Gạch chân', icon: Underline, run: () => exec('underline') },
        {
            label: 'Tiêu đề',
            icon: Heading2,
            run: () => exec('formatBlock', 'h2'),
        },
        {
            label: 'Danh sách gạch đầu dòng',
            icon: List,
            run: () => exec('insertUnorderedList'),
        },
        {
            label: 'Danh sách đánh số',
            icon: ListOrdered,
            run: () => exec('insertOrderedList'),
        },
        {
            label: 'Trích dẫn',
            icon: Quote,
            run: () => exec('formatBlock', 'blockquote'),
        },
        {
            label: 'Chèn liên kết',
            icon: Link2,
            run: () => {
                const url = window.prompt('Địa chỉ liên kết (https://...)');

                if (url && /^https?:\/\//i.test(url)) {
                    exec('createLink', url);
                }
            },
        },
        {
            label: 'Xóa định dạng',
            icon: RemoveFormatting,
            run: () => {
                exec('removeFormat');
                exec('formatBlock', 'p');
            },
        },
    ];

    return (
        <div
            className={cn(
                'overflow-hidden rounded-md border border-input bg-background',
                disabled && 'opacity-70',
                className,
            )}
        >
            <div
                role="toolbar"
                aria-label="Định dạng nội dung"
                className="flex flex-wrap items-center gap-0.5 border-b border-input bg-muted/40 p-1"
            >
                {tools.map(({ label, icon: Icon, run }) => (
                    <button
                        key={label}
                        type="button"
                        title={label}
                        aria-label={label}
                        disabled={disabled || source}
                        onMouseDown={(e) => e.preventDefault()}
                        onClick={run}
                        className="inline-flex size-8 items-center justify-center rounded text-foreground hover:bg-accent disabled:pointer-events-none disabled:opacity-40"
                    >
                        <Icon className="size-4" />
                    </button>
                ))}
                <span className="mx-1 h-5 w-px bg-border" aria-hidden />
                <button
                    type="button"
                    aria-pressed={source}
                    disabled={disabled}
                    onClick={() => setSource((s) => !s)}
                    className="inline-flex h-8 items-center gap-1 rounded px-2 text-xs font-medium hover:bg-accent disabled:opacity-40"
                >
                    <Code2 className="size-4" />
                    {source ? 'Soạn trực quan' : 'Xem mã HTML'}
                </button>
            </div>
            {source ? (
                <textarea
                    id={id}
                    aria-label="Mã HTML của nội dung"
                    className="block min-h-64 w-full resize-y bg-background p-3 font-mono text-sm outline-none"
                    value={value}
                    disabled={disabled}
                    onChange={(e) => onChange(e.target.value)}
                />
            ) : (
                <div
                    id={id}
                    ref={ref}
                    role="textbox"
                    aria-multiline="true"
                    aria-label="Nội dung bài đọc"
                    contentEditable={!disabled}
                    suppressContentEditableWarning
                    onInput={emit}
                    onBlur={emit}
                    className="prose prose-sm dark:prose-invert min-h-64 max-w-none p-3 outline-none [&_blockquote]:border-l-4 [&_blockquote]:pl-3 [&_h2]:text-lg [&_h2]:font-semibold [&_ol]:list-decimal [&_ol]:pl-6 [&_ul]:list-disc [&_ul]:pl-6"
                />
            )}
        </div>
    );
}
