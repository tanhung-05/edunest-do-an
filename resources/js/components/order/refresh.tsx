import { router, usePoll } from '@inertiajs/react';
import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';

const pollInterval = 5_000;

export function useOrderPolling(active: boolean, only: string[]) {
    const { start, stop } = usePoll(
        pollInterval,
        { only },
        { autoStart: false },
    );

    useEffect(() => {
        if (active) {
            start();
        }
        return stop;
    }, [active, start, stop]);
}

export function OrderReload({
    only,
    label,
    failure,
    onSuccess,
}: {
    only: string[];
    label: string;
    failure: string;
    onSuccess?: () => void;
}) {
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    function reload() {
        setLoading(true);
        setFailed(false);
        function onFailure() {
            setFailed(true);
            return false;
        }

        router.reload({
            only,
            onFinish: () => setLoading(false),
            onSuccess,
            onNetworkError: onFailure,
            onHttpException: onFailure,
        });
    }

    return (
        <div className="grid justify-items-start gap-2">
            <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={reload}
                disabled={loading}
            >
                {loading ? 'Đang tải…' : label}
            </Button>
            {failed && (
                <p role="alert" className="text-sm text-destructive">
                    {failure}
                </p>
            )}
        </div>
    );
}

export function OrderCountdown({ expiresAt }: { expiresAt: string }) {
    const [seconds, setSeconds] = useState<number | null>(null);

    useEffect(() => {
        const deadline = Date.parse(expiresAt);
        let refreshed = false;
        function tick() {
            const remaining = deadline - Date.now();
            setSeconds(Math.max(0, Math.ceil(remaining / 1_000)));
            if (remaining < 0 && !refreshed) {
                refreshed = true;
                router.reload({ only: ['order'] });
            }
        }

        const initial = window.setTimeout(tick, 0);
        const timer = window.setInterval(tick, 1_000);
        return () => {
            window.clearTimeout(initial);
            window.clearInterval(timer);
        };
    }, [expiresAt]);

    if (seconds === null) {
        return null;
    }

    const minutes = Math.floor(seconds / 60);
    const remainingSeconds = String(seconds % 60).padStart(2, '0');

    return seconds === 0 ? (
        <p role="status" className="text-sm">
            Đang kiểm tra thời hạn giữ đơn…
        </p>
    ) : (
        <p role="timer" className="text-sm">
            Thời gian giữ đơn còn lại{' '}
            <strong className="tabular-nums">
                {minutes}:{remainingSeconds}
            </strong>
        </p>
    );
}
