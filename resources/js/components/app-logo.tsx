import { cn } from '@/lib/utils';

/**
 * Dấu EduNest (22/11): cuốn sách mở cong lại thành chiếc tổ, ôm quả trứng hổ phách (người học).
 * Nền xanh than; góc bo do nơi dùng quyết định (rounded-*). Hình gốc: public/images/brand/.
 */
export function LogoMark({ className = 'size-8' }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex aspect-square shrink-0 overflow-hidden rounded-[6px]',
                className,
            )}
        >
            <svg viewBox="0 0 48 48" className="h-full w-full" aria-hidden>
                <rect width="48" height="48" fill="#0B1F3A" />
                <path
                    d="M8 21c6.5 0 11.8 2.1 16 6.3 4.2-4.2 9.5-6.3 16-6.3v6.6c0 7.4-7.2 12.4-16 12.4S8 35 8 27.6z"
                    fill="#FFFFFF"
                />
                <path d="M24 27.3V40" stroke="#0B1F3A" strokeWidth="1.8" />
                <circle
                    cx="24"
                    cy="19.5"
                    r="5.2"
                    fill="#F59E0B"
                    stroke="#0B1F3A"
                    strokeWidth="2"
                />
            </svg>
            <span className="sr-only">EduNest</span>
        </span>
    );
}

export function Wordmark({ className }: { className?: string }) {
    return (
        <span
            className={cn(
                'font-display text-lg font-extrabold tracking-tight',
                className,
            )}
        >
            Edu<span className="text-amber">Nest</span>
        </span>
    );
}

export default function AppLogo() {
    return (
        <>
            <LogoMark className="size-8 ring-1 ring-white/20" />
            <div className="ml-1 grid flex-1 text-left">
                <Wordmark className="truncate text-base leading-tight" />
            </div>
        </>
    );
}
