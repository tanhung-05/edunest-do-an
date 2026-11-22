import { Link } from '@inertiajs/react';
import { ArrowUpRight, UserRound } from 'lucide-react';
import { CoursePrice } from '@/components/public/course-price';
import { cn } from '@/lib/utils';
import publicRoutes from '@/routes/public';

export type PublicCourseCard = {
    id: number;
    title: string;
    slug: string;
    cover: string | null;
    price: number;
    original_price: number | null;
    teacher: string | null;
    teacher_id: number | null;
    category: string | null;
    level: string | null;
};

/** Ảnh bìa khóa (he-thiet-ke-v5): ảnh giáo viên tải lên, không có thì máy chủ trả ảnh thật theo môn. */
export function CourseCover({
    course,
    className,
}: {
    course: Pick<PublicCourseCard, 'id' | 'cover'>;
    className?: string;
}) {
    return (
        <div
            className={cn(
                'photo-zoom overflow-hidden bg-muted',
                className ?? 'aspect-[16/10] w-full',
            )}
        >
            {course.cover && (
                <img
                    src={course.cover}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    className="h-full w-full object-cover"
                />
            )}
        </div>
    );
}

export function CourseCard({ course }: { course: PublicCourseCard }) {
    return (
        <li className="min-w-0">
            <Link
                href={publicRoutes.courses.show(course.slug)}
                className="group flex h-full lift flex-col overflow-hidden rounded-2xl border border-border bg-card text-card-foreground shadow-soft"
            >
                <div className="relative">
                    <CourseCover course={course} />
                    {course.category && (
                        <span className="absolute top-3 left-3 rounded-full bg-white/95 px-3 py-1 text-xs font-semibold text-slate-900 backdrop-blur">
                            {course.category}
                        </span>
                    )}
                </div>
                <div className="flex flex-1 flex-col gap-2.5 p-5">
                    {course.level && (
                        <p className="text-xs font-medium text-muted-foreground">
                            {course.level}
                        </p>
                    )}
                    <h3 className="text-[1.05rem] leading-snug font-semibold tracking-tight wrap-anywhere">
                        {course.title}
                    </h3>
                    {course.teacher && (
                        <p className="flex items-center gap-1.5 text-sm wrap-anywhere text-muted-foreground">
                            <UserRound aria-hidden className="size-4" />
                            {course.teacher}
                        </p>
                    )}
                    <div className="mt-auto flex items-end justify-between gap-3 border-t border-border pt-4 text-lg">
                        <CoursePrice
                            price={course.price}
                            originalPrice={course.original_price}
                        />
                        <ArrowUpRight
                            aria-hidden
                            className="size-5 text-muted-foreground transition-transform duration-300 group-hover:translate-x-0.5 group-hover:-translate-y-0.5 group-hover:text-foreground"
                        />
                    </div>
                </div>
            </Link>
        </li>
    );
}
