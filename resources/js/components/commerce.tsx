import { Link } from '@inertiajs/react';
import { Star } from 'lucide-react';
import { cn } from '@/lib/utils';
import { show as publicCourse } from '@/routes/public/courses';

export type CourseCardData = {
    id: number;
    slug: string;
    title: string;
    cover: string | null;
    teacher: string | null;
    selling: boolean;
    rating_avg: string | null;
    rating_count: number;
    price: number;
    list_price: number;
    promotion: {
        id: number;
        price: number;
        ends_at: string;
        ends_on: string;
    } | null;
};

export const vnd = (n: number) => `${n.toLocaleString('vi-VN')}đ`;

export function vnTime(iso: string | null | undefined, withTime = true) {
    if (!iso) {
        return '—';
    }

    return new Date(iso).toLocaleString('vi-VN', {
        timeZone: 'Asia/Ho_Chi_Minh',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
        ...(withTime ? { hour: '2-digit', minute: '2-digit' } : {}),
    });
}

/** Giá sau khuyến mãi; giá gốc gạch ngang khi đang có đợt (MH-KH-03, BR-TT-62). */
export function PriceTag({
    card,
}: {
    card: Pick<CourseCardData, 'price' | 'list_price' | 'promotion'>;
}) {
    return (
        <span className="flex flex-wrap items-baseline gap-x-2">
            <span className="font-bold tabular-nums">
                <span className="sr-only">Giá </span>
                {vnd(card.price)}
            </span>
            {card.promotion && (
                <s className="text-sm text-muted-foreground tabular-nums">
                    <span className="sr-only">Giá gốc </span>
                    {vnd(card.list_price)}
                </s>
            )}
        </span>
    );
}

export function Stars({
    value,
    className,
}: {
    value: number;
    className?: string;
}) {
    return (
        <span
            className={cn('inline-flex items-center gap-0.5', className)}
            role="img"
            aria-label={`${value} trên 5 sao`}
        >
            {[1, 2, 3, 4, 5].map((i) => (
                <Star
                    key={i}
                    aria-hidden
                    className={cn(
                        'size-4',
                        i <= Math.round(value)
                            ? 'fill-amber text-amber'
                            : 'text-border',
                    )}
                />
            ))}
        </span>
    );
}

/** Dòng khóa học có ảnh bìa thật, tên, giáo viên, giá; phần thao tác do trang truyền vào. */
export function CourseLine({
    card,
    children,
}: {
    card: CourseCardData;
    children?: React.ReactNode;
}) {
    return (
        <li className="flex flex-wrap items-center gap-4 py-4 first:pt-0 last:pb-0">
            <div className="aspect-video w-32 shrink-0 overflow-hidden rounded-lg bg-muted">
                {card.cover && (
                    <img
                        src={card.cover}
                        alt=""
                        className="size-full object-cover"
                        decoding="async"
                    />
                )}
            </div>
            <div className="min-w-0 flex-1 space-y-1">
                <Link
                    href={publicCourse(card.slug)}
                    className="font-semibold wrap-anywhere hover:underline"
                >
                    {card.title}
                </Link>
                {card.teacher && (
                    <p className="text-sm text-muted-foreground">
                        {card.teacher}
                    </p>
                )}
                <PriceTag card={card} />
                {!card.selling && (
                    <p className="text-sm text-destructive">
                        Khóa đã ngừng bán.
                    </p>
                )}
            </div>
            {children && (
                <div className="flex flex-wrap items-center gap-2">
                    {children}
                </div>
            )}
        </li>
    );
}
