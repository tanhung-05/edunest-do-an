import { useRef } from 'react';
import { cn } from '@/lib/utils';
import { gsap, MOTION_OK, SplitText, useGSAP } from '@/components/motion/gsap';

/**
 * Kiểu React Bits SplitText: tiêu đề hiện theo từng từ (một khoảnh khắc mở trang).
 * Dùng plugin SplitText của GSAP, có aria nên trình đọc màn hình đọc nguyên câu.
 */
export function SplitHeading({
    as: Tag = 'h1',
    children,
    className,
    delay = 0.1,
}: {
    as?: 'h1' | 'h2' | 'p';
    children: string;
    className?: string;
    delay?: number;
}) {
    const ref = useRef<HTMLHeadingElement & HTMLParagraphElement>(null);

    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                if (!ref.current) {
                    return;
                }
                const split = SplitText.create(ref.current, {
                    type: 'words',
                    mask: 'words',
                    wordsClass: 'split-word',
                    aria: 'auto',
                });
                gsap.from(split.words, {
                    yPercent: 110,
                    duration: 0.8,
                    ease: 'expo.out',
                    stagger: 0.06,
                    delay,
                });

                return () => split.revert();
            });

            return () => mm.revert();
        },
        { scope: ref, dependencies: [children] },
    );

    return (
        <Tag ref={ref} className={cn(className)}>
            {children}
        </Tag>
    );
}
