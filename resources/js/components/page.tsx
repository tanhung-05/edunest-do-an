import { usePage } from '@inertiajs/react';
import type { LucideIcon } from 'lucide-react';
import { cn } from '@/lib/utils';

export type PageStat = {
    label: string;
    value: React.ReactNode;
    icon?: LucideIcon;
    /** Nhấn hổ phách cho số cần chú ý (sắp hết hạn, chờ xử lý). */
    attention?: boolean;
};

/** Ảnh thật theo khu vực (public/images/stock, Unsplash License). Khớp tiền tố dài nhất. */
const HERO_PHOTOS: [RegExp, string][] = [
    [/^\/student\/courses/, 'hoc-laptop'],
    [/^\/student\/(achievements|leaderboard)/, 'doc-sach-ngoai-troi'],
    [/^\/student\/(orders|cart|wishlist|activation)/, 'anh-sach-2'],
    [/^\/student\/(practice|quizzes|attempts)/, 'toan-bang'],
    [/^\/student\/(flashcards|games)/, 'viet-sach'],
    [/^\/(student\/)?community/, 'nhom-macbook'],
    [/^\/student\/live/, 'hero-lop'],
    [/^\/student\/(certificates|guardians|affiliate)/, 'thu-vien'],
    [/^\/student/, 'hoc-dem'],
    [/^\/exams/, 'hero-giang-duong'],
    [/^\/teacher\/(question-banks|practice|flashcard)/, 'viet-sach'],
    [/^\/teacher\/live/, 'hero-lop'],
    [/^\/teacher/, 'hero-giang-duong'],
    [
        /^\/academic\/(orders|bank-reviews|promotions|coupons|activation|bundles|affiliates)/,
        'anh-sach',
    ],
    [/^\/academic\/(community|moderation|discussions|support)/, 'nhom-macbook'],
    [/^\/academic\/(reports|students|enrollments)/, 'thu-vien'],
    [/^\/academic/, 'hero-lop'],
    [/^\/admin/, 'dia-qua-cau'],
    [/^\/guardian/, 'doc-sach-ngoai-troi'],
    [/^\/(account|notifications)/, 'su-trang-sach'],
];

// Màn làm bài, phiên luyện tập, ôn thẻ, thanh toán: giữ tiêu đề gọn để dồn chỗ cho việc chính.
const COMPACT_ON =
    /\/(attempts|sessions?|review|play|checkout|room|lessons)(\/|$)/;

function heroPhoto(path: string): string {
    const hit = HERO_PHOTOS.find(([re]) => re.test(path));

    return `/images/stock/${hit ? hit[1] : 'hoc-dem'}.jpg`;
}

/**
 * Khung nội dung trang trong vùng ứng dụng. P10: đầu trang là dải ảnh thật làm tối (theo khu vực) mang tiêu đề, mô tả,
 * nút chính và các số tóm tắt (`stats`), để trang có chiều sâu mà không tốn chỗ. Màn làm bài, luyện tập, ôn thẻ
 * giữ tiêu đề gọn (`compact` hoặc tự nhận theo đường dẫn).
 */
export function Page({
    title,
    description,
    actions,
    children,
    className,
    eyebrow,
    stats,
    compact,
}: {
    title: string;
    description?: string;
    actions?: React.ReactNode;
    children?: React.ReactNode;
    className?: string;
    eyebrow?: React.ReactNode;
    stats?: PageStat[];
    compact?: boolean;
    /** Giữ tương thích chỗ gọi cũ. */
    art?: boolean;
}) {
    const path = usePage().url.split('?')[0];
    const plain = compact ?? COMPACT_ON.test(path);

    return (
        <div
            className={cn(
                'mx-auto flex w-full max-w-[1400px] flex-col gap-5 px-4 py-5 md:gap-6 md:px-8 md:py-7',
                className,
            )}
        >
            {plain ? (
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div className="min-w-0 space-y-1.5">
                        {eyebrow && (
                            <div className="text-sm text-muted-foreground">
                                {eyebrow}
                            </div>
                        )}
                        <h1 className="text-2xl font-bold tracking-tight wrap-anywhere md:text-[1.875rem] md:leading-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="max-w-[70ch] text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {actions && (
                        <div className="flex flex-wrap items-center gap-2">
                            {actions}
                        </div>
                    )}
                </header>
            ) : (
                <header className="relative isolate overflow-hidden rounded-3xl bg-slate-950 text-white shadow-[0_18px_40px_-24px_rgb(15_23_42/0.6)]">
                    <img
                        src={heroPhoto(path)}
                        alt=""
                        aria-hidden
                        decoding="async"
                        className="absolute inset-0 -z-10 h-full w-full object-cover opacity-70"
                    />
                    <div
                        aria-hidden
                        className="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/95 via-slate-950/75 to-slate-950/15"
                    />
                    <div className="flex flex-wrap items-end justify-between gap-x-6 gap-y-4 px-5 pt-6 pb-5 md:px-8 md:pt-8 md:pb-6">
                        <div className="min-w-0 space-y-2">
                            {eyebrow && (
                                <div className="text-sm text-white/75 [&_a]:text-white [&_a]:underline-offset-4 [&_a:hover]:underline">
                                    {eyebrow}
                                </div>
                            )}
                            <h1 className="text-2xl font-bold tracking-tight text-balance wrap-anywhere md:text-[2rem] md:leading-tight">
                                {title}
                            </h1>
                            {description && (
                                <p className="max-w-[68ch] text-sm leading-relaxed text-white/75">
                                    {description}
                                </p>
                            )}
                        </div>
                        {actions && (
                            <div className="flex flex-wrap items-center gap-2 [&_a[data-slot=button]]:shadow-md [&_button[data-slot=button]]:shadow-md">
                                {actions}
                            </div>
                        )}
                    </div>
                    {stats && stats.length > 0 && (
                        <dl className="grid grid-cols-2 border-t border-white/10 bg-slate-950/65 backdrop-blur-md sm:grid-cols-[repeat(auto-fit,minmax(9rem,1fr))]">
                            {stats.map((s) => (
                                <div
                                    key={s.label}
                                    className="flex items-center gap-3 border-white/10 px-5 py-3.5 not-last:border-r md:px-6"
                                >
                                    {s.icon && (
                                        <s.icon
                                            aria-hidden
                                            className={cn(
                                                'size-5 shrink-0',
                                                s.attention
                                                    ? 'text-amber-300'
                                                    : 'text-white/60',
                                            )}
                                        />
                                    )}
                                    <div className="min-w-0">
                                        <dt className="truncate text-xs text-white/65">
                                            {s.label}
                                        </dt>
                                        <dd
                                            className={cn(
                                                'text-lg leading-tight font-bold tabular-nums',
                                                s.attention && 'text-amber-300',
                                            )}
                                        >
                                            {s.value}
                                        </dd>
                                    </div>
                                </div>
                            ))}
                        </dl>
                    )}
                </header>
            )}
            <FlashStatus />
            {children}
        </div>
    );
}

/** Mã trạng thái Fortify → câu tiếng Việt. */
const fortifyStatus: Record<string, string> = {
    'two-factor-authentication-enabled':
        'Đã tạo khóa 2FA. Nhập mã 6 số để hoàn tất.',
    'two-factor-authentication-confirmed': 'Đã bật xác thực hai lớp.',
    'recovery-codes-generated': 'Đã tạo mã khôi phục mới.',
    'verification-link-sent': 'Đã gửi liên kết xác minh tới email của bạn.',
};

export function FlashStatus({ message }: { message?: string | null }) {
    const { flash } = usePage().props;
    const raw = message ?? flash?.status;
    const text = raw ? (fortifyStatus[raw] ?? raw) : null;

    if (!text) {
        return null;
    }

    return (
        <div
            role="status"
            aria-live="polite"
            className="flex items-center gap-3 rounded-xl border border-amber/40 bg-amber-soft/60 px-4 py-3 text-sm font-medium text-navy dark:text-amber-soft"
        >
            {text}
        </div>
    );
}

/** Khối nội dung v5: thẻ trắng có viền mảnh (mặc định), nền tối hoặc nhấn hổ phách nhạt. */
export function Block({
    tone = 'plain',
    className,
    children,
}: {
    tone?: 'plain' | 'surface' | 'accent';
    className?: string;
    children: React.ReactNode;
}) {
    return (
        <section
            className={cn(
                'rounded-2xl p-5 md:p-6',
                tone === 'plain' && 'border border-border bg-card shadow-soft',
                tone === 'surface' && 'bg-surface text-surface-ink',
                tone === 'accent' &&
                    'border border-amber/40 bg-amber-soft/60 text-navy dark:text-amber-soft',
                className,
            )}
        >
            {children}
        </section>
    );
}

export function Stat({
    label,
    value,
    tone = 'plain',
    icon: Icon,
    hint,
}: {
    label: string;
    value: number | string;
    tone?: 'plain' | 'surface' | 'accent';
    icon?: LucideIcon;
    hint?: string;
}) {
    return (
        <Block tone={tone} className="flex items-start gap-4 p-4 md:p-5">
            {Icon && (
                <span
                    aria-hidden
                    className={cn(
                        'grid size-11 shrink-0 place-items-center rounded-xl',
                        tone === 'accent'
                            ? 'bg-amber/25 text-navy dark:text-amber-soft'
                            : 'bg-muted text-foreground/70',
                    )}
                >
                    <Icon className="size-5" />
                </span>
            )}
            <div className="min-w-0">
                <div className="text-sm text-muted-foreground">{label}</div>
                <div className="mt-0.5 font-display text-3xl font-bold tracking-tight tabular-nums">
                    {value}
                </div>
                {hint && (
                    <div className="mt-0.5 text-xs text-muted-foreground">
                        {hint}
                    </div>
                )}
            </div>
        </Block>
    );
}

export function StatusText({
    status,
    label,
}: {
    status: string;
    label: string;
}) {
    return (
        <span
            className={cn(
                'text-sm font-medium',
                status === 'BI_KHOA' ? 'text-destructive' : 'text-success',
            )}
        >
            {label}
        </span>
    );
}

export function Field({
    label,
    htmlFor,
    error,
    hint,
    children,
}: {
    label: string;
    htmlFor: string;
    error?: string;
    hint?: string;
    children: React.ReactNode;
}) {
    return (
        <div className="grid gap-2">
            <label htmlFor={htmlFor} className="text-sm font-medium">
                {label}
            </label>
            {children}
            {hint && !error && (
                <p className="text-xs text-muted-foreground">{hint}</p>
            )}
            {error && (
                <p className="text-sm text-destructive" role="alert">
                    {error}
                </p>
            )}
        </div>
    );
}

export const textareaClass =
    'min-h-28 w-full rounded-lg border border-input bg-card px-3 py-2 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';

export const selectClass =
    'h-10 w-full min-w-0 rounded-lg border border-input bg-card px-3 text-sm outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50';
