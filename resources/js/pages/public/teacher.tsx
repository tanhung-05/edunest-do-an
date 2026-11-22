import { Head } from '@inertiajs/react';
import { Award, BookOpen } from 'lucide-react';
import { CourseCard } from '@/components/public/course-card';
import type { PublicCourseCard } from '@/components/public/course-card';

type Props = {
    teacher: {
        id: number;
        name: string;
        photo: string | null;
        bio: string | null;
        achievements: string | null;
    };
    courses: {
        id: number;
        title: string;
        slug: string;
        price: number;
        cover: string | null;
    }[];
};

/** MH-KH trang giáo viên v5: dải ảnh chìm, ảnh đại diện tròn, giới thiệu và khóa đang bán. */
export default function TeacherPage({ teacher, courses }: Props) {
    const initial = teacher.name.trim().split(/\s+/).at(-1)?.[0] ?? '';
    const cards: PublicCourseCard[] = courses.map((c) => ({
        ...c,
        original_price: null,
        teacher: teacher.name,
        teacher_id: teacher.id,
        category: null,
        level: null,
    }));

    return (
        <>
            <Head title={teacher.name} />
            <section className="mx-auto w-full max-w-7xl px-4 md:px-8">
                <div className="relative isolate overflow-hidden rounded-3xl bg-slate-950 px-6 pt-28 pb-8 text-white md:px-10">
                    <img
                        src="/images/stock/thu-vien.jpg"
                        alt=""
                        className="absolute inset-0 -z-20 h-full w-full object-cover"
                    />
                    <div className="absolute inset-0 -z-10 bg-gradient-to-t from-slate-950/95 via-slate-950/60 to-slate-950/30" />
                    <div className="flex flex-wrap items-end gap-6">
                        {teacher.photo ? (
                            <img
                                src={teacher.photo}
                                alt={`Ảnh ${teacher.name}`}
                                className="size-28 rounded-full object-cover ring-4 ring-white/90 md:size-36"
                            />
                        ) : (
                            <span
                                aria-hidden
                                className="grid size-28 place-items-center rounded-full bg-white font-display text-5xl font-bold text-slate-950 md:size-36"
                            >
                                {initial}
                            </span>
                        )}
                        <div className="grid gap-2 pb-2">
                            <p className="text-sm text-white/70">
                                Giáo viên EduNest
                            </p>
                            <h1 className="font-display text-[clamp(2.2rem,4.6vw,3.6rem)] leading-tight font-bold tracking-tight">
                                {teacher.name}
                            </h1>
                            <p className="flex items-center gap-2 text-sm text-white/80">
                                <BookOpen className="size-4" />
                                {courses.length} khóa học đang mở bán
                            </p>
                        </div>
                    </div>
                </div>
            </section>

            {(teacher.bio || teacher.achievements) && (
                <div className="mx-auto grid w-full max-w-7xl gap-6 px-4 pt-10 md:grid-cols-[1.4fr_1fr] md:px-8">
                    {teacher.bio && (
                        <section className="grid content-start gap-3 rounded-2xl border border-border bg-card p-6 shadow-soft">
                            <h2 className="text-xl font-bold tracking-tight">
                                Giới thiệu
                            </h2>
                            <p className="leading-relaxed whitespace-pre-line text-muted-foreground">
                                {teacher.bio}
                            </p>
                        </section>
                    )}
                    {teacher.achievements && (
                        <section className="grid content-start gap-3 rounded-2xl border border-amber-200 bg-amber-50 p-6 text-amber-950 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-50">
                            <h2 className="flex items-center gap-2 text-xl font-bold tracking-tight">
                                <Award className="size-5" />
                                Thành tích
                            </h2>
                            <p className="leading-relaxed whitespace-pre-line opacity-85">
                                {teacher.achievements}
                            </p>
                        </section>
                    )}
                </div>
            )}

            {courses.length > 0 && (
                <section className="mx-auto w-full max-w-7xl space-y-6 px-4 pt-14 pb-20 md:px-8">
                    <h2 className="font-display text-3xl font-bold tracking-tight">
                        Khóa học của {teacher.name}
                    </h2>
                    <ul className="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                        {cards.map((c) => (
                            <CourseCard key={c.id} course={c} />
                        ))}
                    </ul>
                </section>
            )}
        </>
    );
}
