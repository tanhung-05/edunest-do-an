import { useRef } from 'react';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';
import { cn } from '@/lib/utils';

/** Vòng tiến độ phẳng (xanh than nền, hổ phách phần đã xong); vẽ dần một lần khi mở trang. */
export function ProgressRing({
    value,
    size = 120,
    label,
    className,
    tone = 'light',
}: {
    value: number;
    size?: number;
    label?: string;
    className?: string;
    tone?: 'light' | 'dark';
}) {
    const ref = useRef<SVGSVGElement>(null);
    const stroke = size * 0.11;
    const radius = (size - stroke) / 2;
    const length = 2 * Math.PI * radius;
    const clamped = Math.max(0, Math.min(100, value));

    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                gsap.from('[data-ring-arc]', {
                    strokeDashoffset: length,
                    duration: 1.2,
                    ease: 'power3.out',
                    delay: 0.15,
                });
            });

            return () => mm.revert();
        },
        { scope: ref, dependencies: [clamped] },
    );

    return (
        <svg
            ref={ref}
            viewBox={`0 0 ${size} ${size}`}
            width={size}
            height={size}
            className={cn('shrink-0', className)}
            role="img"
            aria-label={label ?? `Đã xong ${Math.round(clamped)}%`}
        >
            <circle
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke={tone === 'dark' ? 'rgb(255 255 255 / 0.16)' : '#EEF2F7'}
                strokeWidth={stroke}
            />
            <circle
                data-ring-arc
                cx={size / 2}
                cy={size / 2}
                r={radius}
                fill="none"
                stroke="#F59E0B"
                strokeWidth={stroke}
                strokeDasharray={length}
                strokeDashoffset={length * (1 - clamped / 100)}
                transform={`rotate(-90 ${size / 2} ${size / 2})`}
            />
            <text
                x="50%"
                y="50%"
                dominantBaseline="central"
                textAnchor="middle"
                className="font-extrabold"
                fontSize={size * 0.24}
                fill={tone === 'dark' ? '#FFFFFF' : 'currentColor'}
            >
                {Math.round(clamped)}%
            </text>
        </svg>
    );
}
