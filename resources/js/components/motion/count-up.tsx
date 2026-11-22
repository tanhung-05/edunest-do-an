import { useRef } from 'react';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';

/** Kiểu React Bits CountUp: đếm từ 0 tới giá trị khi phần tử vào khung nhìn. Chữ thật luôn có trong DOM. */
export function CountUp({
    value,
    className,
    delay = 0,
}: {
    value: number;
    className?: string;
    delay?: number;
}) {
    const ref = useRef<HTMLSpanElement>(null);
    const format = (n: number) => Math.round(n).toLocaleString('vi-VN');

    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                const el = ref.current;
                if (!el || value <= 0) {
                    return;
                }
                const state = { n: 0 };
                gsap.to(state, {
                    n: value,
                    duration: 1.4,
                    delay,
                    ease: 'power3.out',
                    scrollTrigger: {
                        trigger: el,
                        start: 'top 90%',
                        once: true,
                    },
                    onUpdate: () => {
                        el.textContent = format(state.n);
                    },
                    onComplete: () => {
                        el.textContent = format(value);
                    },
                });
            });

            return () => mm.revert();
        },
        { scope: ref, dependencies: [value] },
    );

    return (
        <span
            ref={ref}
            className={className}
            style={{ fontVariantNumeric: 'tabular-nums' }}
        >
            {format(value)}
        </span>
    );
}
