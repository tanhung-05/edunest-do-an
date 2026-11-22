import { Head, Link } from '@inertiajs/react';
import { SplitHeading } from '@/components/motion/split-heading';
import { CourseCover } from '@/components/public/course-card';
import {
    CommerceButtons,
    PromoPrice,
    RatingsSection,
} from '@/components/public/course-commerce';
import type {
    CommerceCta,
    Pricing,
    Ratings,
} from '@/components/public/course-commerce';
import publicRoutes from '@/routes/public';

type Props = {
    course: {
        id: number;
        title: string;
        slug: string;
        cover: string | null;
        category: string | null;
        level: string | null;
        summary: string | null;
        objectives: string | null;
        description: string | null;
        price: number;
        original_price: number | null;
        access_months: number | null;
        sale_status: 'DANG_BAN' | 'NGUNG_BAN';
        sale_status_label: string;
        lesson_count: number;
        total_minutes: number | null;
        student_count: number;
    };
    teacher: { id: number; name: string; bio: string | null } | null;
    outline: {
        id: number;
        title: string;
        lessons: {
            id: number;
            number: number;
            title: string;
            type_label: string;
            minutes: number | null;
            is_preview: boolean;
            preview_url: string | null;
        }[];
    }[];
    cta: {
        buy: boolean;
        label: string | null;
        url: string | null;
        validity: 'CON_HAN' | 'HET_HAN' | 'DA_THU_HOI' | null;
        login_url: string | null;
    } & CommerceCta;
    pricing: Pricing;
    ratings: Ratings;
};

function lastInitial(name: string) {
    return name.trim().split(/\s+/).at(-1)?.[0] ?? '';
}

function Check() {
    return (
        <svg viewBox="0 0 20 20" className="mt-0.5 size-5 shrink-0" aria-hidden>
            <rect width="20" height="20" rx="10" fill="#10B981" />
            <path
                d="M5.5 10.5 8.5 13.5 14.5 6.5"
                fill="none"
                stroke="#FFFFFF"
                strokeWidth="2.2"
            />
        </svg>
    );
}

function PriceCard({
    course,
    cta,
    pricing,
}: Pick<Props, 'course' | 'cta' | 'pricing'>) {
    return (
        <div className="flex flex-col gap-5 p-6">
            <div className="font-display text-3xl font-bold tracking-tight">
                <PromoPrice pricing={pricing} />
            </div>
            <dl className="grid gap-2 text-sm">
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Thời hạn truy cập</dt>
                    <dd className="font-semibold">
                        {course.access_months === null
                            ? 'Vĩnh viễn'
                            : `${course.access_months} tháng`}
                    </dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Bài học</dt>
                    <dd className="font-semibold">
                        {course.lesson_count.toLocaleString('vi-VN')}
                    </dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-muted-foreground">Chứng nhận</dt>
                    <dd className="font-semibold">Có mã xác minh</dd>
                </div>
            </dl>
            {cta.label && !(cta.label === 'Gia hạn' && cta.renew_url) && (
                <div aria-live="polite" className="grid">
                    {cta.url ? (
                        <a
                            href={
                                cta.buy && cta.login_url
                                    ? cta.login_url
                                    : cta.url
                            }
                            className="grid h-12 place-items-center rounded-full bg-amber px-4 text-base font-semibold text-slate-950 transition-transform hover:-translate-y-0.5 active:scale-[0.98]"
                        >
                            {cta.label}
                        </a>
                    ) : (
                        <p className="rounded-xl bg-accent px-4 py-3 text-sm font-medium text-muted-foreground">
                            {cta.label}
                        </p>
                    )}
                </div>
            )}
            <CommerceButtons cta={cta} />
        </div>
    );
}

export default function CoursePage({
    course,
    teacher,
    outline,
    cta,
    pricing,
    ratings,
}: Props) {
    const selling = course.sale_status === 'DANG_BAN';
    const previews = outline
        .flatMap((section) => section.lessons)
        .filter((lesson) => lesson.is_preview && lesson.preview_url).length;
    const facts = [
        { value: course.lesson_count, label: 'bài học' },
        ...(course.total_minutes !== null && course.total_minutes > 0
            ? [{ value: course.total_minutes, label: 'phút học' }]
            : []),
        { value: course.student_count, label: 'học viên' },
    ];

    return (
        <div>
            <Head title={course.title} />
            <section className="mx-auto w-full max-w-7xl px-4 md:px-8">
                <div className="relative isolate overflow-hidden rounded-3xl bg-slate-950 text-white">
                    {course.cover && (
                        <img
                            src={course.cover}
                            alt=""
                            className="absolute inset-0 -z-20 h-full w-full object-cover"
                        />
                    )}
                    <div className="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/95 via-slate-950/80 to-slate-950/40" />
                    <div className="flex max-w-3xl min-w-0 flex-col items-start gap-5 px-6 py-10 md:px-10 md:py-14 lg:pb-20">
                        <nav
                            aria-label="Vị trí"
                            className="flex flex-wrap items-center gap-1.5 text-sm text-white/70"
                        >
                            <Link
                                href={publicRoutes.courses.index()}
                                className="hover:text-white"
                            >
                                Danh mục khóa học
                            </Link>
                            {course.category && (
                                <span>
                                    {' / '}
                                    {course.category}
                                </span>
                            )}
                        </nav>
                        <div className="flex flex-wrap items-center gap-2 text-xs font-medium">
                            <span
                                className={
                                    selling
                                        ? 'rounded-full bg-emerald-400/20 px-3 py-1 text-emerald-100 ring-1 ring-emerald-300/40'
                                        : 'rounded-full bg-white/15 px-3 py-1'
                                }
                            >
                                {selling
                                    ? course.sale_status_label
                                    : 'Ngừng bán'}
                            </span>
                            {course.level && (
                                <span className="rounded-full bg-white/10 px-3 py-1 wrap-anywhere ring-1 ring-white/20">
                                    {course.level}
                                </span>
                            )}
                            {previews > 0 && (
                                <span className="rounded-full bg-white/10 px-3 py-1 ring-1 ring-white/20">
                                    {previews} bài học thử
                                </span>
                            )}
                        </div>
                        <SplitHeading className="font-display text-[clamp(2.2rem,4.4vw,3.6rem)] leading-[1.08] font-bold tracking-tight text-balance wrap-anywhere">
                            {course.title}
                        </SplitHeading>
                        {course.summary && (
                            <p className="max-w-2xl text-lg leading-relaxed wrap-anywhere text-white/80">
                                {course.summary}
                            </p>
                        )}
                        <div className="flex flex-wrap items-center gap-x-8 gap-y-4 pt-1">
                            {teacher && (
                                <Link
                                    href={publicRoutes.teachers.show(
                                        teacher.id,
                                    )}
                                    className="flex items-center gap-3 rounded-full py-1 pr-4 pl-1 transition-colors hover:bg-white/10"
                                >
                                    <span
                                        aria-hidden
                                        className="grid size-10 place-items-center rounded-full bg-white font-display text-lg font-bold text-slate-950"
                                    >
                                        {lastInitial(teacher.name)}
                                    </span>
                                    <span className="text-sm leading-tight">
                                        <span className="block text-white/65">
                                            Giáo viên
                                        </span>
                                        <span className="font-semibold wrap-anywhere">
                                            {teacher.name}
                                        </span>
                                    </span>
                                </Link>
                            )}
                            <dl className="flex flex-wrap gap-x-8 gap-y-2">
                                {facts.map((fact) => (
                                    <div
                                        key={fact.label}
                                        className="flex flex-col-reverse"
                                    >
                                        <dt className="text-sm text-white/65">
                                            {fact.label}
                                        </dt>
                                        <dd className="font-display text-2xl font-bold">
                                            {fact.value.toLocaleString('vi-VN')}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                        </div>
                    </div>
                </div>
            </section>

            <div className="mx-auto grid w-full max-w-7xl items-start gap-10 px-4 pt-10 pb-16 md:px-8 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <aside
                    aria-label="Giá và thời hạn truy cập"
                    className="shadow-lift overflow-hidden rounded-2xl border border-border bg-card lg:sticky lg:top-24 lg:col-start-2 lg:row-start-1"
                >
                    <CourseCover
                        course={course}
                        className="aspect-[16/9] w-full"
                    />
                    <PriceCard course={course} cta={cta} pricing={pricing} />
                </aside>

                <div className="flex min-w-0 flex-col gap-12 lg:col-start-1 lg:row-start-1">
                    {course.objectives && (
                        <section className="flex flex-col gap-4">
                            <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">
                                Mục tiêu học tập
                            </h2>
                            <ul className="grid gap-3 rounded-2xl border border-border bg-card p-6 shadow-soft sm:grid-cols-2">
                                {course.objectives
                                    .split(/\n+/)
                                    .map((line) =>
                                        line.replace(/^[-•*\s]+/, '').trim(),
                                    )
                                    .filter(Boolean)
                                    .map((line) => (
                                        <li
                                            key={line}
                                            className="flex gap-3 text-sm leading-relaxed wrap-anywhere"
                                        >
                                            <Check />
                                            {line}
                                        </li>
                                    ))}
                            </ul>
                        </section>
                    )}

                    <section className="flex flex-col gap-4">
                        <div className="flex flex-wrap items-baseline justify-between gap-2">
                            <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">
                                Đề cương
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {outline.length} chương, {course.lesson_count}{' '}
                                bài
                            </p>
                        </div>
                        {outline.length ? (
                            <div className="flex flex-col gap-3">
                                {outline.map((section, index) => (
                                    <details
                                        key={section.id}
                                        open={index === 0}
                                        className="group overflow-hidden rounded-2xl border border-border bg-card shadow-soft"
                                    >
                                        <summary className="flex cursor-pointer list-none items-center gap-4 px-5 py-4 font-semibold wrap-anywhere transition-colors group-open:border-b group-open:border-border hover:bg-accent/50 focus-visible:ring-2 focus-visible:ring-ring">
                                            <span className="grid size-8 shrink-0 place-items-center rounded-full border border-border text-sm font-semibold">
                                                {index + 1}
                                            </span>
                                            <span className="min-w-0 flex-1">
                                                {section.title}
                                            </span>
                                            <span className="text-xs font-medium text-muted-foreground">
                                                {section.lessons.length} bài
                                            </span>
                                        </summary>
                                        <ul className="divide-y divide-border bg-card">
                                            {section.lessons.map((lesson) => (
                                                <li
                                                    key={lesson.id}
                                                    className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 text-sm"
                                                >
                                                    <span className="w-8 shrink-0 font-medium text-muted-foreground tabular-nums">
                                                        {lesson.number}
                                                    </span>
                                                    <span className="min-w-0 grow basis-40 font-medium wrap-anywhere">
                                                        {lesson.title}
                                                    </span>
                                                    <span className="rounded-full bg-accent px-2.5 py-0.5 text-xs font-medium">
                                                        {lesson.type_label}
                                                    </span>
                                                    {lesson.minutes !==
                                                        null && (
                                                        <span className="text-muted-foreground tabular-nums">
                                                            {lesson.minutes}{' '}
                                                            phút
                                                        </span>
                                                    )}
                                                    {lesson.is_preview &&
                                                        lesson.preview_url && (
                                                            <Link
                                                                href={
                                                                    lesson.preview_url
                                                                }
                                                                aria-label={`Xem thử ${lesson.title}`}
                                                                className="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700 ring-1 ring-emerald-200 hover:bg-emerald-100 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30"
                                                            >
                                                                Xem thử
                                                            </Link>
                                                        )}
                                                </li>
                                            ))}
                                        </ul>
                                    </details>
                                ))}
                            </div>
                        ) : (
                            <p className="text-muted-foreground">
                                Chưa có bài học trong đề cương.
                            </p>
                        )}
                    </section>

                    {course.description && (
                        <section className="flex flex-col gap-3">
                            <h2 className="font-display text-2xl font-bold tracking-tight md:text-3xl">
                                Giới thiệu khóa học
                            </h2>
                            <p className="max-w-[70ch] leading-relaxed wrap-anywhere whitespace-pre-line">
                                {course.description}
                            </p>
                        </section>
                    )}

                    <RatingsSection ratings={ratings} />

                    {teacher && (
                        <section className="grid overflow-hidden rounded-2xl border border-border bg-card shadow-soft sm:grid-cols-[8rem_1fr]">
                            <div className="grid place-items-center p-6 sm:pr-0">
                                <span
                                    aria-hidden
                                    className="grid size-20 place-items-center rounded-full bg-amber-100 font-display text-3xl font-bold text-amber-900"
                                >
                                    {lastInitial(teacher.name)}
                                </span>
                            </div>
                            <div className="flex flex-col items-start gap-2 p-6">
                                <h2 className="text-sm font-medium text-muted-foreground">
                                    Về giáo viên
                                </h2>
                                <p className="text-xl font-bold tracking-tight wrap-anywhere">
                                    {teacher.name}
                                </p>
                                {teacher.bio && (
                                    <p className="leading-relaxed wrap-anywhere whitespace-pre-line text-muted-foreground">
                                        {teacher.bio}
                                    </p>
                                )}
                                <Link
                                    href={publicRoutes.teachers.show(
                                        teacher.id,
                                    )}
                                    className="mt-1 text-sm font-semibold underline underline-offset-4"
                                >
                                    Xem các khóa của giáo viên
                                </Link>
                            </div>
                        </section>
                    )}
                </div>
            </div>
        </div>
    );
}
