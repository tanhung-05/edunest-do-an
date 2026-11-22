import { Link, usePage } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import {
    Bot,
    Headset,
    LoaderCircle,
    MessageCircle,
    Phone,
    SendHorizontal,
    Sparkles,
    UserRound,
    X,
} from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';
import { CourseCover } from '@/components/public/course-card';
import { getJson, newRequestKey, sendJson } from '@/lib/http';
import { cn } from '@/lib/utils';
import supportRoutes from '@/routes/account/support';
import publicRoutes from '@/routes/public';

type CourseCard = {
    id?: number;
    title: string;
    url: string;
    cover?: string | null;
    price?: number;
    original_price?: number | null;
    teacher?: string | null;
    level?: string | null;
    category?: string | null;
};

type AiMessage = {
    id: number;
    role: 'USER' | 'AI';
    content: string;
    status: 'OK' | 'LOI';
    courses?: CourseCard[];
};

type AiState = {
    enabled: boolean;
    hotline: string;
    messages: AiMessage[];
};

type SupportMessage = {
    id: number;
    from_staff: boolean;
    sender: string;
    body: string;
    at: string;
};

type SupportState = {
    thread_id: number | null;
    status: 'CHO' | 'DA_TRA_LOI' | 'DONG' | null;
    unread: number;
    is_agent: boolean;
    is_guest: boolean;
    guest: { name: string | null; phone: string | null } | null;
    hotline: string;
    messages: SupportMessage[];
};

type Tab = 'ai' | 'staff';

const QUICK_PROMPTS = [
    'Xin chào, mình cần tư vấn khóa học',
    'Con tôi lớp 9, muốn ôn thi vào lớp 10',
    'Em lớp 12 muốn luyện đề thi THPT',
    'Khóa nào phù hợp người mất gốc?',
];

export const OPEN_CHAT_EVENT = 'edunest:open-chat';

/** Mở hộp chat từ nơi khác (vd. liên kết "Tư vấn AI" trên thanh điều hướng). */
export function openChat(tab: Tab = 'ai') {
    window.dispatchEvent(
        new CustomEvent<Tab>(OPEN_CHAT_EVENT, { detail: tab }),
    );
}

// Trang học bài, làm bài, phòng thi, chơi game có khung riêng; ẩn nút nổi để không che điều khiển.
const HIDDEN_ON = /\/(lessons|attempts|play|sessions|room|games)(\/|$)/;

function time(iso: string): string {
    return new Date(iso).toLocaleTimeString('vi-VN', {
        hour: '2-digit',
        minute: '2-digit',
        timeZone: 'Asia/Ho_Chi_Minh',
    });
}

function telHref(hotline: string): string {
    return `tel:${hotline.replace(/[^\d+]/g, '')}`;
}

/**
 * P10: hộp chat nổi góc phải. Trang công khai (`withAi`) có hai thẻ: "Tư vấn AI" (CR-257, không giới hạn lượt, hiện thẻ
 * khóa học) và "Nhân viên" (CR-258, Khách nhắn được khi để lại họ tên và số điện thoại, có hotline). Vùng ứng dụng chỉ có
 * thẻ "Nhân viên". Giáo vụ không thấy hộp này, họ trả lời ở trang "Chat hỗ trợ".
 */
export function ChatWidget({
    withAi = false,
    aboveDock = false,
}: {
    withAi?: boolean;
    aboveDock?: boolean;
}) {
    const page = usePage();
    const user = page.props.auth.user;
    const path = page.url.split('?')[0];
    const isAgent = (user?.effective_roles ?? []).includes('GIAO_VU');
    const [open, setOpen] = useState(false);
    const [tab, setTab] = useState<Tab>(withAi ? 'ai' : 'staff');
    const [unread, setUnread] = useState(0);

    // Số tin nhân viên chưa đọc cho nút nổi (chỉ khi đã có cuộc trò chuyện trong phiên này).
    useEffect(() => {
        if (isAgent) {
            return;
        }

        getJson<SupportState>(supportRoutes.state.url())
            .then((s) => setUnread(s.unread))
            .catch(() => undefined);
    }, [isAgent, user?.id]);

    useEffect(() => {
        const onOpen = (e: Event) => {
            const wanted = (e as CustomEvent<Tab>).detail ?? 'ai';
            setTab(withAi ? wanted : 'staff');
            setOpen(true);
        };
        window.addEventListener(OPEN_CHAT_EVENT, onOpen);

        return () => window.removeEventListener(OPEN_CHAT_EVENT, onOpen);
    }, [withAi]);

    if (isAgent || HIDDEN_ON.test(path)) {
        return null;
    }

    const active: Tab = withAi ? tab : 'staff';

    return (
        <div
            className={cn(
                'pointer-events-none fixed right-4 z-40 flex flex-col items-end gap-3 sm:right-6',
                aboveDock ? 'bottom-24 md:bottom-6' : 'bottom-4 sm:bottom-6',
            )}
        >
            {open && (
                <section
                    aria-label="Hộp chat tư vấn và hỗ trợ"
                    className="pointer-events-auto flex h-[min(38rem,calc(100dvh-7rem))] w-[min(25rem,calc(100vw-2rem))] flex-col overflow-hidden rounded-3xl border bg-background shadow-[0_24px_64px_-16px_rgb(15_23_42/0.35)]"
                >
                    <header className="relative overflow-hidden bg-slate-950 px-4 pt-4 pb-3 text-white">
                        <div
                            aria-hidden
                            className="pointer-events-none absolute -top-16 -right-10 size-40 rounded-full bg-amber-400/25 blur-2xl"
                        />
                        <div className="relative flex items-start justify-between gap-3">
                            <div className="flex items-center gap-3">
                                <span className="grid size-10 place-items-center rounded-2xl bg-white/10 ring-1 ring-white/15">
                                    {active === 'ai' ? (
                                        <Sparkles
                                            className="size-5 text-amber-300"
                                            aria-hidden
                                        />
                                    ) : (
                                        <Headset
                                            className="size-5 text-amber-300"
                                            aria-hidden
                                        />
                                    )}
                                </span>
                                <div>
                                    <p className="font-semibold">
                                        {active === 'ai'
                                            ? 'Trợ lý tư vấn EduNest'
                                            : 'Hỗ trợ trực tiếp'}
                                    </p>
                                    <p className="flex items-center gap-1.5 text-xs text-white/70">
                                        <span className="size-1.5 rounded-full bg-emerald-400" />
                                        {active === 'ai'
                                            ? 'Trả lời ngay, gợi ý khóa phù hợp'
                                            : 'Nhân viên trả lời 8:00 đến 21:00'}
                                    </p>
                                </div>
                            </div>
                            <button
                                type="button"
                                onClick={() => setOpen(false)}
                                aria-label="Đóng hộp chat"
                                className="grid size-9 place-items-center rounded-xl text-white/70 hover:bg-white/10 hover:text-white"
                            >
                                <X className="size-4" aria-hidden />
                            </button>
                        </div>
                        {withAi && (
                            <div
                                role="tablist"
                                aria-label="Chọn kênh hỗ trợ"
                                className="relative mt-3 grid grid-cols-2 gap-1 rounded-xl bg-white/10 p-1 text-sm"
                            >
                                <TabButton
                                    active={active === 'ai'}
                                    onClick={() => setTab('ai')}
                                >
                                    <Bot className="size-4" aria-hidden />
                                    Tư vấn AI
                                </TabButton>
                                <TabButton
                                    active={active === 'staff'}
                                    onClick={() => setTab('staff')}
                                >
                                    <Headset className="size-4" aria-hidden />
                                    Nhân viên
                                    {unread > 0 && active !== 'staff' && (
                                        <span className="rounded-full bg-destructive px-1.5 text-[10px] leading-4 font-bold text-white">
                                            {unread}
                                        </span>
                                    )}
                                </TabButton>
                            </div>
                        )}
                    </header>
                    {active === 'ai' ? (
                        <AiPanel onAskStaff={() => setTab('staff')} />
                    ) : (
                        <StaffPanel
                            signedIn={Boolean(user)}
                            onRead={() => setUnread(0)}
                        />
                    )}
                </section>
            )}
            <button
                type="button"
                onClick={() => setOpen((v) => !v)}
                aria-label={
                    open
                        ? 'Đóng hộp chat'
                        : unread > 0
                          ? `Mở hộp chat, ${unread} tin nhân viên chưa đọc`
                          : withAi
                            ? 'Mở hộp chat tư vấn và hỗ trợ'
                            : 'Mở chat với nhân viên hỗ trợ'
                }
                aria-expanded={open}
                className="group pointer-events-auto relative flex h-14 items-center gap-2 rounded-full bg-slate-950 pr-5 pl-4 text-white shadow-xl ring-4 ring-white/70 transition-transform hover:-translate-y-0.5 dark:ring-white/10"
            >
                {open ? (
                    <X className="size-6" aria-hidden />
                ) : (
                    <MessageCircle className="size-6" aria-hidden />
                )}
                <span className="hidden text-sm font-semibold sm:inline">
                    {open ? 'Đóng' : withAi ? 'Tư vấn ngay' : 'Hỗ trợ'}
                </span>
                {!open && unread > 0 && (
                    <span
                        aria-hidden
                        className="absolute -top-1 -right-1 grid min-w-5 place-items-center rounded-full bg-destructive px-1 text-[11px] leading-5 font-bold"
                    >
                        {unread > 9 ? '9+' : unread}
                    </span>
                )}
            </button>
        </div>
    );
}

function TabButton({
    active,
    onClick,
    children,
}: {
    active: boolean;
    onClick: () => void;
    children: React.ReactNode;
}) {
    return (
        <button
            type="button"
            role="tab"
            aria-selected={active}
            onClick={onClick}
            className={cn(
                'flex min-h-9 items-center justify-center gap-1.5 rounded-lg px-3 font-medium transition-colors',
                active
                    ? 'bg-white text-slate-950 shadow-sm'
                    : 'text-white/75 hover:text-white',
            )}
        >
            {children}
        </button>
    );
}

function useAutoScroll(dep: unknown) {
    const ref = useRef<HTMLOListElement>(null);
    useEffect(() => {
        ref.current?.scrollTo({ top: ref.current.scrollHeight });
    }, [dep]);

    return ref;
}

function AiPanel({ onAskStaff }: { onAskStaff: () => void }) {
    const [state, setState] = useState<AiState | null>(null);
    const [draft, setDraft] = useState('');
    const [pending, setPending] = useState<string | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [requestKey, setRequestKey] = useState(newRequestKey);
    const list = useAutoScroll([state?.messages.length, pending]);

    useEffect(() => {
        getJson<AiState>(publicRoutes.advisor.state.url())
            .then(setState)
            .catch(() => setError('Không tải được trợ lý AI.'));
    }, []);

    async function ask(text: string) {
        const question = text.trim();

        if (!question || pending) {
            return;
        }

        setPending(question);
        setError(null);

        try {
            const next = await sendJson<AiState>(
                publicRoutes.advisor.ask.url(),
                { question, request_key: requestKey },
            );
            setState(next);
            setDraft('');
            setRequestKey(newRequestKey());
        } catch (err) {
            // Giữ khóa yêu cầu: gửi lại không tạo lượt hỏi trùng (CR-170).
            setError(err instanceof Error ? err.message : 'Không gửi được.');
        } finally {
            setPending(null);
        }
    }

    const empty = state !== null && state.messages.length === 0;

    return (
        <div className="flex min-h-0 flex-1 flex-col bg-muted/30">
            <ol
                ref={list}
                data-lenis-prevent
                aria-label="Hội thoại tư vấn AI"
                aria-live="polite"
                className="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto overscroll-contain p-4"
            >
                <Bubble mine={false}>
                    <p>
                        Chào bạn, mình là trợ lý tư vấn của EduNest. Bạn (hoặc
                        phụ huynh) cho mình biết môn, lớp và mục tiêu, mình gợi
                        ý ngay các khóa đang mở bán phù hợp.
                    </p>
                </Bubble>
                {empty && (
                    <li className="grid gap-2">
                        <p className="text-xs font-medium text-muted-foreground">
                            Gợi ý câu hỏi
                        </p>
                        <div className="flex flex-wrap gap-2">
                            {QUICK_PROMPTS.map((q) => (
                                <button
                                    key={q}
                                    type="button"
                                    onClick={() => void ask(q)}
                                    className="rounded-full border bg-background px-3 py-1.5 text-left text-xs font-medium transition-colors hover:border-slate-950"
                                >
                                    {q}
                                </button>
                            ))}
                        </div>
                    </li>
                )}
                {state === null && !error && (
                    <li className="flex items-center gap-2 text-sm text-muted-foreground">
                        <LoaderCircle
                            className="size-4 animate-spin"
                            aria-hidden
                        />
                        Đang tải
                    </li>
                )}
                {state?.messages.map((m) => (
                    <AiItem key={m.id} message={m} />
                ))}
                {pending && (
                    <>
                        <Bubble mine>
                            <p className="wrap-anywhere whitespace-pre-line">
                                {pending}
                            </p>
                        </Bubble>
                        <li className="flex items-center gap-2 text-sm text-muted-foreground">
                            <LoaderCircle
                                className="size-4 animate-spin"
                                aria-hidden
                            />
                            Trợ lý đang tìm khóa phù hợp
                        </li>
                    </>
                )}
            </ol>
            <form
                onSubmit={(e) => {
                    e.preventDefault();
                    void ask(draft);
                }}
                className="grid gap-2 border-t bg-background p-3"
            >
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {state !== null && !state.enabled ? (
                    <p className="rounded-xl bg-muted p-3 text-sm text-muted-foreground">
                        Trợ lý AI đang tạm nghỉ. Bạn nhắn nhân viên hoặc gọi
                        hotline nhé.
                    </p>
                ) : (
                    <Composer
                        value={draft}
                        onChange={setDraft}
                        busy={pending !== null}
                        maxLength={1000}
                        label="Câu hỏi cho trợ lý AI"
                        placeholder="Ví dụ: Con tôi lớp 11 học yếu Hóa"
                    />
                )}
                <div className="flex flex-wrap items-center justify-between gap-2 text-[11px] text-muted-foreground">
                    <span>AI có thể nhầm, hãy xem kỹ trang khóa học.</span>
                    <button
                        type="button"
                        onClick={onAskStaff}
                        className="font-semibold text-foreground underline underline-offset-4"
                    >
                        Gặp nhân viên
                    </button>
                </div>
            </form>
        </div>
    );
}

function AiItem({ message: m }: { message: AiMessage }) {
    const mine = m.role === 'USER';

    return (
        <>
            <Bubble mine={mine} error={m.status === 'LOI'}>
                <p className="wrap-anywhere whitespace-pre-line">{m.content}</p>
            </Bubble>
            {!mine && m.courses && m.courses.length > 0 && (
                <li className="grid gap-2">
                    {m.courses.map((c) => (
                        <ChatCourseCard key={c.url} course={c} />
                    ))}
                </li>
            )}
        </>
    );
}

function ChatCourseCard({ course: c }: { course: CourseCard }) {
    return (
        <Link
            href={c.url}
            className="group grid grid-cols-[5.5rem_1fr] gap-3 overflow-hidden rounded-2xl border bg-background p-2 transition-shadow hover:shadow-md"
        >
            {c.id !== undefined ? (
                <CourseCover
                    course={{ id: c.id, cover: c.cover ?? null }}
                    className="aspect-square w-full rounded-xl"
                />
            ) : (
                <span className="grid aspect-square place-items-center rounded-xl bg-muted">
                    <Bot className="size-6 text-muted-foreground" aria-hidden />
                </span>
            )}
            <span className="grid min-w-0 content-center gap-1">
                <span className="line-clamp-2 text-sm leading-snug font-semibold group-hover:underline">
                    {c.title}
                </span>
                {(c.category || c.level) && (
                    <span className="truncate text-xs text-muted-foreground">
                        {[c.category, c.level].filter(Boolean).join(' · ')}
                    </span>
                )}
                {c.teacher && (
                    <span className="flex items-center gap-1 truncate text-xs text-muted-foreground">
                        <UserRound className="size-3" aria-hidden />
                        {c.teacher}
                    </span>
                )}
                {c.price !== undefined && (
                    <span className="flex items-baseline gap-2 text-sm">
                        <span className="font-bold tabular-nums">
                            {c.price.toLocaleString('vi-VN')}đ
                        </span>
                        {c.original_price ? (
                            <s className="text-xs text-muted-foreground tabular-nums">
                                {c.original_price.toLocaleString('vi-VN')}đ
                            </s>
                        ) : null}
                    </span>
                )}
            </span>
        </Link>
    );
}

function StaffPanel({
    signedIn,
    onRead,
}: {
    signedIn: boolean;
    onRead: () => void;
}) {
    const [state, setState] = useState<SupportState | null>(null);
    const [draft, setDraft] = useState('');
    const [name, setName] = useState('');
    const [phone, setPhone] = useState('');
    const [busy, setBusy] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const [requestKey, setRequestKey] = useState(newRequestKey);
    const list = useAutoScroll(state?.messages.length);

    const refresh = useCallback(async () => {
        try {
            const next = await getJson<SupportState>(supportRoutes.state.url());
            setState(next);

            if (next.guest) {
                setName((v) => v || next.guest?.name || '');
                setPhone((v) => v || next.guest?.phone || '');
            }

            if (next.unread > 0) {
                await sendJson(supportRoutes.read.url(), {});
            }

            onRead();
        } catch {
            // Mất mạng tạm thời: lần thăm dò sau thử lại.
        }
    }, [onRead]);

    // Khách không có kênh riêng: thăm dò 5 giây khi đang mở; người đăng nhập 15 giây, có Reverb thì nhận ngay.
    useEffect(() => {
        void refresh();
        const id = window.setInterval(
            () => {
                if (!document.hidden) {
                    void refresh();
                }
            },
            signedIn ? 15_000 : 5_000,
        );

        return () => window.clearInterval(id);
    }, [refresh, signedIn]);

    const threadId = state?.thread_id ?? null;
    useEffect(() => {
        if (!signedIn || threadId === null || !echoIsConfigured()) {
            return;
        }

        const channel = `support.thread.${threadId}`;
        echo()
            .private(channel)
            .listen('.support.message', () => void refresh());

        return () => echo().leave(channel);
    }, [signedIn, threadId, refresh]);

    async function send(e: React.FormEvent) {
        e.preventDefault();
        const body = draft.trim();

        if (!body || busy) {
            return;
        }

        setBusy(true);
        setError(null);

        try {
            const next = await sendJson<SupportState>(
                supportRoutes.send.url(),
                {
                    body,
                    request_key: requestKey,
                    ...(signedIn
                        ? {}
                        : { guest_name: name, guest_phone: phone }),
                },
            );
            setState(next);
            setDraft('');
            setRequestKey(newRequestKey());
        } catch (err) {
            setError(err instanceof Error ? err.message : 'Không gửi được.');
        } finally {
            setBusy(false);
        }
    }

    const hotline = state?.hotline ?? '';
    const needContact = !signedIn && !state?.guest?.phone;

    return (
        <div className="flex min-h-0 flex-1 flex-col bg-muted/30">
            {hotline && (
                <a
                    href={telHref(hotline)}
                    className="flex items-center justify-between gap-3 border-b bg-amber-50 px-4 py-2.5 text-sm dark:bg-amber-950/30"
                >
                    <span className="flex items-center gap-2">
                        <span className="grid size-8 place-items-center rounded-full bg-amber-400 text-slate-950">
                            <Phone className="size-4" aria-hidden />
                        </span>
                        <span>
                            <span className="block text-xs text-muted-foreground">
                                Hotline tư vấn (8:00 đến 21:00)
                            </span>
                            <span className="font-bold tracking-wide tabular-nums">
                                {hotline}
                            </span>
                        </span>
                    </span>
                    <span className="text-xs font-semibold underline underline-offset-4">
                        Gọi ngay
                    </span>
                </a>
            )}
            <ol
                ref={list}
                data-lenis-prevent
                aria-label="Trò chuyện với nhân viên"
                aria-live="polite"
                className="grid min-h-0 flex-1 content-start gap-3 overflow-y-auto overscroll-contain p-4"
            >
                <Bubble mine={false}>
                    <p>
                        Phụ huynh và học viên cần tư vấn chi tiết về lộ trình,
                        học phí, đơn hàng hay tài khoản cứ nhắn tại đây. Nhân
                        viên trả lời ngay trong khung này
                        {signedIn
                            ? '.'
                            : ' và gọi lại theo số điện thoại bạn để lại.'}
                    </p>
                </Bubble>
                {state?.messages.map((m) => (
                    <Bubble key={m.id} mine={!m.from_staff}>
                        {m.from_staff && (
                            <span className="text-xs font-semibold text-muted-foreground">
                                {m.sender}
                            </span>
                        )}
                        <p className="wrap-anywhere whitespace-pre-line">
                            {m.body}
                        </p>
                        <span
                            className={cn(
                                'text-[10px]',
                                m.from_staff
                                    ? 'text-muted-foreground'
                                    : 'text-white/70',
                            )}
                        >
                            {time(m.at)}
                        </span>
                    </Bubble>
                ))}
                {state?.status === 'CHO' && state.messages.length > 0 && (
                    <li className="text-center text-xs text-muted-foreground">
                        Đã gửi. Nhân viên sẽ trả lời sớm.
                    </li>
                )}
                {state?.status === 'DONG' && (
                    <li className="text-center text-xs text-muted-foreground">
                        Cuộc trò chuyện đã đóng. Nhắn tin mới để mở lại.
                    </li>
                )}
            </ol>
            <form
                onSubmit={send}
                className="grid gap-2 border-t bg-background p-3"
            >
                {error && (
                    <p role="alert" className="text-sm text-destructive">
                        {error}
                    </p>
                )}
                {!signedIn && (
                    <div
                        className={cn(
                            'grid grid-cols-2 gap-2',
                            !needContact && 'hidden',
                        )}
                    >
                        <input
                            value={name}
                            onChange={(e) => setName(e.target.value)}
                            maxLength={80}
                            autoComplete="name"
                            aria-label="Họ tên của bạn"
                            placeholder="Họ tên"
                            className="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm"
                        />
                        <input
                            value={phone}
                            onChange={(e) => setPhone(e.target.value)}
                            inputMode="tel"
                            autoComplete="tel"
                            maxLength={20}
                            aria-label="Số điện thoại để nhân viên gọi lại"
                            placeholder="Số điện thoại"
                            className="h-10 min-w-0 rounded-lg border bg-background px-3 text-sm"
                        />
                    </div>
                )}
                <Composer
                    value={draft}
                    onChange={setDraft}
                    busy={busy}
                    maxLength={2000}
                    label="Tin nhắn cho nhân viên"
                    placeholder="Nhập câu hỏi cần tư vấn"
                />
                <p className="text-[11px] leading-4 text-muted-foreground">
                    Không gửi mật khẩu hay mã OTP. Nhân viên EduNest không bao
                    giờ hỏi các thông tin này.
                </p>
            </form>
        </div>
    );
}

function Bubble({
    mine,
    error = false,
    children,
}: {
    mine: boolean;
    error?: boolean;
    children: React.ReactNode;
}) {
    return (
        <li
            className={cn(
                'grid max-w-[85%] gap-1 rounded-2xl px-3.5 py-2.5 text-sm leading-relaxed shadow-sm',
                mine
                    ? 'justify-self-end rounded-br-md bg-slate-950 text-white'
                    : 'justify-self-start rounded-bl-md border bg-background',
                error && 'border-destructive/40 text-destructive',
            )}
        >
            {children}
        </li>
    );
}

function Composer({
    value,
    onChange,
    busy,
    maxLength,
    label,
    placeholder,
}: {
    value: string;
    onChange: (v: string) => void;
    busy: boolean;
    maxLength: number;
    label: string;
    placeholder: string;
}) {
    return (
        <div className="flex items-end gap-2 rounded-2xl border bg-background p-1.5 focus-within:ring-2 focus-within:ring-slate-950/20">
            <textarea
                value={value}
                onChange={(e) => onChange(e.target.value)}
                onKeyDown={(e) => {
                    if (e.key === 'Enter' && !e.shiftKey) {
                        e.preventDefault();
                        e.currentTarget.form?.requestSubmit();
                    }
                }}
                required
                maxLength={maxLength}
                rows={1}
                aria-label={label}
                placeholder={placeholder}
                className="max-h-28 min-h-9 flex-1 resize-none bg-transparent px-2 py-1.5 text-sm outline-none"
            />
            <button
                type="submit"
                disabled={busy || value.trim() === ''}
                aria-label="Gửi"
                className="grid size-9 shrink-0 place-items-center rounded-xl bg-slate-950 text-white transition-opacity disabled:opacity-30"
            >
                {busy ? (
                    <LoaderCircle className="size-4 animate-spin" aria-hidden />
                ) : (
                    <SendHorizontal className="size-4" aria-hidden />
                )}
            </button>
        </div>
    );
}
