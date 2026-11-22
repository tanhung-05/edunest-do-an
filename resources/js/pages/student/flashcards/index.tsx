import { Head, Link, router } from '@inertiajs/react';
import { CloudRain, Crosshair, Layers, Lightbulb, Repeat } from 'lucide-react';
import type { LucideIcon } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';

type Props = {
    sets: {
        id: number;
        title: string;
        description: string | null;
        course: string;
        cards: number;
        due: number;
        active: boolean;
        review_url: string;
        game_url: string;
    }[];
    due_total: number;
    suggested: {
        id: number;
        front: string;
        back: string;
        review_url: string;
    }[];
    games: {
        id: number;
        kind: string;
        status: string;
        ended_at: string | null;
        url: string;
    }[];
};

/** MH-HV-19: bộ thẻ theo khóa, hàng "Thẻ cần ôn hôm nay" và mini game (QT-24). */
export default function FlashcardIndex({
    sets,
    due_total,
    suggested,
    games,
}: Props) {
    const play = (url: string, kind: 'BAN_BONG' | 'MUA_CHU') =>
        router.post(url, { kind });

    return (
        <Page
            title="Thẻ ghi nhớ"
            description="Ôn theo lịch lặp lại ngắt quãng: tự chấm Quên, Khó, Nhớ hoặc Dễ. Mini game không đổi lịch ôn."
            stats={[
                {
                    label: 'Thẻ cần ôn hôm nay',
                    value: due_total,
                    icon: Repeat,
                    attention: due_total > 0,
                },
                { label: 'Bộ thẻ', value: sets.length, icon: Layers },
                {
                    label: 'Gợi ý ôn từ game',
                    value: suggested.length,
                    icon: Lightbulb,
                },
            ]}
        >
            <Head title="Thẻ ghi nhớ" />
            <div className="grid gap-5">
                {sets.map((s) => (
                    <Block
                        key={s.id}
                        className="grid gap-5 lg:grid-cols-[minmax(0,1fr)_minmax(0,1.5fr)] lg:items-center"
                    >
                        <div className="grid gap-3">
                            <div>
                                <p className="text-xs text-muted-foreground">
                                    {s.course}
                                </p>
                                <h2 className="text-xl font-semibold tracking-tight wrap-anywhere">
                                    {s.title}
                                </h2>
                                {s.description && (
                                    <p className="mt-1 text-sm text-muted-foreground">
                                        {s.description}
                                    </p>
                                )}
                            </div>
                            <div className="grid gap-1.5">
                                <p className="flex items-baseline justify-between text-sm">
                                    <span>
                                        <b className="tabular-nums">{s.due}</b>{' '}
                                        thẻ cần ôn hôm nay
                                    </span>
                                    <span className="text-muted-foreground tabular-nums">
                                        {s.cards} thẻ
                                    </span>
                                </p>
                                <div
                                    className="h-2 overflow-hidden rounded-full bg-muted"
                                    aria-hidden
                                >
                                    <div
                                        className="h-full rounded-full bg-amber"
                                        style={{
                                            width: `${s.cards > 0 ? (100 * s.due) / s.cards : 0}%`,
                                        }}
                                    />
                                </div>
                            </div>
                            {s.active ? (
                                <Button
                                    asChild
                                    disabled={s.due === 0}
                                    className="justify-self-start"
                                >
                                    <Link href={s.review_url}>
                                        <Layers />
                                        {s.due > 0
                                            ? `Ôn ${s.due} thẻ đến hạn`
                                            : 'Xem bộ thẻ'}
                                    </Link>
                                </Button>
                            ) : (
                                <p className="text-sm text-destructive">
                                    Ghi danh đã hết hạn: không ôn thẻ hoặc chơi
                                    game được.
                                </p>
                            )}
                        </div>
                        {s.active && (
                            <div className="grid gap-3 sm:grid-cols-2">
                                <GameTile
                                    title="Bắn bong bóng"
                                    hint="Chọn bong bóng mang nghĩa đúng, phím 1 đến 4 để bắn nhanh."
                                    photo="doc-sach-ngoai-troi"
                                    icon={Crosshair}
                                    disabled={s.cards < 2}
                                    onPlay={() => play(s.game_url, 'BAN_BONG')}
                                    art={<BubbleArt />}
                                />
                                <GameTile
                                    title="Mưa chữ"
                                    hint="Gõ nghĩa của từ đang rơi trước khi nó chạm đáy."
                                    photo="hoc-dem"
                                    icon={CloudRain}
                                    disabled={s.cards < 1}
                                    onPlay={() => play(s.game_url, 'MUA_CHU')}
                                    art={<RainArt />}
                                />
                            </div>
                        )}
                    </Block>
                ))}
                {sets.length === 0 && (
                    <Block>
                        <p className="text-muted-foreground">
                            Khóa bạn đang học chưa có bộ thẻ.
                        </p>
                    </Block>
                )}
            </div>
            <div className="grid items-start gap-5 lg:grid-cols-2">
                {suggested.length > 0 && (
                    <Block className="grid gap-2">
                        <h2 className="text-lg font-semibold">
                            Gợi ý ôn thêm (trả lời sai trong game hôm nay)
                        </h2>
                        <p className="text-xs text-muted-foreground">
                            Danh sách tạm, không đổi lịch ôn. Mở bộ thẻ để tự
                            chấm nếu muốn.
                        </p>
                        <ul className="grid gap-2 sm:grid-cols-2">
                            {suggested.map((c) => (
                                <li key={c.id}>
                                    <Link
                                        href={c.review_url}
                                        className="block rounded-lg border border-border px-3 py-2 hover:bg-accent/50"
                                    >
                                        <span className="font-medium">
                                            {c.front}
                                        </span>{' '}
                                        <span className="text-muted-foreground">
                                            · {c.back}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Block>
                )}
                {games.length > 0 && (
                    <Block className="p-0 md:p-0">
                        <h2 className="px-5 pt-5 text-lg font-semibold md:px-6">
                            Lượt chơi gần đây
                        </h2>
                        <ul className="mt-2 divide-y divide-border">
                            {games.map((g) => (
                                <li key={g.id}>
                                    <Link
                                        href={g.url}
                                        className="flex gap-4 px-5 py-2.5 text-sm hover:bg-accent/50 md:px-6"
                                    >
                                        <span className="flex-1">{g.kind}</span>
                                        <span>{g.status}</span>
                                        <span className="text-muted-foreground">
                                            {vnTime(g.ended_at)}
                                        </span>
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    </Block>
                )}
            </div>
        </Page>
    );
}

function GameTile({
    title,
    hint,
    photo,
    icon: Icon,
    disabled,
    onPlay,
    art,
}: {
    title: string;
    hint: string;
    photo: string;
    icon: LucideIcon;
    disabled: boolean;
    onPlay: () => void;
    art: React.ReactNode;
}) {
    return (
        <button
            type="button"
            disabled={disabled}
            onClick={onPlay}
            className="group relative isolate grid min-h-44 content-end overflow-hidden rounded-2xl bg-slate-950 p-4 text-left text-white shadow-[0_18px_36px_-22px_rgb(15_23_42/0.8)] transition-transform outline-none hover:-translate-y-0.5 focus-visible:ring-4 focus-visible:ring-amber/60 disabled:cursor-not-allowed disabled:opacity-50"
        >
            <img
                src={`/images/stock/${photo}.jpg`}
                alt=""
                aria-hidden
                className="absolute inset-0 -z-20 h-full w-full object-cover opacity-45 transition-transform duration-700 group-hover:scale-105"
            />
            <div
                aria-hidden
                className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950 via-slate-950/60 to-slate-950/10"
            />
            <div
                aria-hidden
                className="absolute inset-x-0 top-0 h-24 overflow-hidden"
            >
                {art}
            </div>
            <span className="flex items-center gap-2 font-display text-lg font-bold">
                <Icon aria-hidden className="size-5 text-amber-300" />
                {title}
            </span>
            <span className="mt-1 text-sm text-white/75">{hint}</span>
            <span className="mt-3 inline-flex w-fit items-center rounded-full bg-amber-400 px-3 py-1 text-sm font-semibold text-slate-950 transition-colors group-hover:bg-amber-300">
                Chơi ngay
            </span>
        </button>
    );
}

/** Ba bong bóng nhỏ trôi trên ô game (trang trí). */
function BubbleArt() {
    return (
        <>
            {[
                { left: '12%', top: '22%', size: 34, hue: 199, delay: '0s' },
                { left: '44%', top: '10%', size: 46, hue: 38, delay: '0.6s' },
                { left: '74%', top: '30%', size: 30, hue: 168, delay: '1.1s' },
            ].map((b, i) => (
                <span
                    key={i}
                    className="absolute rounded-full motion-safe:animate-[bob_3s_ease-in-out_infinite]"
                    style={{
                        left: b.left,
                        top: b.top,
                        width: b.size,
                        height: b.size,
                        animationDelay: b.delay,
                        backgroundImage: `radial-gradient(circle at 30% 28%, rgb(255 255 255 / 0.9) 0 6%, transparent 30%), radial-gradient(circle, hsl(${b.hue} 85% 60% / 0.15) 52%, hsl(${b.hue} 90% 75% / 0.8) 100%)`,
                    }}
                />
            ))}
        </>
    );
}

/** Vài chữ cái rơi trên ô Mưa chữ (trang trí). */
function RainArt() {
    return (
        <>
            {['A', 'h', 'ó', 'e', 'x'].map((ch, i) => (
                <span
                    key={i}
                    className="absolute -top-6 font-display text-lg font-bold text-sky-200/60 motion-safe:animate-[rain_linear_infinite]"
                    style={{
                        left: `${12 + i * 18}%`,
                        animationDuration: `${3.5 + i * 0.6}s`,
                        animationDelay: `${i * 0.5}s`,
                    }}
                >
                    {ch}
                </span>
            ))}
        </>
    );
}
