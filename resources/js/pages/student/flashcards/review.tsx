import { Head, Link, router } from '@inertiajs/react';
import { PartyPopper, RotateCw, Volume2 } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { speak } from '@/lib/speak';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Card = {
    id: number;
    front: string;
    back: string;
    back_version: number;
    image_url: string | null;
    speech_lang: string | null;
    grade_url: string;
};

type Props = {
    set: { id: number; title: string; active: boolean };
    cards: Card[];
    grades: { value: string; label: string }[];
};

/** Màu từng mức tự chấm: Quên đỏ, Khó hổ phách, Nhớ xanh nhạt, Dễ xanh đậm. */
const gradeTone: Record<string, { dot: string; ring: string }> = {
    QUEN: { dot: 'bg-destructive', ring: 'hover:ring-destructive/50' },
    KHO: { dot: 'bg-amber', ring: 'hover:ring-amber/60' },
    NHO: { dot: 'bg-success/70', ring: 'hover:ring-success/40' },
    DE: { dot: 'bg-success', ring: 'hover:ring-success/60' },
};

/** MH-HV-19: lật thẻ, phát âm, tự chấm bốn mức theo SM-2 rút gọn (M22, CR-90/161). */
export default function FlashcardReview({ set, cards, grades }: Props) {
    const card = cards[0];
    const [flipped, setFlipped] = useState<number | null>(null);
    const [busy, setBusy] = useState(false);
    const showBack = card && flipped === card.id;
    const scope = useRef<HTMLDivElement>(null);

    const grade = (value: string) => {
        if (!card || busy) {
            return;
        }
        setBusy(true);
        router.post(
            card.grade_url,
            {
                grade: value,
                back_version: card.back_version,
                request_id: crypto.randomUUID(),
            },
            {
                preserveScroll: true,
                onFinish: () => setBusy(false),
                onSuccess: () => setFlipped(null),
            },
        );
    };

    // Thẻ mới trượt từ chồng bài lên mỗi khi đổi thẻ.
    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                gsap.from('[data-deck-top]', {
                    y: 28,
                    scale: 0.94,
                    opacity: 0,
                    duration: 0.5,
                    ease: 'back.out(1.5)',
                });
            });
        },
        { scope, dependencies: [card?.id] },
    );

    // Phím tắt: Space hoặc Enter lật thẻ, 1–4 tự chấm khi đã lật.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if (!card) {
                return;
            }
            const tag = (e.target as HTMLElement | null)?.tagName;
            if (tag === 'INPUT' || tag === 'TEXTAREA') {
                return;
            }
            if (e.key === ' ') {
                e.preventDefault();
                setFlipped(showBack ? null : card.id);

                return;
            }
            const n = Number(e.key);
            if (showBack && set.active && n >= 1 && n <= grades.length) {
                grade(grades[n - 1].value);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    return (
        <Page
            title={set.title}
            description={`Thẻ cần ôn hôm nay: còn ${cards.length}. Lật thẻ, tự nhớ lại rồi chấm thật lòng để lịch ôn hợp với bạn.`}
            className="max-w-3xl"
            actions={
                <Button asChild variant="outline">
                    <Link href={student.flashcards.index()}>Về bộ thẻ</Link>
                </Button>
            }
        >
            <Head title={`Ôn thẻ · ${set.title}`} />
            {!card ? (
                <Block className="grid justify-items-center gap-3 py-10 text-center">
                    <span
                        aria-hidden
                        className="grid size-14 place-items-center rounded-2xl bg-amber/25 text-navy dark:text-amber"
                    >
                        <PartyPopper className="size-7" />
                    </span>
                    <p className="font-display text-xl font-bold">
                        Xong hết thẻ đến hạn hôm nay
                    </p>
                    <p className="max-w-sm text-sm text-muted-foreground">
                        Quay lại vào ngày ôn tiếp theo, hoặc chơi một lượt mini
                        game để giữ nhịp.
                    </p>
                    <Button asChild>
                        <Link href={student.flashcards.index()}>Về bộ thẻ</Link>
                    </Button>
                </Block>
            ) : (
                <div ref={scope} className="grid gap-5">
                    <div className="relative [perspective:1400px]">
                        {cards.length > 2 && (
                            <div
                                aria-hidden
                                className="absolute inset-x-8 -bottom-4 h-full rounded-3xl border border-border bg-card/70 shadow-soft"
                            />
                        )}
                        {cards.length > 1 && (
                            <div
                                aria-hidden
                                className="absolute inset-x-4 -bottom-2 h-full rounded-3xl border border-border bg-card shadow-soft"
                            />
                        )}
                        <button
                            key={card.id}
                            data-deck-top
                            type="button"
                            onClick={() =>
                                setFlipped(showBack ? null : card.id)
                            }
                            className="relative block min-h-80 w-full rounded-3xl text-left outline-none focus-visible:ring-4 focus-visible:ring-amber/60"
                            aria-label={
                                showBack
                                    ? `Mặt sau: ${card.back}. Bấm để lật lại`
                                    : `Mặt trước: ${card.front}. Bấm để lật`
                            }
                        >
                            <div
                                className={cn(
                                    'relative grid min-h-80 transition-transform duration-500 ease-[cubic-bezier(0.22,1,0.36,1)] [transform-style:preserve-3d] motion-reduce:transition-none',
                                    showBack && '[transform:rotateY(180deg)]',
                                )}
                            >
                                <Face side="Mặt trước">
                                    {card.image_url && (
                                        <img
                                            src={card.image_url}
                                            alt=""
                                            className="max-h-40 rounded-xl object-contain"
                                        />
                                    )}
                                    <span className="font-display text-4xl font-bold tracking-tight text-balance wrap-anywhere whitespace-pre-wrap">
                                        {card.front}
                                    </span>
                                </Face>
                                <Face side="Mặt sau" back>
                                    <span className="font-display text-3xl font-bold text-balance wrap-anywhere whitespace-pre-wrap">
                                        {card.back}
                                    </span>
                                    <span className="text-sm text-white/65">
                                        {card.front}
                                    </span>
                                </Face>
                            </div>
                        </button>
                    </div>

                    <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <RotateCw aria-hidden className="size-4" />
                            Bấm thẻ hoặc phím cách để lật
                        </p>
                        <div className="flex gap-2">
                            {card.speech_lang && (
                                <Button
                                    variant="ghost"
                                    onClick={() =>
                                        speak(card.front, card.speech_lang)
                                    }
                                >
                                    <Volume2 /> Phát âm
                                </Button>
                            )}
                            {!showBack && (
                                <Button
                                    variant="secondary"
                                    onClick={() => setFlipped(card.id)}
                                >
                                    Xem mặt sau
                                </Button>
                            )}
                        </div>
                    </div>

                    {showBack && set.active && (
                        <div className="grid grid-cols-2 gap-2 sm:grid-cols-4">
                            {grades.map((g, i) => (
                                <button
                                    key={g.value}
                                    type="button"
                                    disabled={busy}
                                    onClick={() => grade(g.value)}
                                    className={cn(
                                        'flex h-14 items-center justify-between gap-2 rounded-xl border border-border bg-card px-4 font-semibold ring-2 shadow-soft ring-transparent transition-[box-shadow,transform] hover:-translate-y-0.5 focus-visible:ring-amber/60 focus-visible:outline-none disabled:opacity-60',
                                        gradeTone[g.value]?.ring,
                                    )}
                                >
                                    <span className="flex items-center gap-2">
                                        <span
                                            aria-hidden
                                            className={cn(
                                                'size-2.5 rounded-full',
                                                gradeTone[g.value]?.dot,
                                            )}
                                        />
                                        {g.label}
                                    </span>
                                    <kbd className="rounded-md bg-muted px-1.5 text-xs font-medium text-muted-foreground">
                                        {i + 1}
                                    </kbd>
                                </button>
                            ))}
                        </div>
                    )}
                    {!set.active && (
                        <p className="text-sm text-destructive">
                            Ghi danh đã hết hạn: chỉ xem, không tự chấm được.
                        </p>
                    )}
                </div>
            )}
        </Page>
    );
}

function Face({
    side,
    back,
    children,
}: {
    side: string;
    back?: boolean;
    children: React.ReactNode;
}) {
    return (
        <div
            className={cn(
                'col-start-1 row-start-1 grid place-items-center content-center gap-3 rounded-3xl border p-8 text-center shadow-[0_24px_50px_-28px_rgb(15_23_42/0.55)] [backface-visibility:hidden]',
                back
                    ? '[transform:rotateY(180deg)] border-navy bg-navy text-white dark:border-white/10 dark:bg-slate-900'
                    : 'border-border bg-card',
            )}
        >
            <span
                className={cn(
                    'absolute top-4 left-5 text-xs font-medium',
                    back ? 'text-white/60' : 'text-muted-foreground',
                )}
            >
                {side}
            </span>
            {children}
        </div>
    );
}
