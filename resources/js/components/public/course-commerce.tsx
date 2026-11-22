import { router } from '@inertiajs/react';
import { Heart, ShoppingCart } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Stars, vnd, vnTime } from '@/components/commerce';
import { textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

export type Pricing = {
    price: number;
    list_price: number;
    promotion: {
        id: number;
        price: number;
        ends_at: string;
        ends_on: string;
    } | null;
};

export type CommerceCta = {
    renew_url: string | null;
    extension_price: number | null;
    cart_url: string | null;
    wishlist_url: string | null;
    in_cart: boolean;
    wished: boolean;
    cart_count: number;
};

type RatingRow = {
    id: number;
    student: string;
    stars: number;
    comment: string | null;
    reply: string | null;
    created_at: string | null;
};

export type Ratings = {
    avg: string | null;
    count: number;
    distribution: number[];
    items: RatingRow[];
    own: (RatingRow & { hidden: boolean; hidden_reason: string | null }) | null;
    can_rate: boolean;
    progress: { done: number; total: number } | null;
    store_url: string | null;
};

function useCountdown(until: string | null) {
    const [now, setNow] = useState(() => Date.now());

    useEffect(() => {
        if (!until) {
            return;
        }

        const id = window.setInterval(() => setNow(Date.now()), 1000);

        return () => window.clearInterval(id);
    }, [until]);

    if (!until) {
        return null;
    }

    const left = Math.max(0, new Date(until).getTime() - now);
    const d = Math.floor(left / 86_400_000);
    const h = Math.floor((left % 86_400_000) / 3_600_000);
    const m = Math.floor((left % 3_600_000) / 60_000);
    const s = Math.floor((left % 60_000) / 1000);

    return left === 0
        ? 'Đã kết thúc'
        : `${d > 0 ? `${d} ngày ` : ''}${String(h).padStart(2, '0')}:${String(m).padStart(2, '0')}:${String(s).padStart(2, '0')}`;
}

/** Giá sau khuyến mãi kèm đếm ngược tới 00:00 giờ Việt Nam sau ngày cuối (BR-TT-62, CR-121). */
export function PromoPrice({ pricing }: { pricing: Pricing }) {
    const left = useCountdown(pricing.promotion?.ends_at ?? null);

    return (
        <div className="space-y-1">
            <p className="flex flex-wrap items-baseline gap-x-3">
                <span className="font-bold tabular-nums">
                    <span className="sr-only">Giá bán </span>
                    {vnd(pricing.price)}
                </span>
                {pricing.promotion && (
                    <s className="text-base font-normal text-muted-foreground tabular-nums">
                        <span className="sr-only">Giá gốc </span>
                        {vnd(pricing.list_price)}
                    </s>
                )}
            </p>
            {pricing.promotion && (
                <p className="font-sans text-sm font-medium text-destructive">
                    Khuyến mãi còn <span className="tabular-nums">{left}</span>
                </p>
            )}
        </div>
    );
}

export function CommerceButtons({ cta }: { cta: CommerceCta }) {
    const [busy, setBusy] = useState(false);
    const post = (url: string) =>
        router.post(
            url,
            {},
            {
                preserveScroll: true,
                onStart: () => setBusy(true),
                onFinish: () => setBusy(false),
            },
        );

    if (!cta.cart_url && !cta.wishlist_url && !cta.renew_url) {
        return null;
    }

    return (
        <div className="flex flex-wrap gap-2">
            {cta.renew_url && (
                <Button
                    className="flex-1"
                    disabled={busy}
                    onClick={() => post(cta.renew_url!)}
                >
                    Gia hạn
                    {cta.extension_price !== null &&
                        ` · ${vnd(cta.extension_price)}`}
                </Button>
            )}
            {cta.cart_url && (
                <Button
                    variant="outline"
                    className="flex-1"
                    disabled={busy || cta.in_cart}
                    onClick={() => post(cta.cart_url!)}
                >
                    <ShoppingCart aria-hidden />
                    {cta.in_cart ? 'Đã trong giỏ' : 'Thêm vào giỏ'}
                </Button>
            )}
            {cta.wishlist_url && (
                <Button
                    variant="outline"
                    size="icon"
                    aria-pressed={cta.wished}
                    aria-label={
                        cta.wished ? 'Bỏ khỏi yêu thích' : 'Thêm vào yêu thích'
                    }
                    disabled={busy}
                    onClick={() => post(cta.wishlist_url!)}
                >
                    <Heart
                        aria-hidden
                        className={cn(
                            cta.wished && 'fill-destructive text-destructive',
                        )}
                    />
                </Button>
            )}
        </div>
    );
}

export function RatingForm({ ratings }: { ratings: Ratings }) {
    const [stars, setStars] = useState(ratings.own?.stars ?? 0);
    const [comment, setComment] = useState(ratings.own?.comment ?? '');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [busy, setBusy] = useState(false);

    if (!ratings.store_url) {
        return null;
    }

    if (ratings.own?.hidden) {
        return (
            <p className="rounded-xl bg-accent px-4 py-3 text-sm">
                Đánh giá của bạn đang bị ẩn: {ratings.own.hidden_reason}
            </p>
        );
    }

    if (!ratings.can_rate) {
        const p = ratings.progress;

        return (
            <p className="rounded-xl bg-accent px-4 py-3 text-sm text-muted-foreground">
                {p && p.total > 0 && p.done * 100 < 30 * p.total
                    ? `Hoàn thành ít nhất 30% bài bắt buộc để đánh giá (đã xong ${p.done}/${p.total}).`
                    : 'Bạn chỉ đánh giá được khi quyền học còn hạn.'}
            </p>
        );
    }

    return (
        <form
            className="grid gap-3 rounded-2xl border border-border bg-card p-5"
            onSubmit={(e) => {
                e.preventDefault();
                router.post(
                    ratings.store_url!,
                    { stars, comment },
                    {
                        preserveScroll: true,
                        onStart: () => setBusy(true),
                        onFinish: () => setBusy(false),
                        onError: (err) => setErrors(err),
                        onSuccess: () => setErrors({}),
                    },
                );
            }}
        >
            <fieldset>
                <legend className="mb-2 font-semibold">
                    {ratings.own ? 'Sửa đánh giá của bạn' : 'Đánh giá khóa học'}
                </legend>
                <div className="flex gap-1">
                    {[1, 2, 3, 4, 5].map((i) => (
                        <label key={i} className="cursor-pointer">
                            <input
                                type="radio"
                                name="stars"
                                value={i}
                                checked={stars === i}
                                onChange={() => setStars(i)}
                                className="peer sr-only"
                            />
                            <span className="sr-only">{i} sao</span>
                            <span
                                aria-hidden
                                className={cn(
                                    'grid size-9 place-items-center rounded-lg text-2xl peer-focus-visible:ring-2 peer-focus-visible:ring-ring',
                                    i <= stars ? 'text-amber' : 'text-border',
                                )}
                            >
                                ★
                            </span>
                        </label>
                    ))}
                </div>
            </fieldset>
            {errors.stars && (
                <p className="text-sm text-destructive">{errors.stars}</p>
            )}
            <label htmlFor="rating-comment" className="sr-only">
                Nhận xét
            </label>
            <textarea
                id="rating-comment"
                value={comment}
                maxLength={2000}
                onChange={(e) => setComment(e.target.value)}
                placeholder="Nhận xét (không bắt buộc)"
                className={textareaClass}
            />
            {errors.comment && (
                <p className="text-sm text-destructive">{errors.comment}</p>
            )}
            <Button className="w-fit" disabled={busy || stars === 0}>
                Gửi đánh giá
            </Button>
        </form>
    );
}

export function RatingsSection({ ratings }: { ratings: Ratings }) {
    const max = Math.max(1, ...ratings.distribution);

    return (
        <section
            className="flex flex-col gap-4"
            aria-labelledby="ratings-heading"
        >
            <h2
                id="ratings-heading"
                className="font-display text-2xl font-bold tracking-tight md:text-3xl"
            >
                Đánh giá của học viên
            </h2>
            <div className="grid gap-6 rounded-2xl border border-border bg-card p-6 shadow-soft sm:grid-cols-[10rem_1fr]">
                <div className="text-center">
                    <p className="font-display text-5xl font-bold tabular-nums">
                        {ratings.avg
                            ? Number(ratings.avg).toLocaleString('vi-VN', {
                                  maximumFractionDigits: 1,
                              })
                            : '—'}
                    </p>
                    <Stars
                        value={Number(ratings.avg ?? 0)}
                        className="justify-center"
                    />
                    <p className="mt-1 text-sm text-muted-foreground">
                        {ratings.count} đánh giá
                    </p>
                </div>
                <ul
                    className="grid gap-1.5 text-sm"
                    aria-label="Phân bố số sao"
                >
                    {ratings.distribution.map((n, i) => (
                        <li key={5 - i} className="flex items-center gap-2">
                            <span className="w-10 tabular-nums">
                                {5 - i} sao
                            </span>
                            <span className="h-2 flex-1 overflow-hidden rounded-full bg-muted">
                                <span
                                    className="block h-full rounded-full bg-amber"
                                    style={{ width: `${(n / max) * 100}%` }}
                                />
                            </span>
                            <span className="w-8 text-right text-muted-foreground tabular-nums">
                                {n}
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
            <RatingForm ratings={ratings} />
            {ratings.items.length > 0 && (
                <ul className="divide-y divide-border rounded-2xl border border-border bg-card px-6 shadow-soft">
                    {ratings.items.map((r) => (
                        <li key={r.id} className="space-y-1.5 py-4">
                            <div className="flex flex-wrap items-center gap-x-3">
                                <Stars value={r.stars} />
                                <span className="font-medium">{r.student}</span>
                                <span className="text-sm text-muted-foreground">
                                    {vnTime(r.created_at, false)}
                                </span>
                            </div>
                            {r.comment && (
                                <p className="leading-relaxed wrap-anywhere">
                                    {r.comment}
                                </p>
                            )}
                            {r.reply && (
                                <p className="rounded-lg bg-muted px-3 py-2 text-sm">
                                    <span className="font-medium">
                                        Giáo viên trả lời:{' '}
                                    </span>
                                    {r.reply}
                                </p>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}
