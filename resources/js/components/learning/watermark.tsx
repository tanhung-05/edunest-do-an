import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

const SPOTS = [
    'top-[8%] left-[6%]',
    'top-[10%] right-[8%]',
    'top-[45%] left-[30%]',
    'bottom-[22%] right-[10%]',
    'bottom-[26%] left-[8%]',
];

/**
 * Watermark email học viên trên video (BR-HV-27): đổi vị trí mỗi 30 giây, không chặn thao tác
 * với trình phát. Chỉ làm khó quay lén, không phải DRM.
 */
export function VideoWatermark() {
    const { auth } = usePage().props;
    const [spot, setSpot] = useState(0);

    useEffect(() => {
        const id = window.setInterval(
            () =>
                setSpot(
                    (s) =>
                        (s +
                            1 +
                            Math.floor(Math.random() * (SPOTS.length - 1))) %
                        SPOTS.length,
                ),
            30_000,
        );

        return () => window.clearInterval(id);
    }, []);

    if (!auth.user?.email) {
        return null;
    }

    return (
        <span
            aria-hidden
            data-test="video-watermark"
            className={`pointer-events-none absolute z-10 rounded px-2 py-1 font-mono text-xs text-white/55 transition-all duration-700 select-none [text-shadow:0_1px_2px_rgb(0_0_0/0.6)] ${SPOTS[spot]}`}
        >
            {auth.user.email}
        </span>
    );
}
