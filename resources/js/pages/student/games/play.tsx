import { Head, Link, router } from '@inertiajs/react';
import {
    Check,
    CheckCircle2,
    Crosshair,
    Flame,
    Maximize2,
    Timer,
    Volume2,
    X,
    XCircle,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';
import { Button } from '@/components/ui/button';
import { speak } from '@/lib/speak';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Props = {
    game: {
        id: number;
        kind: 'BAN_BONG' | 'MUA_CHU';
        kind_label: string;
        set: string;
        position: number;
        count: number;
        answer_url: string;
        results: (string | null)[];
    };
    card: {
        position: number;
        front: string;
        image_url: string | null;
        speech_lang: string | null;
        options: string[];
    } | null;
    last: {
        position: number;
        result: string | null;
        back: string;
        front: string;
    } | null;
};

/** Thời gian chữ rơi từ đỉnh tới đáy ở Mưa chữ. */
const FALL_MS = 14_000;

/** Sắc độ cho từng bong bóng (xanh trời, hổ phách, xanh ngọc, hồng san hô): đủ khác nhau để mắt tách được. */
const BUBBLE_HUES = [199, 38, 168, 12];
const BUBBLE_SIZES = [
    'size-36 md:size-44',
    'size-40 md:size-48',
    'size-36 md:size-44',
    'size-40 md:size-48',
];

function bubbleStyle(hue: number): React.CSSProperties {
    return {
        backgroundImage: [
            'radial-gradient(circle at 30% 26%, rgb(255 255 255 / 0.9) 0 5%, rgb(255 255 255 / 0.25) 12%, transparent 30%)',
            'radial-gradient(circle at 70% 80%, rgb(255 255 255 / 0.18) 0 8%, transparent 22%)',
            `radial-gradient(circle at 50% 50%, hsl(${hue} 85% 60% / 0.12) 52%, hsl(${hue} 85% 62% / 0.5) 74%, hsl(${hue} 90% 78% / 0.85) 100%)`,
        ].join(','),
        boxShadow: `0 24px 48px -22px rgb(2 6 23 / 0.9), inset 0 -14px 32px hsl(${hue} 90% 70% / 0.25), 0 0 0 1px rgb(255 255 255 / 0.22)`,
    };
}

/** MH-HV-20: Bắn bong bóng và Mưa chữ, toàn màn hình (QT-24 bước 7–11, BR-XP-69). */
export default function GamePlay(props: Props) {
    return (
        <Arena
            key={`${props.game.id}-${props.card?.position ?? 0}`}
            {...props}
        />
    );
}

function Arena({ game, card, last }: Props) {
    const root = useRef<HTMLDivElement>(null);
    const fx = useRef<HTMLDivElement>(null);
    const [busy, setBusy] = useState(false);
    const [typed, setTyped] = useState('');
    const [fall, setFall] = useState(0);
    const sent = useRef(false);

    const correct = game.results.filter((r) => r === 'DUNG').length;
    const streak = useMemo(() => {
        let n = 0;
        for (let i = game.position - 2; i >= 0; i--) {
            if (game.results[i] !== 'DUNG') {
                break;
            }
            n++;
        }

        return n;
    }, [game.results, game.position]);

    const send = (value: string | null, bottom = false) => {
        if (!card || sent.current) {
            return;
        }
        sent.current = true;
        setBusy(true);
        router.post(
            game.answer_url,
            {
                position: card.position,
                value,
                bottom,
                request_id: crypto.randomUUID(),
            },
            { preserveScroll: true, onFinish: () => setBusy(false) },
        );
    };
    const sendRef = useRef(send);
    useEffect(() => {
        sendRef.current = send;
    });

    useEffect(() => {
        if (game.kind !== 'MUA_CHU' || !card) {
            return;
        }
        const started = performance.now();
        let frame = 0;
        const step = () => {
            const p = Math.min(1, (performance.now() - started) / FALL_MS);
            setFall(p);
            if (p >= 1) {
                sendRef.current(null, true);

                return;
            }
            frame = requestAnimationFrame(step);
        };
        frame = requestAnimationFrame(step);

        return () => cancelAnimationFrame(frame);
    }, [game.kind, card]);

    // Phản hồi câu vừa trả lời: dải thông báo trượt xuống, cả sân chớp màu đúng/sai, sai thì rung nhẹ.
    useGSAP(
        () => {
            if (!last) {
                return;
            }
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                const good = last.result === 'DUNG';
                gsap.from('[data-feedback]', {
                    y: -18,
                    opacity: 0,
                    scale: 0.96,
                    duration: 0.5,
                    ease: 'back.out(2)',
                });
                if (!good) {
                    gsap.fromTo(
                        '[data-feedback]',
                        { x: -8 },
                        {
                            x: 0,
                            duration: 0.5,
                            ease: 'elastic.out(1, 0.3)',
                            delay: 0.1,
                        },
                    );
                }
                gsap.fromTo(
                    '[data-flash]',
                    { opacity: good ? 0.35 : 0.45 },
                    { opacity: 0, duration: 0.7, ease: 'power2.out' },
                );
            });
        },
        { scope: root },
    );

    /** Hạt văng ra từ điểm (x, y) trong sân: dùng cho bong bóng vỡ và đạn trúng chữ. */
    const burst = (x: number, y: number, colors: string[], count = 14) => {
        const layer = fx.current;
        if (!layer || !window.matchMedia(MOTION_OK).matches) {
            return;
        }
        for (let i = 0; i < count; i++) {
            const dot = document.createElement('span');
            const size = 4 + Math.random() * 6;
            dot.style.cssText = `position:absolute;left:${x}px;top:${y}px;width:${size}px;height:${size}px;border-radius:9999px;background:${colors[i % colors.length]};pointer-events:none`;
            layer.appendChild(dot);
            const angle = (Math.PI * 2 * i) / count + Math.random() * 0.4;
            const dist = 50 + Math.random() * 70;
            gsap.to(dot, {
                x: Math.cos(angle) * dist,
                y: Math.sin(angle) * dist + 20,
                opacity: 0,
                scale: 0.3,
                duration: 0.6 + Math.random() * 0.3,
                ease: 'power3.out',
                onComplete: () => dot.remove(),
            });
        }
    };

    const fullscreen = () => {
        if (document.fullscreenElement) {
            void document.exitFullscreen();
        } else {
            void root.current?.requestFullscreen();
        }
    };

    const rain = game.kind === 'MUA_CHU';

    return (
        <div
            ref={root}
            className="relative isolate flex min-h-[calc(100svh-4rem)] flex-col gap-4 overflow-hidden bg-slate-950 p-4 text-white md:min-h-svh md:p-6"
        >
            <Head title={`${game.kind_label} · ${game.set}`} />
            <img
                src={`/images/stock/${rain ? 'hoc-dem' : 'doc-sach-ngoai-troi'}.jpg`}
                alt=""
                aria-hidden
                className="absolute inset-0 -z-20 h-full w-full object-cover opacity-35"
            />
            <div
                aria-hidden
                className={cn(
                    'absolute inset-0 -z-10',
                    rain
                        ? 'bg-[radial-gradient(ellipse_at_top,rgb(14_116_144/0.35),transparent_60%),linear-gradient(to_bottom,rgb(2_6_23/0.55),rgb(2_6_23/0.92))]'
                        : 'bg-[radial-gradient(ellipse_at_center,rgb(2_6_23/0.25),rgb(2_6_23/0.85)_75%)]',
                )}
            />
            <div
                data-flash
                aria-hidden
                className={cn(
                    'pointer-events-none absolute inset-0 z-20 opacity-0',
                    last?.result === 'DUNG' ? 'bg-emerald-400' : 'bg-rose-500',
                )}
            />
            <div
                ref={fx}
                aria-hidden
                className="pointer-events-none absolute inset-0 z-30"
            />

            <header className="flex flex-wrap items-center gap-x-4 gap-y-3">
                <div className="min-w-0 flex-1">
                    <p className="truncate text-sm text-white/65">{game.set}</p>
                    <h1 className="font-display text-2xl font-bold tracking-tight">
                        {game.kind_label}
                    </h1>
                </div>
                <dl className="flex items-center gap-2 text-sm">
                    <div className="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">
                        <dt className="sr-only">Thẻ</dt>
                        <dd className="font-semibold tabular-nums">
                            {game.position}/{game.count}
                        </dd>
                    </div>
                    <div className="flex items-center gap-1.5 rounded-full bg-white/10 px-3 py-1.5 ring-1 ring-white/15">
                        <Check
                            aria-hidden
                            className="size-4 text-emerald-300"
                        />
                        <dt className="sr-only">Số thẻ đúng</dt>
                        <dd className="font-semibold tabular-nums">
                            {correct}
                        </dd>
                    </div>
                    <div
                        className={cn(
                            'flex items-center gap-1.5 rounded-full px-3 py-1.5 ring-1 transition-colors',
                            streak >= 2
                                ? 'bg-amber-400/20 text-amber-200 ring-amber-300/50'
                                : 'bg-white/10 ring-white/15',
                        )}
                    >
                        <Flame
                            aria-hidden
                            className={cn(
                                'size-4',
                                streak >= 2
                                    ? 'text-amber-300'
                                    : 'text-white/50',
                            )}
                        />
                        <dt className="sr-only">Chuỗi đúng liên tiếp</dt>
                        <dd className="font-semibold tabular-nums">{streak}</dd>
                    </div>
                </dl>
                <div className="flex items-center gap-1">
                    <Button
                        variant="ghost"
                        size="icon"
                        onClick={fullscreen}
                        aria-label="Toàn màn hình"
                        className="text-white hover:bg-white/10 hover:text-white"
                    >
                        <Maximize2 />
                    </Button>
                    <Button
                        asChild
                        variant="ghost"
                        size="sm"
                        className="text-white hover:bg-white/10 hover:text-white"
                    >
                        <Link href={student.flashcards.index()}>
                            <X /> Thoát
                        </Link>
                    </Button>
                </div>
                <ol
                    className="flex w-full gap-1"
                    aria-label={`Tiến độ: thẻ ${game.position} trên ${game.count}`}
                >
                    {game.results.map((r, i) => (
                        <li
                            key={i}
                            className={cn(
                                'h-1.5 flex-1 rounded-full bg-white/15',
                                r === 'DUNG' &&
                                    'bg-emerald-400 shadow-[0_0_10px_rgb(52_211_153/0.7)]',
                                (r === 'SAI' || r === 'CHAM_DAY') &&
                                    'bg-rose-500',
                                r === null &&
                                    i === game.position - 1 &&
                                    'bg-amber-300 motion-safe:animate-pulse',
                            )}
                        />
                    ))}
                </ol>
            </header>

            {last && (
                <p
                    data-feedback
                    role="status"
                    className={cn(
                        'flex items-center gap-2 self-center rounded-2xl px-4 py-2.5 text-sm ring-1 backdrop-blur-md md:text-base',
                        last.result === 'DUNG'
                            ? 'bg-emerald-500/15 text-emerald-50 ring-emerald-300/40'
                            : 'bg-rose-500/15 text-rose-50 ring-rose-300/40',
                    )}
                >
                    {last.result === 'DUNG' ? (
                        <CheckCircle2
                            aria-hidden
                            className="size-5 shrink-0 text-emerald-300"
                        />
                    ) : (
                        <XCircle
                            aria-hidden
                            className="size-5 shrink-0 text-rose-300"
                        />
                    )}
                    <span>
                        {last.result === 'CHAM_DAY'
                            ? 'Chạm đáy: '
                            : last.result === 'DUNG'
                              ? 'Chính xác: '
                              : 'Chưa đúng: '}
                        <b>{last.front}</b> là {last.back}
                    </span>
                </p>
            )}

            {card && game.kind === 'BAN_BONG' && (
                <Bubbles
                    card={card}
                    busy={busy}
                    onPick={send}
                    burst={burst}
                    root={root}
                />
            )}

            {card && game.kind === 'MUA_CHU' && (
                <section className="grid flex-1 grid-rows-[1fr_auto] gap-4">
                    <WordRain card={card} fall={fall} />
                    <form
                        className="mx-auto flex w-full max-w-2xl gap-2 rounded-2xl bg-white/10 p-2 ring-1 ring-white/15 backdrop-blur-md"
                        onSubmit={(e) => {
                            e.preventDefault();
                            if (typed.trim() === '') {
                                return;
                            }
                            const word =
                                root.current?.querySelector<HTMLElement>(
                                    '[data-word]',
                                );
                            const host = root.current?.getBoundingClientRect();
                            if (word && host) {
                                const r = word.getBoundingClientRect();
                                burst(
                                    r.left - host.left + r.width / 2,
                                    r.top - host.top + r.height / 2,
                                    ['#fcd34d', '#fde68a', '#ffffff'],
                                    18,
                                );
                            }
                            send(typed);
                        }}
                    >
                        <label htmlFor="mua-chu-dap-an" className="sr-only">
                            Đáp án
                        </label>
                        <input
                            id="mua-chu-dap-an"
                            autoFocus
                            autoComplete="off"
                            value={typed}
                            onChange={(e) => setTyped(e.target.value)}
                            placeholder="Gõ mặt sau của thẻ rồi Enter"
                            disabled={busy}
                            className="h-12 min-w-0 flex-1 rounded-xl bg-slate-950/50 px-4 text-lg text-white caret-amber-300 outline-none placeholder:text-white/45 focus-visible:ring-2 focus-visible:ring-amber-300/70 disabled:opacity-60"
                        />
                        <Button
                            type="submit"
                            disabled={busy || typed.trim() === ''}
                            className="h-12 rounded-xl bg-amber-400 px-5 text-base font-semibold text-slate-950 hover:bg-amber-300"
                        >
                            <Crosshair aria-hidden /> Bắn
                        </Button>
                    </form>
                </section>
            )}
        </div>
    );
}

function Bubbles({
    card,
    busy,
    onPick,
    burst,
    root,
}: {
    card: NonNullable<Props['card']>;
    busy: boolean;
    onPick: (value: string) => void;
    burst: (x: number, y: number, colors: string[], count?: number) => void;
    root: React.RefObject<HTMLDivElement | null>;
}) {
    const field = useRef<HTMLUListElement>(null);
    const picked = useRef(false);

    // Bong bóng bay lên khi vào và trôi nhẹ không đều, để sân có sức sống mà vẫn dễ bấm trúng.
    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                gsap.from('[data-prompt]', {
                    y: 24,
                    opacity: 0,
                    duration: 0.6,
                    ease: 'power3.out',
                });
                gsap.from('[data-bubble]', {
                    y: 120,
                    scale: 0.4,
                    opacity: 0,
                    duration: 0.9,
                    stagger: 0.09,
                    ease: 'back.out(1.6)',
                });
                gsap.utils
                    .toArray<HTMLElement>('[data-float]')
                    .forEach((el) => {
                        gsap.to(el, {
                            y: gsap.utils.random(-16, 16),
                            x: gsap.utils.random(-10, 10),
                            rotation: gsap.utils.random(-4, 4),
                            duration: gsap.utils.random(2.2, 3.4),
                            ease: 'sine.inOut',
                            yoyo: true,
                            repeat: -1,
                            delay: gsap.utils.random(0, 0.8),
                        });
                    });
            });
        },
        { scope: field },
    );

    const pick = (value: string, index: number, el: HTMLButtonElement) => {
        if (busy || picked.current) {
            return;
        }
        picked.current = true;
        if (!window.matchMedia(MOTION_OK).matches) {
            onPick(value);

            return;
        }
        const host = root.current?.getBoundingClientRect();
        const r = el.getBoundingClientRect();
        const hue = BUBBLE_HUES[index % BUBBLE_HUES.length];
        if (host) {
            burst(
                r.left - host.left + r.width / 2,
                r.top - host.top + r.height / 2,
                [`hsl(${hue} 90% 70%)`, `hsl(${hue} 90% 85%)`, '#ffffff'],
                16,
            );
        }
        const others = field.current?.querySelectorAll('[data-bubble]');
        others?.forEach((o) => {
            if (o !== el) {
                gsap.to(o, { opacity: 0.25, scale: 0.92, duration: 0.25 });
            }
        });
        gsap.timeline({ onComplete: () => onPick(value) })
            .to(el, { scale: 1.12, duration: 0.08, ease: 'power1.out' })
            .to(el, {
                scale: 1.6,
                opacity: 0,
                duration: 0.22,
                ease: 'power2.in',
            });
    };

    // Phím 1–4 bắn bong bóng tương ứng.
    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const n = Number(e.key);
            if (!Number.isInteger(n) || n < 1 || n > card.options.length) {
                return;
            }
            const el =
                field.current?.querySelectorAll<HTMLButtonElement>(
                    '[data-bubble]',
                )[n - 1];
            if (el) {
                pick(card.options[n - 1], n - 1, el);
            }
        };
        window.addEventListener('keydown', onKey);

        return () => window.removeEventListener('keydown', onKey);
    });

    return (
        <section className="grid flex-1 content-center gap-8 md:gap-12">
            <div
                data-prompt
                className="mx-auto grid max-w-xl justify-items-center gap-3 rounded-3xl bg-slate-950/55 px-8 py-6 text-center shadow-[0_24px_60px_-30px_rgb(0_0_0/0.9)] ring-1 ring-white/15 backdrop-blur-md"
            >
                <p className="text-sm text-white/70">
                    Bắn bong bóng mang nghĩa đúng của
                </p>
                {card.image_url && (
                    <img
                        src={card.image_url}
                        alt=""
                        className="max-h-28 rounded-xl object-contain"
                    />
                )}
                <p className="font-display text-4xl font-bold tracking-tight text-balance wrap-anywhere md:text-5xl">
                    {card.front}
                </p>
                {card.speech_lang && (
                    <Button
                        variant="ghost"
                        size="sm"
                        className="text-white/85 hover:bg-white/10 hover:text-white"
                        onClick={() => speak(card.front, card.speech_lang)}
                    >
                        <Volume2 /> Phát âm
                    </Button>
                )}
            </div>
            <ul
                ref={field}
                className="flex flex-wrap items-center justify-center gap-5 md:gap-10"
            >
                {card.options.map((o, i) => (
                    <li key={i} data-float>
                        <button
                            type="button"
                            data-bubble
                            disabled={busy}
                            onClick={(e) => pick(o, i, e.currentTarget)}
                            aria-label={`Lựa chọn ${i + 1}: ${o}`}
                            style={bubbleStyle(
                                BUBBLE_HUES[i % BUBBLE_HUES.length],
                            )}
                            className={cn(
                                'relative grid cursor-crosshair place-items-center rounded-full p-5 text-center font-semibold text-white transition-[filter] outline-none [text-shadow:0_1px_8px_rgb(2_6_23/0.85)] hover:brightness-125 focus-visible:ring-4 focus-visible:ring-amber-300/80 disabled:cursor-wait',
                                BUBBLE_SIZES[i % BUBBLE_SIZES.length],
                            )}
                        >
                            <span className="line-clamp-4 text-base wrap-anywhere md:text-lg">
                                {o}
                            </span>
                            <span
                                aria-hidden
                                className="absolute -top-1 -right-1 grid size-7 place-items-center rounded-full bg-slate-950/80 text-xs font-bold text-white/80 ring-1 ring-white/25"
                            >
                                {i + 1}
                            </span>
                        </button>
                    </li>
                ))}
            </ul>
            <p className="text-center text-xs text-white/55">
                Mẹo: bấm phím 1 đến {card.options.length} để bắn nhanh.
            </p>
        </section>
    );
}

function WordRain({
    card,
    fall,
}: {
    card: NonNullable<Props['card']>;
    fall: number;
}) {
    const seconds = Math.ceil(((1 - fall) * FALL_MS) / 1000);
    const danger = seconds <= 4;
    // Chữ cái của chính thẻ rơi mờ phía sau làm mưa nền (chỉ trang trí).
    const drops = useMemo(() => {
        const letters = Array.from(card.front.replace(/\s+/g, '')).slice(0, 12);
        const src = letters.length > 0 ? letters : ['a'];

        return Array.from({ length: 16 }, (_, i) => ({
            ch: src[i % src.length],
            left: (i * 61) % 100,
            delay: (i * 0.73) % 6,
            dur: 6 + ((i * 1.7) % 5),
            size: 0.9 + ((i * 0.37) % 1.4),
        }));
    }, [card.front]);

    return (
        <div className="relative min-h-80 overflow-hidden rounded-3xl bg-slate-950/40 ring-1 ring-white/10">
            <div aria-hidden className="absolute inset-0">
                {drops.map((d, i) => (
                    <span
                        key={i}
                        className="absolute -top-10 font-display font-bold text-sky-200/15 motion-safe:animate-[rain_linear_infinite] motion-reduce:hidden"
                        style={{
                            left: `${d.left}%`,
                            fontSize: `${d.size}rem`,
                            animationDuration: `${d.dur}s`,
                            animationDelay: `${d.delay}s`,
                        }}
                    >
                        {d.ch}
                    </span>
                ))}
            </div>
            <div
                aria-hidden
                className="absolute inset-x-0 top-0 h-1 origin-left bg-gradient-to-r from-sky-300 to-amber-300"
                style={{ transform: `scaleX(${1 - fall})` }}
            />
            <div
                aria-hidden
                className="absolute inset-x-0 bottom-0 h-32 bg-gradient-to-t from-rose-500/45 to-transparent"
                style={{ opacity: Math.max(0, fall - 0.35) * 1.5 }}
            />
            <div
                aria-hidden
                className="absolute inset-x-0 bottom-0 h-0.5 bg-rose-400 shadow-[0_0_18px_4px_rgb(251_113_133/0.6)]"
            />
            <div
                className={cn(
                    'absolute top-3 right-3 flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-semibold tabular-nums ring-1',
                    danger
                        ? 'bg-rose-500/25 text-rose-100 ring-rose-300/50 motion-safe:animate-pulse'
                        : 'bg-white/10 text-white/85 ring-white/15',
                )}
                aria-live="off"
            >
                <Timer aria-hidden className="size-4" />
                {seconds} giây
            </div>
            <p className="absolute top-3 left-4 text-xs text-white/60">
                Gõ nghĩa trước khi chữ chạm đáy
            </p>
            <div
                data-word
                className="absolute left-1/2 -translate-x-1/2 motion-reduce:top-1/2"
                style={{ top: `calc(${fall * 100}% - ${fall * 3.5}rem)` }}
            >
                <span
                    aria-hidden
                    className="absolute bottom-full left-1/2 h-28 w-px -translate-x-1/2 bg-gradient-to-t from-amber-200/70 to-transparent"
                />
                <span className="block rounded-2xl bg-white px-5 py-2.5 font-display text-2xl font-bold text-slate-900 shadow-[0_0_44px_rgb(252_211_77/0.45),0_18px_30px_-18px_rgb(0_0_0/0.8)] md:text-3xl">
                    {card.front}
                </span>
            </div>
        </div>
    );
}
