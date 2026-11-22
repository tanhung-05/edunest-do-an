import { Head, Link, usePage } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { dashboard, home } from '@/routes';

type Props = {
    status: number;
    message?: string;
    retryAfter?: number | null;
};

const titles: Record<number, string> = {
    403: 'Bạn không có quyền truy cập',
    404: 'Không tìm thấy trang',
    419: 'Phiên đã hết hiệu lực. Hãy tải lại trang',
    429: 'Thử lại sau',
    503: 'Dịch vụ tạm thời gián đoạn',
};

export default function ErrorPage({ status, message, retryAfter }: Props) {
    const { auth } = usePage().props;
    const heading = useRef<HTMLHeadingElement>(null);
    const title = titles[status] ?? 'Đã có lỗi';
    // Chỉ hiện thông báo nghiệp vụ tiếng Việt do máy chủ đặt (vd. “Tài khoản bị khóa”), không hiện câu mặc định của framework.
    const detail = message && /[À-ỹ]/.test(message) ? message : null;

    useEffect(() => heading.current?.focus(), []);

    return (
        <div className="flex min-h-svh items-center justify-center bg-background p-6">
            <Head title={title} />
            <div className="w-full max-w-md space-y-6">
                <div className="inline-block rounded-md bg-surface px-4 py-2 text-3xl font-bold text-surface-ink">
                    {status}
                </div>
                <h1
                    ref={heading}
                    tabIndex={-1}
                    className="text-2xl font-bold outline-none"
                >
                    {title}
                </h1>
                {detail && <p className="text-muted-foreground">{detail}</p>}
                {status === 429 && retryAfter ? (
                    <p aria-live="polite" className="text-muted-foreground">
                        Thử lại sau {retryAfter} giây.
                    </p>
                ) : null}
                <div className="flex flex-wrap gap-3">
                    <Link
                        href={auth?.user ? dashboard() : home()}
                        className="rounded-md bg-accent px-5 py-2.5 font-semibold text-accent-foreground"
                    >
                        {auth?.user ? 'Về trang của tôi' : 'Về trang chủ'}
                    </Link>
                    {(status === 503 || status === 429) && (
                        <button
                            type="button"
                            onClick={() => window.location.reload()}
                            className="rounded-md border border-border px-5 py-2.5 font-medium"
                        >
                            Tải lại
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
