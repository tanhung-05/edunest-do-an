import { Link, usePage } from '@inertiajs/react';
import Lenis from 'lenis';
import 'lenis/dist/lenis.css';
import { Menu } from 'lucide-react';
import { useEffect, useState } from 'react';
import { LogoMark, Wordmark } from '@/components/app-logo';
import { ChatWidget, openChat } from '@/components/chat-widget';
import { gsap, ScrollTrigger } from '@/components/motion/gsap';
import {
    Sheet,
    SheetClose,
    SheetContent,
    SheetDescription,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { cn } from '@/lib/utils';
import { dashboard, home, login, register } from '@/routes';
import publicRoutes from '@/routes/public';

/**
 * Layout trang công khai v5: thanh điều hướng dạng viên kính nổi (trong suốt trên ảnh banner
 * trang chủ, chuyển nền trắng khi cuộn), chân trang tối trung tính. Lenis chỉ ở đây.
 */
export default function PublicLayout({
    children,
}: {
    children: React.ReactNode;
}) {
    const {
        props: { auth },
        url,
    } = usePage();
    const [scrolled, setScrolled] = useState(false);
    const path = url.split('?')[0];
    const overPhoto = path === '/' && !scrolled;

    // Lenis chỉ ở layout công khai (skill lenis); GSAP giữ vòng lặp, ScrollTrigger cập nhật theo Lenis.
    useEffect(() => {
        const preference = window.matchMedia(
            '(prefers-reduced-motion: reduce)',
        );
        let stop: (() => void) | undefined;

        function syncMotion() {
            stop?.();
            stop = undefined;

            if (preference.matches) {
                return;
            }

            const lenis = new Lenis({ autoRaf: false, lerp: 0.11 });
            lenis.on('scroll', () => ScrollTrigger.update());
            // GSAP tính theo giây, Lenis nhận mili giây.
            const tick = (seconds: number) => lenis.raf(seconds * 1000);
            gsap.ticker.add(tick);
            gsap.ticker.lagSmoothing(0);
            stop = () => {
                gsap.ticker.remove(tick);
                lenis.destroy();
            };
        }

        syncMotion();
        preference.addEventListener('change', syncMotion);

        return () => {
            preference.removeEventListener('change', syncMotion);
            stop?.();
        };
    }, [url]);

    useEffect(() => {
        const onScroll = () => setScrolled(window.scrollY > 24);
        onScroll();
        window.addEventListener('scroll', onScroll, { passive: true });

        return () => window.removeEventListener('scroll', onScroll);
    }, []);

    const onCatalog = path.startsWith('/courses');
    const onVerify = path.startsWith('/certificates');
    const navLink = (active: boolean) =>
        cn(
            'rounded-full px-4 py-2 transition-colors',
            overPhoto
                ? 'text-white/85 hover:bg-white/10 hover:text-white'
                : 'text-slate-600 hover:bg-slate-100 hover:text-slate-950',
            active &&
                (overPhoto
                    ? 'bg-white/15 text-white'
                    : 'bg-slate-100 text-slate-950'),
        );
    const footLink = 'text-white/65 transition-colors hover:text-white';

    return (
        <div className="flex min-h-svh flex-col bg-background text-foreground">
            <header className="pointer-events-none fixed inset-x-0 top-0 z-40 px-3 pt-3 md:px-6 md:pt-4">
                <div
                    className={cn(
                        'pointer-events-auto mx-auto flex h-14 w-full max-w-6xl items-center justify-between gap-3 rounded-full border pr-2 pl-4 transition-[background-color,border-color,box-shadow] duration-300',
                        overPhoto
                            ? 'border-white/15 bg-white/10 text-white backdrop-blur-md'
                            : 'border-slate-200/80 bg-white/85 text-slate-950 shadow-soft backdrop-blur-md',
                    )}
                >
                    <Link href={home()} className="flex items-center gap-2.5">
                        <LogoMark className="size-8 rounded-lg" />
                        <Wordmark
                            className={cn(
                                '[&>span]:text-amber',
                                overPhoto && 'text-white',
                            )}
                        />
                    </Link>
                    <nav
                        aria-label="Chính"
                        className="hidden items-center gap-1 text-sm font-medium md:flex"
                    >
                        <Link
                            href={publicRoutes.courses.index()}
                            className={navLink(onCatalog)}
                        >
                            Khóa học
                        </Link>
                        <Link
                            href={publicRoutes.bundles.index()}
                            className={navLink(false)}
                        >
                            Combo
                        </Link>
                        <button
                            type="button"
                            onClick={() => openChat('ai')}
                            className={navLink(path.startsWith('/advisor'))}
                        >
                            Tư vấn AI
                        </button>
                        <Link
                            href={publicRoutes.certificates.verify()}
                            className={navLink(onVerify)}
                        >
                            Xác minh chứng nhận
                        </Link>
                    </nav>
                    <div className="flex items-center gap-1 text-sm font-semibold">
                        <MobileMenu
                            overPhoto={overPhoto}
                            signedIn={Boolean(auth?.user)}
                        />
                        {auth?.user ? (
                            <Link
                                href={dashboard()}
                                className="rounded-full bg-slate-950 px-5 py-2.5 text-white transition-transform hover:-translate-y-px active:scale-[0.98]"
                            >
                                Vào trang học
                            </Link>
                        ) : (
                            <>
                                <Link
                                    href={login()}
                                    className={cn(
                                        'hidden rounded-full px-4 py-2.5 transition-colors sm:inline-flex',
                                        overPhoto
                                            ? 'hover:bg-white/10'
                                            : 'hover:bg-slate-100',
                                    )}
                                >
                                    Đăng nhập
                                </Link>
                                <Link
                                    href={register()}
                                    className="rounded-full bg-amber px-5 py-2.5 text-slate-950 transition-transform hover:-translate-y-px active:scale-[0.98]"
                                >
                                    Đăng ký
                                </Link>
                            </>
                        )}
                    </div>
                </div>
            </header>
            <main className={cn('flex-1', path !== '/' && 'pt-24')}>
                {children}
            </main>
            <ChatWidget withAi />
            <footer className="mt-auto bg-slate-950 text-white">
                <div className="mx-auto grid w-full max-w-7xl gap-10 px-4 py-14 md:grid-cols-[1.4fr_1fr_1fr] md:px-8">
                    <div className="grid content-start gap-4">
                        <Link
                            href={home()}
                            className="flex items-center gap-2.5"
                        >
                            <LogoMark className="size-9 rounded-lg" />
                            <Wordmark className="[&>span]:text-amber" />
                        </Link>
                        <p className="max-w-sm text-sm leading-relaxed text-white/60">
                            Khóa học do giáo viên soạn, giáo vụ duyệt trước khi
                            mở bán. Học xong nhận chứng nhận có mã xác minh.
                        </p>
                    </div>
                    <nav
                        aria-label="Khám phá"
                        className="grid content-start gap-2.5 text-sm"
                    >
                        <p className="font-semibold text-white">Khám phá</p>
                        <Link
                            href={publicRoutes.courses.index()}
                            className={footLink}
                        >
                            Danh mục khóa học
                        </Link>
                        <Link
                            href={publicRoutes.certificates.verify()}
                            className={footLink}
                        >
                            Xác minh chứng nhận
                        </Link>
                    </nav>
                    <nav
                        aria-label="Tài khoản"
                        className="grid content-start gap-2.5 text-sm"
                    >
                        <p className="font-semibold text-white">Tài khoản</p>
                        {auth?.user ? (
                            <Link href={dashboard()} className={footLink}>
                                Trang học của tôi
                            </Link>
                        ) : (
                            <>
                                <Link href={login()} className={footLink}>
                                    Đăng nhập
                                </Link>
                                <Link href={register()} className={footLink}>
                                    Tạo tài khoản
                                </Link>
                            </>
                        )}
                    </nav>
                </div>
                <div className="border-t border-white/10">
                    <div className="mx-auto flex w-full max-w-7xl flex-wrap justify-between gap-2 px-4 py-5 text-xs text-white/45 md:px-8">
                        <span>© {new Date().getFullYear()} EduNest</span>
                        <span>Ảnh minh họa: Unsplash</span>
                    </div>
                </div>
            </footer>
        </div>
    );
}

/** Menu trên điện thoại (dưới md): các mục chính, tư vấn AI, đăng nhập (UAT 22/11). */
function MobileMenu({
    overPhoto,
    signedIn,
}: {
    overPhoto: boolean;
    signedIn: boolean;
}) {
    const item =
        'flex items-center rounded-xl px-4 py-3 text-base font-medium text-slate-900 transition-colors hover:bg-slate-100';

    return (
        <Sheet>
            <SheetTrigger asChild>
                <button
                    type="button"
                    aria-label="Mở menu"
                    className={cn(
                        'grid size-10 place-items-center rounded-full transition-colors md:hidden',
                        overPhoto ? 'hover:bg-white/10' : 'hover:bg-slate-100',
                    )}
                >
                    <Menu className="size-5" aria-hidden />
                </button>
            </SheetTrigger>
            <SheetContent side="right" className="w-72 gap-2 p-4 pt-12">
                <SheetTitle className="px-4 text-lg">Menu</SheetTitle>
                <SheetDescription className="sr-only">
                    Các mục chính của EduNest
                </SheetDescription>
                <nav aria-label="Chính trên điện thoại" className="grid gap-1">
                    <SheetClose asChild>
                        <Link
                            href={publicRoutes.courses.index()}
                            className={item}
                        >
                            Khóa học
                        </Link>
                    </SheetClose>
                    <SheetClose asChild>
                        <Link
                            href={publicRoutes.bundles.index()}
                            className={item}
                        >
                            Combo
                        </Link>
                    </SheetClose>
                    <SheetClose asChild>
                        <button
                            type="button"
                            onClick={() => openChat('ai')}
                            className={cn(item, 'text-left')}
                        >
                            Tư vấn AI
                        </button>
                    </SheetClose>
                    <SheetClose asChild>
                        <Link
                            href={publicRoutes.certificates.verify()}
                            className={item}
                        >
                            Xác minh chứng nhận
                        </Link>
                    </SheetClose>
                    {!signedIn && (
                        <SheetClose asChild>
                            <Link
                                href={login()}
                                className={cn(
                                    item,
                                    'mt-2 border border-slate-200',
                                )}
                            >
                                Đăng nhập
                            </Link>
                        </SheetClose>
                    )}
                </nav>
            </SheetContent>
        </Sheet>
    );
}
