import { cn } from '@/lib/utils';

/** Dấu EduNest: một ô "khối ghép" — nền xanh than, phần tư tròn hổ phách (tổ), chấm trắng (người học). */
export function LogoMark({ className = 'size-8' }: { className?: string }) {
    return (
        <span
            className={cn(
                'inline-flex aspect-square shrink-0 overflow-hidden rounded-[6px]',
                className,
            )}
        >
            <svg viewBox="0 0 40 40" className="h-full w-full" aria-hidden>
                <rect width="40" height="40" fill="#0B1F3A" />
                <path d="M0 40 V8 A32 32 0 0 1 32 40 Z" fill="#F59E0B" />
                <circle cx="29" cy="11" r="5" fill="#FFFFFF" />
            </svg>
            <span className="sr-only">EduNest</span>
        </span>
    );
}

export function Wordmark({ className }: { className?: string }) {
    return (
        <span
            className={cn('text-lg font-extrabold tracking-tight', className)}
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
