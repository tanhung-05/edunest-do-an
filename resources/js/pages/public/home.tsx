import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowRight,
    BadgeCheck,
    CirclePlay,
    FileText,
    ListChecks,
    Lock,
    PlayCircle,
} from 'lucide-react';
import { useRef } from 'react';
import { CountUp } from '@/components/motion/count-up';
import { gsap, MOTION_OK, SplitText, useGSAP } from '@/components/motion/gsap';
import { CourseCard } from '@/components/public/course-card';
import type { PublicCourseCard } from '@/components/public/course-card';
import { CourseSearch } from '@/components/public/course-search';
import { register } from '@/routes';
import publicRoutes from '@/routes/public';

type Props = {
    featured: PublicCourseCard[];
    newest: PublicCourseCard[];
    teachers: { id: number; name: string; photo: string | null }[];
    subjects: { id: number; name: string; count: number; photo: string }[];
    stats: { courses: number; students: number };
    hall_of_fame: {
        title: string;
        published_at: string | null;
        people: { rank: number; name: string; percent: string }[];
    } | null;
};

const STEPS = [
    {
        title: 'Chọn khóa, học thử',
        text: 'Xem đề cương và mở các bài học thử trước khi mua.',
    },
    {
        title: 'Thanh toán, vào học ngay',
        text: 'Thanh toán xong, khóa học có sẵn trong “Khóa của tôi”.',
    },
    {
        title: 'Học video, làm quiz',
        text: 'Hệ thống nhớ chỗ bạn dừng, chấm quiz ngay khi nộp.',
    },
    {
        title: 'Nhận chứng nhận',
        text: 'Hoàn thành khóa, nhận chứng nhận có mã xác minh công khai.',
    },
];

function initials(name: string) {
    return name.trim().split(/\s+/).at(-1)?.[0] ?? '';
}

/** Mảnh giao diện trình học thật, đặt trong khung trình duyệt (khoảnh khắc "trồi lên" khi cuộn). */
function PlayerPreview({ course }: { course: PublicCourseCard | undefined }) {
    const outline = [
        { title: 'Cấu tạo nguyên tử', time: '12:40', state: 'done' },
        { title: 'Bảng tuần hoàn', time: '18:05', state: 'done' },
        { title: 'Liên kết hóa học', time: '21:30', state: 'now' },
        { title: 'Quiz chương 1', time: '10 câu', state: 'quiz' },
        { title: 'Phản ứng oxi hóa khử', time: '16:20', state: 'lock' },
    ];

    return (
        <div className="overflow-hidden rounded-[1.25rem] border border-slate-200 bg-white shadow-[0_40px_120px_-30px_rgba(15,23,42,0.45)]">
            <div className="flex items-center gap-2 border-b border-slate-100 px-4 py-3">
                <span className="size-3 rounded-full bg-slate-200" />
                <span className="size-3 rounded-full bg-slate-200" />
                <span className="size-3 rounded-full bg-slate-200" />
                <span className="ml-3 hidden rounded-full bg-slate-100 px-4 py-1 text-xs text-slate-500 sm:block">
                    edunest.vn/hoc/lien-ket-hoa-hoc
                </span>
            </div>
            <div className="grid gap-0 text-left text-slate-900 lg:grid-cols-[1fr_300px]">
                <div className="grid gap-4 p-4 md:p-5">
                    <div className="relative aspect-video overflow-hidden rounded-xl bg-slate-900">
                        <img
                            src={
                                course?.cover ?? '/images/stock/hoa-pipette.jpg'
                            }
                            alt=""
                            className="h-full w-full object-cover opacity-80"
                        />
                        <span className="absolute inset-0 grid place-items-center">
                            <span className="shadow-lift grid size-16 place-items-center rounded-full bg-white/95 text-slate-950">
                                <PlayCircle className="size-8" />
                            </span>
                        </span>
                        <div className="absolute inset-x-4 bottom-3 h-1.5 overflow-hidden rounded-full bg-white/30">
                            <div className="h-full w-[42%] rounded-full bg-amber" />
                        </div>
                    </div>
                    <div className="flex flex-wrap items-center gap-1 border-b border-slate-100 text-sm">
                        {['Tổng quan', 'Tài liệu', 'Kiểm tra', 'Ghi chú'].map(
                            (tab, index) => (
                                <span
                                    key={tab}
                                    className={
                                        index === 0
                                            ? 'border-b-2 border-slate-950 px-3 pb-2.5 font-semibold'
                                            : 'px-3 pb-2.5 text-slate-500'
                                    }
                                >
                                    {tab}
                                </span>
                            ),
                        )}
                    </div>
                    <div className="hidden gap-2 sm:grid">
                        <p className="font-display text-lg font-semibold">
                            Liên kết hóa học
                        </p>
                        <p className="text-sm text-slate-500">
                            Liên kết ion, cộng hóa trị và cách nhận biết qua độ
                            âm điện.
                        </p>
                    </div>
                </div>
                <div className="hidden border-l border-slate-100 bg-slate-50/70 p-4 lg:block">
                    <p className="text-sm font-semibold">Nội dung khóa học</p>
                    <p className="mt-1 text-xs text-slate-500">2/5 bài xong</p>
                    <ul className="mt-4 grid gap-1.5 text-sm">
                        {outline.map((item) => (
                            <li
                                key={item.title}
                                className={
                                    item.state === 'now'
                                        ? 'flex items-center gap-2.5 rounded-lg bg-white px-2.5 py-2 font-medium ring-1 shadow-soft ring-slate-200'
                                        : 'flex items-center gap-2.5 px-2.5 py-2 text-slate-600'
                                }
                            >
                                {item.state === 'done' && (
                                    <BadgeCheck className="size-4 text-emerald-600" />
                                )}
                                {item.state === 'now' && (
                                    <CirclePlay className="size-4 text-amber-600" />
                                )}
                                {item.state === 'quiz' && (
                                    <ListChecks className="size-4 text-slate-400" />
                                )}
                                {item.state === 'lock' && (
                                    <Lock className="size-4 text-slate-300" />
                                )}
                                <span className="min-w-0 flex-1 truncate">
                                    {item.title}
                                </span>
                                <span className="text-xs text-slate-400">
                                    {item.time}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </div>
    );
}

export default function Home({
    featured,
    newest,
    teachers,
    subjects,
    stats,
    hall_of_fame,
}: Props) {
    const scope = useRef<HTMLDivElement>(null);

    // Một khoảnh khắc mở trang (ảnh nền thu dần, tiêu đề hiện theo từ) và một khoảnh khắc
    // cuộn (khung trình học trồi lên, ảnh nền trôi chậm). Tắt hết khi giảm chuyển động.
    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                const title = scope.current?.querySelector('[data-hero-title]');
                const split = title
                    ? SplitText.create(title, {
                          type: 'words',
                          mask: 'words',
                          wordsClass: 'split-word',
                          aria: 'auto',
                      })
                    : null;
                const intro = gsap.timeline({
                    defaults: { ease: 'expo.out' },
                });
                intro
                    .from('[data-hero-photo]', {
                        scale: 1.18,
                        duration: 2.4,
                        ease: 'power3.out',
                    })
                    .from(
                        split?.words ?? [],
                        { yPercent: 115, duration: 1, stagger: 0.07 },
                        0.25,
                    )
                    .from(
                        '[data-hero-fade]',
                        {
                            y: 24,
                            opacity: 0,
                            duration: 0.9,
                            stagger: 0.1,
                            clearProps: 'opacity,transform',
                        },
                        0.6,
                    );

                gsap.to('[data-hero-photo]', {
                    yPercent: 14,
                    ease: 'none',
                    scrollTrigger: {
                        trigger: '[data-hero]',
                        start: 'top top',
                        end: 'bottom top',
                        scrub: true,
                    },
                });
                gsap.fromTo(
                    '[data-rise]',
                    { y: 140, scale: 0.9, rotateX: 14 },
                    {
                        y: 0,
                        scale: 1,
                        rotateX: 0,
                        ease: 'none',
                        scrollTrigger: {
                            trigger: '[data-rise]',
                            start: 'top bottom',
                            end: 'top 35%',
                            scrub: 0.6,
                        },
                    },
                );

                return () => split?.revert();
            });

            return () => mm.revert();
        },
        { scope },
    );

    const searchSubmit = (event: React.FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        const value = new FormData(event.currentTarget).get('q');
        const query = typeof value === 'string' ? value.trim() : '';
        router.visit(
            publicRoutes.courses.index({ query: { q: query || undefined } }),
        );
    };

    // Bố cục ô ảnh: một ô lớn + bốn ô nhỏ khi đủ năm môn, còn lại lưới đều.
    const bento = subjects.length >= 5;
    const tiles = bento ? subjects.slice(0, 5) : subjects;

    return (
        <div ref={scope}>
            <Head title="Học trực tuyến cùng giáo viên giỏi" />

            <section
                data-hero
                className="relative isolate flex min-h-[min(100svh,880px)] items-center overflow-hidden bg-slate-950 pt-28 pb-56 text-white md:pb-72"
            >
                <img
                    data-hero-photo
                    src="/images/stock/hero-giang-duong.jpg"
                    alt=""
                    fetchPriority="high"
                    className="absolute inset-0 -z-20 h-full w-full origin-center object-cover"
                />
                <div className="absolute inset-0 -z-10 bg-slate-950/60" />
                <div className="absolute inset-x-0 bottom-0 -z-10 h-48 bg-gradient-to-t from-background to-transparent" />
                <div className="mx-auto flex w-full max-w-5xl flex-col items-center gap-7 px-4 text-center md:px-8">
                    <p
                        data-hero-fade
                        className="rounded-full border border-white/20 bg-white/10 px-4 py-1.5 text-sm text-white/90 backdrop-blur"
                    >
                        <strong className="font-semibold text-white">
                            <CountUp value={stats.courses} />
                        </strong>{' '}
                        khóa đang mở,{' '}
                        <strong className="font-semibold text-white">
                            <CountUp value={stats.students} />
                        </strong>{' '}
                        học viên đang học
                    </p>
                    <h1
                        data-hero-title
                        className="max-w-4xl font-display text-[clamp(2.6rem,6.4vw,5.4rem)] leading-[1.06] font-bold tracking-tight text-balance"
                    >
                        Học cùng giáo viên giỏi, tiến bộ thấy rõ mỗi tuần
                    </h1>
                    <p
                        data-hero-fade
                        className="max-w-2xl text-lg text-white/80 md:text-xl"
                    >
                        Video bài giảng, quiz chấm ngay và chứng nhận có mã xác
                        minh. Mọi khóa đều được giáo vụ duyệt trước khi mở bán.
                    </p>
                    <form
                        data-hero-fade
                        action={publicRoutes.courses.index.url()}
                        onSubmit={searchSubmit}
                        className="w-full max-w-2xl"
                    >
                        <CourseSearch variant="hero" />
                    </form>
                    {subjects.length > 0 && (
                        <ul
                            data-hero-fade
                            aria-label="Môn học"
                            className="flex flex-wrap justify-center gap-2"
                        >
                            {subjects.map((subject) => (
                                <li key={subject.id}>
                                    <Link
                                        href={publicRoutes.courses.index({
                                            query: { category: subject.id },
                                        })}
                                        className="inline-flex rounded-full border border-white/20 px-3.5 py-1.5 text-sm text-white/85 transition-colors hover:bg-white hover:text-slate-950"
                                    >
                                        {subject.name}
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
            </section>

            <div className="relative z-10 mx-auto -mt-44 w-full max-w-6xl px-4 [perspective:1600px] md:-mt-60 md:px-8">
                <div data-rise className="origin-top will-change-transform">
                    <PlayerPreview course={featured[0]} />
                </div>
            </div>

            {subjects.length > 0 && (
                <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                        <h2 className="max-w-xl font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                            Chọn môn bạn cần học
                        </h2>
                        <Link
                            href={publicRoutes.courses.index()}
                            className="group inline-flex items-center gap-2 text-sm font-semibold"
                        >
                            Toàn bộ danh mục
                            <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                        </Link>
                    </div>
                    <ul
                        className={
                            bento
                                ? 'grid auto-rows-[200px] gap-4 sm:grid-cols-2 lg:auto-rows-[220px] lg:grid-cols-4'
                                : 'grid auto-rows-[240px] gap-4 sm:grid-cols-2 lg:grid-cols-3'
                        }
                    >
                        {tiles.map((subject, index) => (
                            <li
                                key={subject.id}
                                className={
                                    bento && index === 0
                                        ? 'sm:col-span-2 sm:row-span-2'
                                        : ''
                                }
                            >
                                <Link
                                    href={publicRoutes.courses.index({
                                        query: { category: subject.id },
                                    })}
                                    className="group relative flex h-full photo-zoom overflow-hidden rounded-2xl bg-slate-900 text-white"
                                >
                                    <img
                                        src={subject.photo}
                                        alt=""
                                        loading="lazy"
                                        className="absolute inset-0 h-full w-full object-cover"
                                    />
                                    <span className="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/20 to-transparent" />
                                    <span className="relative mt-auto flex w-full items-end justify-between gap-3 p-5">
                                        <span>
                                            <span
                                                className={
                                                    bento && index === 0
                                                        ? 'block font-display text-3xl font-bold'
                                                        : 'block font-display text-xl font-bold'
                                                }
                                            >
                                                {subject.name}
                                            </span>
                                            <span className="text-sm text-white/75">
                                                {subject.count} khóa đang mở
                                            </span>
                                        </span>
                                        <span className="grid size-10 shrink-0 place-items-center rounded-full bg-white/15 backdrop-blur transition-colors group-hover:bg-white group-hover:text-slate-950">
                                            <ArrowRight className="size-4" />
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                <div className="mb-8 flex flex-wrap items-end justify-between gap-4">
                    <h2 className="font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                        Khóa học nổi bật
                    </h2>
                    <Link
                        href={publicRoutes.courses.index()}
                        className="group inline-flex items-center gap-2 text-sm font-semibold"
                    >
                        Xem tất cả khóa học
                        <ArrowRight className="size-4 transition-transform group-hover:translate-x-1" />
                    </Link>
                </div>
                {featured.length ? (
                    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {featured.map((course) => (
                            <CourseCard key={course.id} course={course} />
                        ))}
                    </ul>
                ) : (
                    <p className="text-muted-foreground">
                        Chưa có khóa đang bán.
                    </p>
                )}
            </section>

            <section className="mx-auto grid w-full max-w-7xl items-center gap-10 px-4 pt-24 md:px-8 md:pt-32 lg:grid-cols-2 lg:gap-16">
                <div className="relative aspect-[4/5] photo-zoom overflow-hidden rounded-3xl bg-slate-200 sm:aspect-[4/3] lg:aspect-[4/5]">
                    <img
                        src="/images/stock/hero-lop.jpg"
                        alt="Ba học viên cùng học trên máy tính xách tay"
                        loading="lazy"
                        className="h-full w-full object-cover"
                    />
                    <div className="shadow-lift absolute bottom-5 left-5 flex items-center gap-3 rounded-2xl bg-white/95 p-3 pr-5 text-slate-950 backdrop-blur">
                        <span className="grid size-10 place-items-center rounded-xl bg-emerald-100 text-emerald-700">
                            <BadgeCheck className="size-5" />
                        </span>
                        <span className="text-sm">
                            <strong className="block font-semibold">
                                Đạt quiz chương 3
                            </strong>
                            <span className="text-slate-500">
                                9/10 câu đúng
                            </span>
                        </span>
                    </div>
                </div>
                <div>
                    <h2 className="font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                        Từ lúc chọn khóa tới khi nhận chứng nhận
                    </h2>
                    <ol className="mt-10 grid gap-2">
                        {STEPS.map((step, index) => (
                            <li
                                key={step.title}
                                className="grid grid-cols-[3rem_1fr] gap-4 rounded-2xl p-4 transition-colors hover:bg-card hover:shadow-soft"
                            >
                                <span className="grid size-12 place-items-center rounded-full border border-border font-display text-lg font-bold">
                                    {index + 1}
                                </span>
                                <span className="pt-1">
                                    <strong className="block text-lg font-semibold">
                                        {step.title}
                                    </strong>
                                    <span className="text-muted-foreground">
                                        {step.text}
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </div>
            </section>

            {teachers.length > 0 && (
                <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                    <h2 className="mb-8 font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                        Giáo viên đang dạy
                    </h2>
                    <ul className="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-4">
                        {teachers.map((t, index) => (
                            <li key={t.id}>
                                <Link
                                    href={publicRoutes.teachers.show(t.id)}
                                    className="group flex h-full lift items-center gap-4 rounded-2xl border border-border bg-card p-4 shadow-soft"
                                >
                                    {t.photo ? (
                                        <img
                                            src={t.photo}
                                            alt=""
                                            className="size-14 shrink-0 rounded-full object-cover"
                                        />
                                    ) : (
                                        <span
                                            aria-hidden
                                            className={
                                                [
                                                    'bg-sky-100 text-sky-800',
                                                    'bg-amber-100 text-amber-800',
                                                    'bg-emerald-100 text-emerald-800',
                                                    'bg-rose-100 text-rose-800',
                                                ][index % 4] +
                                                ' grid size-14 shrink-0 place-items-center rounded-full font-display text-xl font-bold'
                                            }
                                        >
                                            {initials(t.name)}
                                        </span>
                                    )}
                                    <span className="min-w-0">
                                        <span className="block font-semibold wrap-anywhere">
                                            {t.name}
                                        </span>
                                        <span className="text-sm text-muted-foreground group-hover:text-foreground">
                                            Xem hồ sơ
                                        </span>
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            {hall_of_fame && (
                <section
                    aria-labelledby="vinh-danh"
                    className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32"
                >
                    <div className="mb-8 flex flex-wrap items-end justify-between gap-3">
                        <h2
                            id="vinh-danh"
                            className="font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight"
                        >
                            Vinh danh thi thử
                        </h2>
                        <p className="text-muted-foreground">
                            {hall_of_fame.title}
                        </p>
                    </div>
                    <ol className="grid grid-cols-[repeat(auto-fill,minmax(15rem,1fr))] gap-3">
                        {hall_of_fame.people.map((p) => (
                            <li
                                key={`${p.rank}-${p.name}`}
                                className="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 shadow-soft"
                            >
                                <span
                                    className={
                                        (p.rank === 1
                                            ? 'bg-amber-100 text-amber-900'
                                            : 'bg-muted text-foreground') +
                                        ' grid size-10 shrink-0 place-items-center rounded-full font-display text-lg font-bold tabular-nums'
                                    }
                                >
                                    {p.rank}
                                </span>
                                <span className="min-w-0">
                                    <span className="block font-semibold wrap-anywhere">
                                        {p.name}
                                    </span>
                                    <span className="text-sm text-muted-foreground tabular-nums">
                                        {p.percent}%
                                    </span>
                                </span>
                            </li>
                        ))}
                    </ol>
                </section>
            )}

            {newest.length > 0 && (
                <section className="mx-auto w-full max-w-7xl px-4 pt-24 md:px-8 md:pt-32">
                    <h2 className="mb-8 font-display text-[clamp(2rem,3.6vw,3rem)] leading-tight font-bold tracking-tight">
                        Mới mở bán
                    </h2>
                    <ul className="grid gap-3 md:grid-cols-2">
                        {newest.slice(0, 6).map((course) => (
                            <li key={course.id}>
                                <Link
                                    href={publicRoutes.courses.show(
                                        course.slug,
                                    )}
                                    className="group hover:shadow-lift flex items-center gap-4 rounded-2xl border border-border bg-card p-3 pr-5 transition-shadow"
                                >
                                    <span className="size-20 shrink-0 photo-zoom overflow-hidden rounded-xl bg-muted">
                                        {course.cover && (
                                            <img
                                                src={course.cover}
                                                alt=""
                                                loading="lazy"
                                                className="h-full w-full object-cover"
                                            />
                                        )}
                                    </span>
                                    <span className="min-w-0 flex-1">
                                        <span className="block font-semibold wrap-anywhere">
                                            {course.title}
                                        </span>
                                        <span className="text-sm text-muted-foreground">
                                            {[course.category, course.teacher]
                                                .filter(Boolean)
                                                .join(', ')}
                                        </span>
                                    </span>
                                    <span className="font-semibold tabular-nums">
                                        {course.price.toLocaleString('vi-VN')}đ
                                    </span>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </section>
            )}

            <section className="mx-auto w-full max-w-7xl px-4 pt-24 pb-24 md:px-8 md:pt-32 md:pb-32">
                <div className="relative isolate flex min-h-[420px] w-full items-end overflow-hidden rounded-3xl bg-slate-950 p-8 text-white md:p-14">
                    <img
                        src="/images/stock/thu-vien.jpg"
                        alt=""
                        loading="lazy"
                        className="absolute inset-0 -z-20 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 -z-10 bg-slate-950/65" />
                    <div className="grid max-w-2xl gap-6">
                        <h2 className="font-display text-[clamp(2.2rem,4.4vw,3.6rem)] leading-[1.05] font-bold tracking-tight">
                            Bắt đầu với một bài học thử miễn phí
                        </h2>
                        <p className="text-lg text-white/75">
                            Tạo tài khoản để lưu tiến độ, mua khóa và nhận chứng
                            nhận khi hoàn thành.
                        </p>
                        <div className="flex flex-wrap gap-3">
                            <Link
                                href={register()}
                                className="inline-flex h-12 items-center rounded-full bg-amber px-7 font-semibold text-slate-950 transition-transform hover:-translate-y-0.5 active:scale-[0.98]"
                            >
                                Tạo tài khoản
                            </Link>
                            <Link
                                href={publicRoutes.courses.index()}
                                className="inline-flex h-12 items-center gap-2 rounded-full border border-white/30 px-7 font-semibold transition-colors hover:bg-white hover:text-slate-950"
                            >
                                <FileText className="size-4" />
                                Xem đề cương các khóa
                            </Link>
                        </div>
                    </div>
                </div>
            </section>
        </div>
    );
}
