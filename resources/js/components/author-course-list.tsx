import { Link } from '@inertiajs/react';
import { CalendarClock, PenLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
import teacher from '@/routes/teacher';
import type { CourseRow } from '@/types/course';

/** Danh sách "Khóa tôi soạn" (MH-GV-01/02) v5: thẻ sạch, nhãn trạng thái, quá hạn và lý do trả lại nổi bật. */
export function AuthorCourseList({ courses }: { courses: CourseRow[] }) {
    if (courses.length === 0) {
        return (
            <div className="flex items-center gap-4 rounded-2xl border border-dashed border-border bg-card p-5">
                <span className="grid size-11 place-items-center rounded-xl bg-accent text-muted-foreground">
                    <PenLine className="size-5" />
                </span>
                <p className="text-muted-foreground">
                    Chưa có khóa nào được giao cho bạn.
                </p>
            </div>
        );
    }

    return (
        <ul className="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
            {courses.map((c) => (
                <li
                    key={c.id}
                    className="flex min-w-0 flex-col gap-3 rounded-2xl border border-border bg-card p-5 shadow-soft"
                >
                    <div className="flex flex-wrap items-center gap-2">
                        <span className="rounded-full bg-accent px-2.5 py-1 text-xs font-medium">
                            {c.open_version
                                ? `Bản ${c.open_version.version_no}: ${c.open_version.status_label}`
                                : `Đã công bố, ${c.sale_status_label.toLowerCase()}`}
                        </span>
                        {c.overdue && (
                            <span className="rounded-full bg-rose-50 px-2.5 py-1 text-xs font-semibold text-rose-700 ring-1 ring-rose-200 dark:bg-rose-400/10 dark:text-rose-300 dark:ring-rose-400/30">
                                Quá hạn
                            </span>
                        )}
                    </div>
                    <h3 className="text-lg font-semibold tracking-tight wrap-anywhere">
                        {c.title}
                    </h3>
                    {c.due_date && (
                        <p className="flex items-center gap-1.5 text-sm text-muted-foreground">
                            <CalendarClock className="size-4" />
                            Hạn soạn {c.due_date}
                        </p>
                    )}
                    {c.return_reason && (
                        <p className="rounded-xl border-l-2 border-rose-500 bg-rose-50 px-3 py-2 text-sm text-rose-900 dark:bg-rose-400/10 dark:text-rose-100">
                            <strong>Bị trả lại:</strong> {c.return_reason}
                        </p>
                    )}
                    <div className="mt-auto flex flex-wrap gap-2 pt-2">
                        <Button asChild>
                            <Link href={teacher.courses.show(c.id)}>Soạn</Link>
                        </Button>
                        <Button asChild variant="outline">
                            <Link
                                href={teacher.courses.questionBanks.index(c.id)}
                            >
                                Ngân hàng câu hỏi
                            </Link>
                        </Button>
                    </div>
                </li>
            ))}
        </ul>
    );
}
