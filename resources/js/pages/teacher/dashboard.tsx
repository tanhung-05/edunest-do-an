import { Head, Link } from '@inertiajs/react';
import { AuthorCourseList } from '@/components/author-course-list';
import { Block, Page } from '@/components/page';
import teacher from '@/routes/teacher';
import type { CourseRow } from '@/types/course';

export default function TeacherDashboard({
    hasProfile,
    courses,
}: {
    hasProfile: boolean;
    courses: CourseRow[];
}) {
    const drafting = courses.filter((c) => c.open_version !== null).length;
    const overdue = courses.filter((c) => c.overdue).length;
    const returned = courses.filter((c) => c.return_reason).length;
    const stats = [
        {
            label: 'Khóa được giao',
            value: courses.length,
            tone: 'border border-border bg-card shadow-soft',
        },
        {
            label: 'Bản đang soạn hoặc chờ duyệt',
            value: drafting,
            tone: 'border border-border bg-card shadow-soft',
        },
        {
            label: 'Quá hạn soạn',
            value: overdue,
            tone:
                overdue > 0
                    ? 'border border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-400/30 dark:bg-rose-400/10 dark:text-rose-100'
                    : 'border border-border bg-card shadow-soft',
        },
        {
            label: 'Bị trả lại',
            value: returned,
            tone:
                returned > 0
                    ? 'border border-amber-200 bg-amber-50 text-amber-900 dark:border-amber-400/30 dark:bg-amber-400/10 dark:text-amber-100'
                    : 'border border-border bg-card shadow-soft',
        },
    ];

    return (
        <Page
            title="Việc cần làm"
            description="Các khóa được giao cho bạn soạn, kèm hạn và kết quả duyệt."
        >
            <Head title="Việc cần làm" />
            {!hasProfile && (
                <Block tone="accent">
                    <p className="font-semibold">
                        Bạn chưa có hồ sơ giáo viên. Giáo vụ sẽ lập hồ sơ cho
                        bạn.
                    </p>
                </Block>
            )}
            <dl className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                {stats.map((item) => (
                    <div
                        key={item.label}
                        className={`flex flex-col-reverse rounded-2xl p-5 ${item.tone}`}
                    >
                        <dd className="font-display text-3xl font-bold tracking-tight tabular-nums">
                            {item.value}
                        </dd>
                        <dt className="text-sm opacity-75">{item.label}</dt>
                    </div>
                ))}
            </dl>
            <section className="grid gap-4">
                <div className="flex flex-wrap items-end justify-between gap-3">
                    <h2 className="text-xl font-bold tracking-tight">
                        Khóa tôi soạn
                    </h2>
                    {hasProfile && (
                        <Link
                            href={teacher.profile.edit()}
                            className="text-sm font-semibold underline underline-offset-4"
                        >
                            Sửa hồ sơ giáo viên
                        </Link>
                    )}
                </div>
                <AuthorCourseList courses={courses} />
            </section>
        </Page>
    );
}
