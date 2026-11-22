import { Head, Link, router } from '@inertiajs/react';
import {
    CircleCheck,
    Gauge,
    Layers,
    RotateCcw,
    Sparkles,
    Target,
    Zap,
} from 'lucide-react';
import { useRef } from 'react';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Row = { front: string; back: string; result: string };

type Props = {
    game: {
        id: number;
        kind: string;
        kind_label: string;
        status: string;
        status_label: string;
        end_reason: string | null;
        count: number;
        factor: number;
        set: string;
        xp: number | null;
        replay_url: string;
        review_url: string;
    };
    good: Row[];
    again: Row[];
};

function Cards({
    title,
    rows,
    empty,
    tone,
}: {
    title: string;
    rows: Row[];
    empty: string;
    tone: 'good' | 'again';
}) {
    return (
        <Block className="grid content-start gap-3">
            <h2 className="flex items-center gap-2 text-lg font-semibold">
                <span
                    aria-hidden
                    className={cn(
                        'size-2.5 rounded-full',
                        tone === 'good' ? 'bg-success' : 'bg-amber',
                    )}
                />
                {title}
                <span className="text-sm font-normal text-muted-foreground tabular-nums">
                    {rows.length}
                </span>
            </h2>
            {rows.length === 0 ? (
                <p className="text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="grid gap-2 sm:grid-cols-2">
                    {rows.map((r, i) => (
                        <li
                            key={i}
                            data-tile
                            className={cn(
                                'grid gap-0.5 rounded-xl px-3.5 py-2.5 ring-1',
                                tone === 'good'
                                    ? 'bg-success/5 ring-success/20'
                                    : 'bg-amber/10 ring-amber/30',
                            )}
                        >
                            <span className="font-semibold wrap-anywhere">
                                {r.front}
                            </span>
                            <span className="text-sm wrap-anywhere text-muted-foreground">
                                {r.back}
                            </span>
                            <span className="text-xs text-muted-foreground">
                                {r.result}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </Block>
    );
}

/** Màn kết thúc game (BR-XP-69): thẻ đã nhớ tốt / cần ôn lại và thưởng phiên đã ghi. */
export default function GameResult({ game, good, again }: Props) {
    const scope = useRef<HTMLDivElement>(null);
    const quit = game.status === 'BO_DO';
    const ratio = game.count > 0 ? good.length / game.count : 0;
    const great = !quit && ratio >= 0.8;
    const headline = quit
        ? 'Lượt chơi đã dừng giữa chừng'
        : great
          ? `Xuất sắc! Bạn nhớ ${good.length}/${game.count} thẻ`
          : ratio >= 0.5
            ? `Khá lắm, ${good.length}/${game.count} thẻ đã chắc`
            : `Mới nhớ ${good.length}/${game.count} thẻ, ôn thêm một lượt nhé`;

    // Một khoảnh khắc chúc mừng: thẻ hiện lần lượt; nhớ từ 80% trở lên thì bắn pháo giấy một lần.
    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                gsap.from('[data-tile]', {
                    y: 12,
                    opacity: 0,
                    duration: 0.45,
                    stagger: 0.04,
                    ease: 'power2.out',
                });
                const host =
                    scope.current?.querySelector<HTMLElement>(
                        '[data-confetti]',
                    );
                if (!great || !host) {
                    return;
                }
                const colors = ['#fbbf24', '#34d399', '#38bdf8', '#f87171'];
                for (let i = 0; i < 36; i++) {
                    const p = document.createElement('span');
                    p.style.cssText = `position:absolute;left:50%;top:60%;width:${6 + (i % 3) * 3}px;height:${10 + (i % 4) * 2}px;border-radius:2px;background:${colors[i % colors.length]}`;
                    host.appendChild(p);
                    gsap.to(p, {
                        x: gsap.utils.random(-320, 320),
                        y: gsap.utils.random(-170, -40),
                        rotation: gsap.utils.random(-360, 360),
                        duration: 0.9,
                        ease: 'power3.out',
                    });
                    gsap.to(p, {
                        y: '+=180',
                        opacity: 0,
                        duration: 1.3,
                        delay: 0.9,
                        ease: 'power1.in',
                        onComplete: () => p.remove(),
                    });
                }
            });
        },
        { scope },
    );

    return (
        <Page
            title={`${game.kind_label} · ${game.set}`}
            description={
                quit
                    ? `Bỏ dở${game.end_reason ? ` (${game.end_reason})` : ''}. Lượt bỏ dở không được thưởng phiên.`
                    : 'Đã chơi hết lượt. Mini game không đổi lịch ôn thẻ; thẻ cần ôn lại nên ôn ngay khi còn nhớ.'
            }
            actions={
                <>
                    <Button asChild variant="secondary">
                        <Link href={game.review_url}>
                            <Layers /> Ôn thẻ
                        </Link>
                    </Button>
                    <Button
                        className="bg-amber text-navy hover:bg-amber/90"
                        onClick={() =>
                            router.post(game.replay_url, { kind: game.kind })
                        }
                    >
                        <RotateCcw /> Chơi lại
                    </Button>
                </>
            }
            stats={[
                {
                    label: 'Đã nhớ tốt',
                    value: `${good.length}/${game.count}`,
                    icon: CircleCheck,
                },
                {
                    label: 'Cần ôn lại',
                    value: again.length,
                    icon: Target,
                    attention: again.length > 0,
                },
                {
                    label: 'Thưởng phiên',
                    value: quit
                        ? 0
                        : game.xp === null
                          ? 'Đang ghi…'
                          : `+${game.xp} XP`,
                    icon: Zap,
                },
                {
                    label: 'Hệ số cấp',
                    value: `×${game.factor.toFixed(2)}`,
                    icon: Gauge,
                },
            ]}
        >
            <Head title="Kết quả mini game" />
            <div ref={scope} className="grid gap-5">
                <Block
                    tone={great ? 'accent' : 'plain'}
                    className="relative flex flex-wrap items-center gap-4 overflow-hidden"
                >
                    <div
                        data-confetti
                        aria-hidden
                        className="pointer-events-none absolute inset-0"
                    />
                    <span
                        aria-hidden
                        className={cn(
                            'grid size-12 shrink-0 place-items-center rounded-2xl',
                            great
                                ? 'bg-navy text-amber dark:bg-amber dark:text-navy'
                                : 'bg-muted text-foreground/70',
                        )}
                    >
                        <Sparkles className="size-6" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="font-display text-xl font-bold tracking-tight">
                            {headline}
                        </p>
                        <div
                            className="mt-2 h-2 max-w-md overflow-hidden rounded-full bg-foreground/10"
                            role="img"
                            aria-label={`Đã nhớ ${Math.round(ratio * 100)}% số thẻ`}
                        >
                            <div
                                className="h-full rounded-full bg-success"
                                style={{ width: `${ratio * 100}%` }}
                            />
                        </div>
                    </div>
                    {again.length > 0 && (
                        <Button asChild variant="outline">
                            <Link href={game.review_url}>
                                Ôn {again.length} thẻ cần ôn
                            </Link>
                        </Button>
                    )}
                </Block>
                <div className="grid items-start gap-5 lg:grid-cols-2">
                    <Cards
                        title="Cần ôn lại"
                        rows={again}
                        tone="again"
                        empty="Không có thẻ nào cần ôn lại."
                    />
                    <Cards
                        title="Đã nhớ tốt"
                        rows={good}
                        tone="good"
                        empty="Chưa có thẻ nào."
                    />
                </div>
            </div>
        </Page>
    );
}
