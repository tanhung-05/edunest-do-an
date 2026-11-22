import { useEffect, useRef, useState } from 'react';
import { gsap, REDUCED } from '@/components/motion/gsap';

/** Kiểu React Bits RotatingText: lần lượt đổi từ trong một khối hổ phách. Trình đọc màn hình đọc cả danh sách. */
export function RotatingText({
    words,
    interval = 2200,
    className,
}: {
    words: string[];
    interval?: number;
    className?: string;
}) {
    const [index, setIndex] = useState(0);
    const ref = useRef<HTMLSpanElement>(null);

    useEffect(() => {
        if (words.length < 2 || window.matchMedia(REDUCED).matches) {
            return;
        }
        const id = window.setInterval(() => {
            const el = ref.current;
            if (!el) {
                return;
            }
            gsap.to(el, {
                yPercent: -110,
                opacity: 0,
                duration: 0.28,
                ease: 'power2.in',
                onComplete: () => {
                    setIndex((i) => (i + 1) % words.length);
                    gsap.fromTo(
                        el,
                        { yPercent: 110, opacity: 0 },
                        {
                            yPercent: 0,
                            opacity: 1,
                            duration: 0.42,
                            ease: 'back.out(1.6)',
                        },
                    );
                },
            });
        }, interval);

        return () => window.clearInterval(id);
    }, [words, interval]);

    return (
        <span className={className}>
            <span className="sr-only">{words.join(', ')}</span>
            <span
                aria-hidden
                className="inline-block overflow-hidden align-bottom"
            >
                <span ref={ref} className="inline-block">
                    {words[index]}
                </span>
            </span>
        </span>
    );
}
