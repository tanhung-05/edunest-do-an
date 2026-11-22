import { Link } from '@inertiajs/react';
import { CircleCheckBig } from 'lucide-react';
import { LogoMark, Wordmark } from '@/components/app-logo';
import { home } from '@/routes';
import type { AuthLayoutProps } from '@/types';

const POINTS = [
    'Học tiếp đúng chỗ đã dừng trên mọi thiết bị',
    'Quiz chấm ngay, xem lời giải sau khi đạt',
    'Chứng nhận có mã xác minh công khai',
];

/** Trang tài khoản (đăng nhập, đăng ký…) v5: biểu mẫu bên trái, ảnh lớp học thật bên phải. */
export default function AuthSimpleLayout({
    children,
    title,
    description,
}: AuthLayoutProps) {
    return (
        <div className="grid min-h-svh bg-background lg:grid-cols-[1fr_1.05fr]">
            <div className="flex flex-col px-6 py-8 md:px-12">
                <Link
                    href={home()}
                    className="flex items-center gap-2.5 self-start"
                >
                    <LogoMark className="size-9 rounded-lg" />
                    <Wordmark />
                </Link>
                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center gap-8 py-10">
                    <div className="space-y-2">
                        <h1 className="font-display text-3xl font-bold tracking-tight">
                            {title}
                        </h1>
                        {description && (
                            <p className="text-sm text-muted-foreground">
                                {description}
                            </p>
                        )}
                    </div>
                    {children}
                </div>
            </div>
            <aside
                aria-hidden
                className="relative isolate m-3 hidden overflow-hidden rounded-3xl bg-slate-950 text-white lg:block"
            >
                <img
                    src="/images/stock/hero-lop.jpg"
                    alt=""
                    className="absolute inset-0 -z-20 h-full w-full object-cover"
                />
                <div className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/90 via-slate-950/30 to-transparent" />
                <div className="absolute inset-x-10 bottom-10 grid gap-5">
                    <p className="max-w-md font-display text-3xl leading-tight font-bold tracking-tight">
                        Học có lộ trình, có giáo viên đồng hành.
                    </p>
                    <ul className="grid gap-2.5 text-sm text-white/85">
                        {POINTS.map((point) => (
                            <li
                                key={point}
                                className="flex items-center gap-2.5"
                            >
                                <CircleCheckBig className="size-4 shrink-0 text-amber" />
                                {point}
                            </li>
                        ))}
                    </ul>
                </div>
            </aside>
        </div>
    );
}
