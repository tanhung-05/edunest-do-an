import { useGSAP } from '@gsap/react';
import gsap from 'gsap';
import { useRef } from 'react';

gsap.registerPlugin(useGSAP);

export function usePageMotion(pageKey?: string) {
    const scope = useRef<HTMLDivElement>(null);

    useGSAP(
        () => {
            const motion = gsap.matchMedia();

            motion.add(
                '(prefers-reduced-motion: no-preference)',
                () => {
                    gsap.timeline().from('[data-page-enter]', {
                        y: 8,
                        opacity: 0.75,
                        duration: 0.3,
                        stagger: 0.06,
                        ease: 'power2.out',
                        clearProps: 'transform,opacity',
                    });
                },
                scope,
            );

            return () => motion.revert();
        },
        { scope, dependencies: [pageKey], revertOnUpdate: true },
    );

    return scope;
}
