import { Link, router, usePage } from '@inertiajs/react';
import { echo, echoIsConfigured } from '@laravel/echo-react';
import { Bell } from 'lucide-react';
import { useEffect, useState } from 'react';
import { toast } from 'sonner';
import {
    Tooltip,
    TooltipContent,
    TooltipTrigger,
} from '@/components/ui/tooltip';
import { cn } from '@/lib/utils';
import notifications from '@/routes/account/notifications';

const POLL_MS = 30_000;

type Pushed = { title?: string; body?: string };

/**
 * Chuông thông báo (M15). Có Reverb thì nghe kênh riêng của người dùng: số chưa đọc cập nhật ngay và hiện
 * một dòng báo nhanh. Vẫn thăm dò mỗi 30 giây (bỏ qua khi tab ẩn) làm dự phòng khi Reverb chưa chạy hoặc mất kết nối.
 */
export function NotificationBell() {
    const { notifications: shared, auth } = usePage().props;
    const [unread, setUnread] = useState(shared?.unread ?? 0);

    useEffect(() => {
        setUnread(shared?.unread ?? 0);
    }, [shared?.unread]);

    useEffect(() => {
        if (!auth.user) {
            return;
        }

        let stopped = false;
        const tick = async () => {
            if (document.hidden) {
                return;
            }

            try {
                const res = await fetch(notifications.unread().url, {
                    headers: { Accept: 'application/json' },
                    credentials: 'same-origin',
                });

                if (res.ok && !stopped) {
                    const body = (await res.json()) as { count: number };
                    setUnread(body.count);
                }
            } catch {
                // Mất mạng tạm thời: giữ số cũ, lần sau thử lại.
            }
        };
        const id = window.setInterval(tick, POLL_MS);
        const off = router.on('navigate', () => void tick());
        const channel = `App.Models.User.${auth.user.id}`;
        const live = echoIsConfigured();

        if (live) {
            echo()
                .private(channel)
                .notification((n: Pushed) => {
                    void tick();

                    if (n.title) {
                        toast(n.title, { description: n.body });
                    }
                });
        }

        return () => {
            stopped = true;
            window.clearInterval(id);
            off();

            if (live) {
                echo().leave(channel);
            }
        };
    }, [auth.user]);

    if (!auth.user) {
        return null;
    }

    const label = unread > 0 ? `Thông báo, ${unread} chưa đọc` : 'Thông báo';

    return (
        <Tooltip>
            <TooltipTrigger asChild>
                <Link
                    href={notifications.index()}
                    aria-label={label}
                    className="relative grid size-11 place-items-center rounded-xl text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
                >
                    <Bell className="size-[1.2rem]" />
                    {unread > 0 && (
                        <span
                            aria-hidden
                            className={cn(
                                'absolute top-1.5 right-1.5 grid min-w-4 place-items-center rounded-full bg-destructive px-1 text-[10px] leading-4 font-bold text-white tabular-nums',
                            )}
                        >
                            {unread > 99 ? '99+' : unread}
                        </span>
                    )}
                </Link>
            </TooltipTrigger>
            <TooltipContent side="right" sideOffset={10}>
                {label}
            </TooltipContent>
        </Tooltip>
    );
}
