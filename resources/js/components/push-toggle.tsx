import { BellRing } from 'lucide-react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { HttpError, sendJson } from '@/lib/http';

type State = 'checking' | 'unsupported' | 'denied' | 'off' | 'on';

function keyBytes(base64url: string): Uint8Array<ArrayBuffer> {
    const pad = '='.repeat((4 - (base64url.length % 4)) % 4);
    const raw = atob((base64url + pad).replace(/-/g, '+').replace(/_/g, '/'));
    const out = new Uint8Array(new ArrayBuffer(raw.length));

    for (let i = 0; i < raw.length; i++) {
        out[i] = raw.charCodeAt(i);
    }

    return out;
}

async function registration(): Promise<ServiceWorkerRegistration> {
    return (
        (await navigator.serviceWorker.getRegistration('/sw.js')) ??
        (await navigator.serviceWorker.register('/sw.js'))
    );
}

/**
 * CR-262: bật hoặc tắt thông báo đẩy cho trình duyệt hiện tại. Thông báo chuông mới sẽ hiện cả khi đã đóng tab
 * (trình duyệt vẫn chạy). Cần localhost hoặc HTTPS.
 */
export function PushToggle({ publicKey }: { publicKey: string }) {
    const [state, setState] = useState<State>('checking');
    const [busy, setBusy] = useState(false);
    const [message, setMessage] = useState<string | null>(null);

    useEffect(() => {
        if (
            !('serviceWorker' in navigator) ||
            !('PushManager' in window) ||
            !window.isSecureContext
        ) {
            setState('unsupported');

            return;
        }

        if (Notification.permission === 'denied') {
            setState('denied');

            return;
        }

        void registration()
            .then((reg) => reg.pushManager.getSubscription())
            .then((sub) => setState(sub ? 'on' : 'off'))
            .catch(() => setState('off'));
    }, []);

    const enable = async () => {
        setBusy(true);
        setMessage(null);

        try {
            const permission = await Notification.requestPermission();

            if (permission !== 'granted') {
                setState(permission === 'denied' ? 'denied' : 'off');

                return;
            }

            const reg = await registration();
            const sub =
                (await reg.pushManager.getSubscription()) ??
                (await reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: keyBytes(publicKey),
                }));
            const res = await sendJson<{ status: string }>(
                '/account/push-subscriptions',
                sub.toJSON(),
            );
            setMessage(res.status);
            setState('on');
        } catch (e) {
            setMessage(
                e instanceof HttpError
                    ? e.message
                    : 'Không bật được thông báo đẩy trên trình duyệt này.',
            );
        } finally {
            setBusy(false);
        }
    };

    const disable = async () => {
        setBusy(true);
        setMessage(null);

        try {
            const reg = await registration();
            const sub = await reg.pushManager.getSubscription();

            if (sub) {
                await sendJson('/account/push-subscriptions/delete', {
                    endpoint: sub.endpoint,
                });
                await sub.unsubscribe();
            }

            setMessage('Đã tắt thông báo đẩy trên trình duyệt này.');
            setState('off');
        } catch {
            setMessage('Không tắt được, hãy thử lại.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <div className="grid gap-2 border-b border-border pb-4">
            <h2 className="flex items-center gap-2 font-semibold">
                <BellRing className="size-4" aria-hidden />
                Thông báo đẩy
            </h2>
            <p className="text-sm text-muted-foreground">
                {state === 'unsupported'
                    ? 'Trình duyệt này chưa hỗ trợ thông báo đẩy (cần HTTPS hoặc localhost).'
                    : state === 'denied'
                      ? 'Bạn đã chặn thông báo cho trang này. Mở biểu tượng ổ khóa trên thanh địa chỉ để cho phép lại.'
                      : state === 'on'
                        ? 'Đang bật trên trình duyệt này: thông báo mới hiện cả khi bạn không mở EduNest.'
                        : 'Nhận thông báo mới ngay trên máy, cả khi bạn không mở EduNest.'}
            </p>
            {(state === 'off' || state === 'on') && (
                <Button
                    size="sm"
                    variant={state === 'on' ? 'outline' : 'default'}
                    className="justify-self-start"
                    disabled={busy}
                    onClick={() => void (state === 'on' ? disable() : enable())}
                >
                    {state === 'on'
                        ? 'Tắt trên trình duyệt này'
                        : 'Bật thông báo đẩy'}
                </Button>
            )}
            {message && (
                <p role="status" className="text-sm">
                    {message}
                </p>
            )}
        </div>
    );
}
