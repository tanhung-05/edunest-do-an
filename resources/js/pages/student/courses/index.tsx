import { Head, Link } from '@inertiajs/react';
import {
    BookOpen,
    CalendarClock,
    CircleCheck,
    Hourglass,
    Layers,
    PlayCircle,
    RotateCcw,
} from 'lucide-react';
import { LearningBadge, LearningProgress } from '@/components/learning/records';
import { RenewButton } from '@/components/learning/renew-button';
import { OrderReload } from '@/components/order/refresh';
import { OrderBadge } from '@/components/order/status';
import { Block, Page } from '@/components/page';
import { CourseCover } from '@/components/public/course-card';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import publicRoutes from '@/routes/public';
import type { StudentCourse } from '@/types/learning';
import { localTime, minuteSecond } from '@/types/learning';

/**
 * MH-HV khóa của tôi. P10: dải đầu trang có số tóm tắt; mỗi khóa là thẻ ngang (ảnh trái, tiến độ phủ trên ảnh) để
 * xem được nhiều khóa trên một màn và không để trống.
 */
export default function StudentCourses({
    courses,
    selectedCourse,
}: {
    courses: StudentCourse[];
    selectedCourse: number | null;
}) {
    const active = courses.filter((c) => c.validity === 'CON_HAN').length;
    const soon = courses.filter((c) => c.expires_soon).length;
    const done = courses.filter(
        (c) =>
            c.progress.required > 0 &&
            c.progress.completed >= c.progress.required,
    ).length;

    return (
        <Page
            title="Khóa của tôi"
            description="Tiếp tục đúng chỗ bạn dừng, theo dõi tiến độ bài bắt buộc và hạn truy cập của từng khóa."
            actions={
                <Button asChild variant="secondary">
                    <Link href={publicRoutes.courses.index()}>
                        Tìm thêm khóa học
                    </Link>
                </Button>
            }
            stats={[
                { label: 'Đã ghi danh', value: courses.length, icon: Layers },
                { label: 'Còn hạn', value: active, icon: PlayCircle },
                {
                    label: 'Sắp hết hạn',
                    value: soon,
                    icon: Hourglass,
                    attention: soon > 0,
                },
                { label: 'Hoàn thành', value: done, icon: CircleCheck },
            ]}
        >
            <Head title="Khóa của tôi" />
            <div aria-live="polite">
                {courses.length === 0 ? (
                    <Block className="flex flex-wrap items-center gap-4 border-dashed">
                        <span
                            aria-hidden
                            className="grid size-11 shrink-0 place-items-center rounded-xl bg-accent text-muted-foreground"
                        >
                            <BookOpen className="size-5" />
                        </span>
                        <div className="min-w-0 flex-1">
                            <p className="font-medium">
                                Bạn chưa ghi danh khóa học nào.
                            </p>
                            <p className="text-sm text-muted-foreground">
                                Xem danh mục hoặc hỏi trợ lý tư vấn để chọn khóa
                                hợp với mục tiêu.
                            </p>
                        </div>
                        <Button asChild>
                            <Link href={publicRoutes.courses.index()}>
                                Xem danh mục
                            </Link>
                        </Button>
                    </Block>
                ) : (
                    <ul className="grid gap-4 xl:grid-cols-2">
                        {courses.map((course) => (
                            <li key={course.id} className="min-w-0">
                                <CourseRow
                                    course={course}
                                    selected={
                                        course.course_id === selectedCourse
                                    }
                                />
                            </li>
                        ))}
                    </ul>
                )}
            </div>
            <OrderReload
                only={['courses']}
                label="Tải lại danh sách"
                failure="Không tải được khóa của bạn."
            />
        </Page>
    );
}

function CourseRow({
    course,
    selected,
}: {
    course: StudentCourse;
    selected: boolean;
}) {
    const percent =
        course.progress.required > 0
            ? Math.round(
                  (100 * course.progress.completed) / course.progress.required,
              )
            : null;

    return (
        <article
            className={cn(
                'group grid h-full overflow-hidden rounded-2xl border border-border bg-card shadow-soft transition-shadow hover:shadow-[0_14px_32px_-18px_rgb(15_23_42/0.45)] sm:grid-cols-[minmax(0,15rem)_1fr]',
                selected && 'ring-2 ring-amber',
            )}
        >
            <div className="relative">
                <CourseCover
                    course={{ id: course.course_id, cover: course.cover }}
                    className="aspect-[16/9] h-full w-full sm:aspect-auto sm:min-h-full"
                />
                <div
                    aria-hidden
                    className="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-slate-950/10 to-transparent"
                />
                <div className="absolute inset-x-3 bottom-3 flex items-center justify-between gap-2 text-white">
                    <OrderBadge
                        status={course.validity}
                        label={course.validity_label}
                    />
                    {percent !== null && (
                        <span className="rounded-full bg-white/15 px-2.5 py-0.5 text-sm font-bold tabular-nums backdrop-blur">
                            {percent}%
                        </span>
                    )}
                </div>
            </div>
            <div className="grid min-w-0 content-between gap-4 p-5">
                <div className="grid gap-2">
                    <div className="flex flex-wrap items-start justify-between gap-2">
                        <h2 className="text-lg leading-snug font-semibold tracking-tight wrap-anywhere">
                            {course.title}
                        </h2>
                        {course.expires_soon && (
                            <LearningBadge attention>Sắp hết hạn</LearningBadge>
                        )}
                    </div>
                    <p className="flex flex-wrap items-center gap-x-4 gap-y-1 text-sm text-muted-foreground">
                        <span>{course.learning_status}</span>
                        <span>Phiên bản {course.version}</span>
                        <span className="flex items-center gap-1.5">
                            <CalendarClock className="size-4" aria-hidden />
                            {course.expires_at
                                ? `Hạn ${localTime(course.expires_at)}`
                                : 'Học vĩnh viễn'}
                        </span>
                    </p>
                    <LearningProgress progress={course.progress} />
                </div>
                <div className="flex flex-wrap items-center gap-x-3 gap-y-2">
                    {course.learning_entry && (
                        <>
                            <Button asChild>
                                <Link
                                    href={course.learning_entry.url}
                                    aria-label={`Tiếp tục học ${course.title}`}
                                >
                                    <PlayCircle aria-hidden />
                                    Tiếp tục học
                                </Link>
                            </Button>
                            {course.learning_entry.position_ms !== null && (
                                <span className="text-sm text-muted-foreground">
                                    Dừng ở{' '}
                                    {minuteSecond(
                                        Math.floor(
                                            course.learning_entry.position_ms /
                                                1_000,
                                        ),
                                    )}
                                </span>
                            )}
                        </>
                    )}
                    {!course.learning_entry && course.review_url && (
                        <Button asChild variant="secondary">
                            <Link
                                href={course.review_url}
                                aria-label={`Xem lại bài học ${course.title}`}
                            >
                                <RotateCcw aria-hidden />
                                Xem lại bài học
                            </Link>
                        </Button>
                    )}
                    {course.validity === 'HET_HAN' && !course.renew_url && (
                        <p className="text-sm">Đã hết hạn truy cập</p>
                    )}
                    {course.renew_url && (
                        <RenewButton
                            url={course.renew_url}
                            price={course.extension_price}
                            expired={course.validity === 'HET_HAN'}
                        />
                    )}
                    {course.validity === 'DA_THU_HOI' && (
                        <p className="text-sm">
                            Quyền học đã bị thu hồi. Bạn nhắn nhân viên hỗ trợ
                            để được giải thích.
                        </p>
                    )}
                </div>
            </div>
        </article>
    );
}
