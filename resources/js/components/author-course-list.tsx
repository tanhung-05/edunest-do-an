import { Link } from '@inertiajs/react';
import { CalendarClock, PenLine } from 'lucide-react';
import { Button } from '@/components/ui/button';
import teacher from '@/routes/teacher';
import type { CourseRow } from '@/types/course';

/** UAT 22/11: nhãn nút theo đúng việc làm được ở trạng thái hiện tại của khóa. */
export function authorActionLabel(c: CourseRow): string {
    switch (c.open_version?.status) {
        case 'NHAP':
            return 'Tiếp tục soạn';
        case 'TRA_LAI':
            return 'Sửa theo góp ý';
        case 'CHO_DUYET':
            return 'Xem bản chờ duyệt';
        case 'DA_DUYET':
            return 'Xem bản đã duyệt';
        default:
            return 'Xem nội dung, tạo bản mới';
    }
}

function statusText(c: CourseRow): string {
    return c.open_version
        ? `Bản ${c.open_version.version_no}: ${c.open_version.status_label}`
        : `Đã công bố, ${c.sale_status_label.toLowerCase()}`;
}

/** Danh sách "Khóa tôi soạn" (MH-GV-01/02) v5: thẻ sạch, nhãn trạng thái, quá hạn và lý do trả lại nổi bật. */
export function AuthorCourseList({
    courses,
    layout = 'grid',
}: {
    courses: CourseRow[];
    layout?: 'grid' | 'list';
}) {
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

    if (layout === 'list') {
        return (
            <div className="overflow-x-auto rounded-2xl border border-border bg-card">
                <table className="w-full min-w-[36rem] text-sm">
                    <thead className="bg-muted/50 text-left text-xs text-muted-foreground">
                        <tr>
                            <th className="px-4 py-2 font-medium">Khóa học</th>
                            <th className="px-4 py-2 font-medium">
                                Trạng thái
                            </th>
                            <th className="px-4 py-2 font-medium">Hạn soạn</th>
                            <th className="px-4 py-2 font-medium">
                                <span className="sr-only">Thao tác</span>
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {courses.map((c) => (
                            <tr key={c.id} className="border-t border-border">
                                <td className="px-4 py-2.5 font-medium wrap-anywhere">
                                    {c.title}
                                </td>
                                <td className="px-4 py-2.5">
                                    {statusText(c)}
                                    {c.overdue && (
                                        <span className="ml-2 text-xs font-semibold text-rose-700 dark:text-rose-300">
                                            Quá hạn
                                        </span>
                                    )}
                                </td>
                                <td className="px-4 py-2.5 tabular-nums">
                                    {c.due_date ?? '–'}
                                </td>
                                <td className="px-4 py-2.5 text-right">
                                    <Button asChild size="sm">
                                        <Link href={teacher.courses.show(c.id)}>
                                            {authorActionLabel(c)}
                                        </Link>
                                    </Button>
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
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
                            {statusText(c)}
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
                            <Link href={teacher.courses.show(c.id)}>
                                {authorActionLabel(c)}
                            </Link>
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
