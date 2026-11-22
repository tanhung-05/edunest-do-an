import { Form, Head, Link, usePage } from '@inertiajs/react';
import {
    ArrowRight,
    BookOpen,
    CircleCheckBig,
    Clock3,
    PlayCircle,
    Receipt,
    Search,
    TimerReset,
} from 'lucide-react';
import { useRef } from 'react';
import { LearningBadge, LearningProgress } from '@/components/learning/records';
import { ProgressRing } from '@/components/learning/progress-ring';
import { CountUp } from '@/components/motion/count-up';
import { gsap, MOTION_OK, useGSAP } from '@/components/motion/gsap';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { CourseCard, CourseCover } from '@/components/public/course-card';
import type { PublicCourseCard } from '@/components/public/course-card';
import { Button } from '@/components/ui/button';
import student from '@/routes/student';
import { send } from '@/routes/verification';
import { index as catalog } from '@/routes/public/courses';
import type { LearningCourse } from '@/types/learning';
import { localTime, minuteSecond, validityLabels } from '@/types/learning';

function percentOf(course: LearningCourse) {
    const { completed, required } = course.progress;

    return required === 0 ? 0 : (100 * completed) / required;
}

function stopAt(course: LearningCourse) {
    const ms = course.learning_entry?.position_ms;

    return ms === null || ms === undefined
        ? null
        : minuteSecond(Math.floor(ms / 1_000));
}

/** Thẻ "học tiếp" là nội dung cần chú ý nhất: ảnh khóa chìm, nút học tiếp nổi bật. */
function ContinueHero({ course }: { course: LearningCourse }) {
    const position = stopAt(course);

    return (
        <section
            data-dash-enter
            className="relative isolate overflow-hidden rounded-3xl bg-slate-950 text-white"
        >
            {course.cover && (
                <img
                    src={course.cover}
                    alt=""
                    className="absolute inset-0 -z-20 h-full w-full object-cover"
                />
            )}
            <div className="absolute inset-0 -z-10 bg-gradient-to-r from-slate-950/95 via-slate-950/75 to-slate-950/30" />
            <div className="flex flex-wrap items-center gap-6 p-6 md:p-8">
                <ProgressRing
                    value={percentOf(course)}
                    size={104}
                    tone="dark"
                />
                <div className="grid min-w-0 flex-1 gap-2">
                    <p className="text-sm text-white/70">Đang học dở</p>
                    <h2 className="font-display text-2xl leading-tight font-bold tracking-tight wrap-anywhere md:text-3xl">
                        {course.title}
                    </h2>
                    <ul className="flex flex-wrap gap-x-5 gap-y-1 text-sm text-white/75">
                        <li className="flex items-center gap-1.5">
                            <CircleCheckBig className="size-4" />
                            {course.progress.completed}/
                            {course.progress.required} bài bắt buộc
                        </li>
                        {position && (
                            <li className="flex items-center gap-1.5">
                                <PlayCircle className="size-4" />
                                Dừng ở phút {position}
                            </li>
                        )}
                        <li className="flex items-center gap-1.5">
                            <Clock3 className="size-4" />
                            Hạn{' '}
                            {course.expires_at
                                ? localTime(course.expires_at)
                                : 'vĩnh viễn'}
                        </li>
                    </ul>
                </div>
                {course.learning_entry && (
                    <Link
                        href={course.learning_entry.url}
                        aria-label={`Tiếp tục học ${course.title}`}
                        className="inline-flex h-12 items-center gap-2 rounded-full bg-amber px-7 font-semibold text-slate-950 transition-transform hover:-translate-y-0.5 active:scale-[0.98]"
                    >
                        <PlayCircle className="size-5" />
                        Tiếp tục học
                    </Link>
                )}
            </div>
        </section>
    );
}

function CourseTile({ course }: { course: LearningCourse }) {
    return (
        <li className="group flex min-w-0 lift flex-col overflow-hidden rounded-2xl border border-border bg-card shadow-soft">
            <div className="relative">
                <CourseCover
                    course={{ id: course.course_id, cover: course.cover }}
                    className="aspect-[16/8] w-full"
                />
                <span className="absolute top-3 left-3">
                    <LearningBadge attention={course.validity !== 'CON_HAN'}>
                        {course.expires_soon
                            ? 'Sắp hết hạn'
                            : validityLabels[course.validity]}
                    </LearningBadge>
                </span>
            </div>
            <div className="flex flex-1 flex-col gap-3 p-5">
                <h3 className="font-semibold tracking-tight wrap-anywhere">
                    {course.title}
                </h3>
                <LearningProgress progress={course.progress} />
                <div className="mt-auto flex flex-wrap items-center gap-3 pt-1">
                    {course.learning_entry ? (
                        <Button asChild>
                            <Link
                                href={course.learning_entry.url}
                                aria-label={`Tiếp tục học ${course.title}`}
                            >
                                Tiếp tục học
                            </Link>
                        </Button>
                    ) : course.review_url ? (
                        <Button asChild variant="secondary">
                            <Link
                                href={course.review_url}
                                aria-label={`Xem lại bài học ${course.title}`}
                            >
                                Xem lại bài học
                            </Link>
                        </Button>
                    ) : (
                        <span className="text-sm text-muted-foreground">
                            {course.validity === 'CON_HAN'
                                ? 'Đã học hết bài'
                                : 'Không còn vào học được'}
                        </span>
                    )}
                    {stopAt(course) && (
                        <span className="text-sm text-muted-foreground">
                            Dừng ở {stopAt(course)}
                        </span>
                    )}
                </div>
            </div>
        </li>
    );
}

export default function StudentDashboard({
    needsEmailVerification,
    courseCount,
    learningCourses,
    suggestedCourses = [],
}: {
    needsEmailVerification: boolean;
    courseCount: number;
    learningCourses: LearningCourse[];
    suggestedCourses?: PublicCourseCard[];
}) {
    const { auth } = usePage().props;
    const scope = useRef<HTMLDivElement>(null);
    const firstName = auth.user?.name?.trim().split(/\s+/).at(-1) ?? 'bạn';
    const current =
        learningCourses.find(
            (course) =>
                course.validity === 'CON_HAN' && course.learning_entry !== null,
        ) ?? null;
    const attention = learningCourses.filter(
        (course) => course.expires_soon || course.validity !== 'CON_HAN',
    );
    const lessonsDone = learningCourses.reduce(
        (sum, course) => sum + course.progress.completed,
        0,
    );
    const active = learningCourses.filter(
        (course) => course.validity === 'CON_HAN',
    ).length;
    const stats = [
        {
            label: 'Khóa đã ghi danh',
            value: courseCount,
            icon: BookOpen,
            chip: 'bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300',
        },
        {
            label: 'Khóa còn hạn',
            value: active,
            icon: TimerReset,
            chip: 'bg-amber-50 text-amber-700 dark:bg-amber-400/10 dark:text-amber-300',
        },
        {
            label: 'Bài bắt buộc đã xong',
            value: lessonsDone,
            icon: CircleCheckBig,
            chip: 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300',
        },
    ];

    // MH-HV-01 là màn duy nhất trong vùng ứng dụng có chuyển động mở trang (09 §7 P4.5).
    useGSAP(
        () => {
            const mm = gsap.matchMedia();
            mm.add(MOTION_OK, () => {
                gsap.from('[data-dash-enter]', {
                    y: 18,
                    opacity: 0,
                    duration: 0.55,
                    stagger: 0.08,
                    ease: 'expo.out',
                    clearProps: 'opacity,transform',
                });
            });

            return () => mm.revert();
        },
        { scope },
    );

    return (
        <Page
            title={`Chào ${firstName}`}
            description="Học tiếp từ chỗ bạn dừng, theo dõi hạn truy cập và chứng nhận."
        >
            <Head title="Bảng học tập" />
            <div ref={scope} className="flex flex-col gap-5 md:gap-6">
                {needsEmailVerification && (
                    <Block tone="accent" className="grid gap-3 p-5">
                        <h2 className="font-semibold">
                            Hãy xác minh email để mua khóa học.
                        </h2>
                        <Form
                            {...send.form()}
                            className="grid justify-items-start gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button type="submit" disabled={processing}>
                                        {processing
                                            ? 'Đang gửi…'
                                            : 'Gửi lại email xác minh'}
                                    </Button>
                                    {(errors.email || errors.general) && (
                                        <p role="alert" className="text-sm">
                                            {errors.email || errors.general}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    </Block>
                )}

                {current && <ContinueHero course={current} />}

                <dl
                    data-dash-enter
                    className="grid grid-cols-2 gap-3 md:grid-cols-3 md:gap-4"
                >
                    {stats.map((item, index) => (
                        <div
                            key={item.label}
                            className={`flex items-center gap-4 rounded-2xl border border-border bg-card p-4 shadow-soft md:p-5 ${index === 2 ? 'col-span-2 md:col-span-1' : ''}`}
                        >
                            <span
                                className={`grid size-11 shrink-0 place-items-center rounded-xl ${item.chip}`}
                            >
                                <item.icon className="size-5" />
                            </span>
                            <div className="flex flex-col-reverse">
                                <dt className="text-sm text-muted-foreground">
                                    {item.label}
                                </dt>
                                <dd className="font-display text-3xl font-bold tracking-tight">
                                    <CountUp value={item.value} />
                                </dd>
                            </div>
                        </div>
                    ))}
                </dl>

                <div className="grid min-w-0 items-start gap-6 xl:grid-cols-[minmax(0,1fr)_20rem]">
                    <section
                        data-dash-enter
                        aria-labelledby="khoa-dang-hoc"
                        className="grid min-w-0 gap-4"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2
                                id="khoa-dang-hoc"
                                className="text-xl font-bold tracking-tight"
                            >
                                Khóa của tôi
                            </h2>
                            <Link
                                href={student.courses.index()}
                                className="group inline-flex items-center gap-1.5 text-sm font-semibold"
                            >
                                Xem tất cả
                                <ArrowRight className="size-4 transition-transform group-hover:translate-x-0.5" />
                            </Link>
                        </div>
                        {learningCourses.length === 0 ? (
                            <div className="flex flex-wrap items-center gap-5 rounded-2xl border border-dashed border-border bg-card p-6">
                                <span className="grid size-12 place-items-center rounded-xl bg-accent text-muted-foreground">
                                    <BookOpen className="size-6" />
                                </span>
                                <div className="grid justify-items-start gap-2">
                                    <p className="font-semibold">
                                        Bạn chưa ghi danh khóa học nào.
                                    </p>
                                    <Button asChild>
                                        <Link href={catalog()}>
                                            Xem danh mục khóa
                                        </Link>
                                    </Button>
                                </div>
                            </div>
                        ) : (
                            <ul
                                className="grid gap-4 md:grid-cols-2"
                                aria-live="polite"
                            >
                                {learningCourses.map((course) => (
                                    <CourseTile
                                        key={course.id}
                                        course={course}
                                    />
                                ))}
                            </ul>
                        )}
                        <OrderReload
                            only={['learningCourses']}
                            label="Tải lại dữ liệu học tập"
                            failure="Không tải được dữ liệu học tập"
                        />
                    </section>

                    <aside data-dash-enter className="grid min-w-0 gap-4">
                        {attention.length > 0 && (
                            <Block className="grid gap-3 p-5">
                                <h2 className="font-semibold">Hạn truy cập</h2>
                                <ul aria-live="polite" className="grid gap-3">
                                    {attention.map((course) => (
                                        <li
                                            key={course.id}
                                            className="grid gap-1 border-l-2 border-amber pl-3 text-sm"
                                        >
                                            <span className="font-medium wrap-anywhere">
                                                {course.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {course.expires_soon
                                                    ? 'Sắp hết hạn'
                                                    : validityLabels[
                                                          course.validity
                                                      ]}
                                                {course.expires_at &&
                                                    `, ${localTime(course.expires_at)}`}
                                            </span>
                                        </li>
                                    ))}
                                </ul>
                            </Block>
                        )}
                        <Link
                            href={catalog()}
                            className="group relative isolate flex min-h-48 photo-zoom items-end overflow-hidden rounded-2xl bg-slate-950 p-5 text-white"
                        >
                            <img
                                src="/images/stock/thu-vien.jpg"
                                alt=""
                                loading="lazy"
                                className="absolute inset-0 -z-20 h-full w-full object-cover"
                            />
                            <span className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/90 to-slate-950/20" />
                            <span className="grid gap-1">
                                <span className="font-display text-lg font-bold">
                                    Tìm khóa học tiếp theo
                                </span>
                                <span className="flex items-center gap-1.5 text-sm text-white/80">
                                    <Search className="size-4" />
                                    Xem danh mục khóa
                                </span>
                            </span>
                        </Link>
                        <Link
                            href={student.orders.index()}
                            className="flex items-center gap-3 rounded-2xl border border-border bg-card p-4 text-sm font-medium shadow-soft transition-colors hover:bg-accent/60"
                        >
                            <Receipt className="size-5 text-muted-foreground" />
                            Đơn hàng của tôi
                            <ArrowRight className="ml-auto size-4 text-muted-foreground" />
                        </Link>
                    </aside>
                </div>
                {suggestedCourses.length > 0 && (
                    <section
                        className="grid gap-4"
                        aria-labelledby="goi-y-khoa"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-2">
                            <div>
                                <h2
                                    id="goi-y-khoa"
                                    className="text-xl font-bold tracking-tight"
                                >
                                    Gợi ý cho bạn
                                </h2>
                                <p className="text-sm text-muted-foreground">
                                    Cùng môn với khóa bạn đang học và được nhiều
                                    học viên chọn.
                                </p>
                            </div>
                            <Link
                                href={catalog()}
                                className="flex items-center gap-1 text-sm font-medium underline-offset-4 hover:underline"
                            >
                                Xem tất cả khóa
                                <ArrowRight aria-hidden className="size-4" />
                            </Link>
                        </div>
                        <ul className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
                            {suggestedCourses.map((c) => (
                                <li key={c.id} className="min-w-0">
                                    <CourseCard course={c} />
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </Page>
    );
}
