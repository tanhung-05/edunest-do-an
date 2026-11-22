import { Form, Head } from '@inertiajs/react';
import { CalendarClock, CircleCheck, CircleX, Users } from 'lucide-react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import { ConfirmAction } from '@/components/confirm-action';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import teacher from '@/routes/teacher';
import type { IdName } from '@/types/course';

type Session = {
    id: number;
    course_id: number;
    course: string | null;
    title: string;
    description: string | null;
    starts_at: string;
    duration_minutes: number;
    meeting_url: string;
    cancelled: boolean;
    ended: boolean;
    can_reschedule: boolean;
    recording: string | null;
    joins: { name: string; at: string }[];
};

/** Giờ ISO → giá trị ô datetime-local theo giờ Việt Nam. */
function localValue(iso: string): string {
    const d = new Date(new Date(iso).getTime() + 7 * 3600 * 1000);

    return d.toISOString().slice(0, 16);
}

/** MH-GV buổi trực tiếp (QT-28): lên lịch, sửa/dời/hủy, xem người đã vào phòng, tải bản ghi sau buổi. */
export default function TeacherLive({
    courses,
    sessions,
}: {
    courses: IdName[];
    sessions: Session[];
}) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <Page
            title="Buổi học trực tiếp"
            description="Dán link phòng họp của bạn (Google Meet, Zoom…). Học viên còn hạn vào phòng từ 10 phút trước giờ bắt đầu; hệ thống nhắc trước 30 phút."
            stats={[
                {
                    label: 'Sắp diễn ra',
                    value: sessions.filter((s) => !s.cancelled && !s.ended)
                        .length,
                    icon: CalendarClock,
                },
                {
                    label: 'Đã kết thúc',
                    value: sessions.filter((s) => s.ended).length,
                    icon: CircleCheck,
                },
                {
                    label: 'Đã hủy',
                    value: sessions.filter((s) => s.cancelled).length,
                    icon: CircleX,
                },
                {
                    label: 'Lượt vào phòng',
                    value: sessions.reduce((n, s) => n + s.joins.length, 0),
                    icon: Users,
                },
            ]}
        >
            <Head title="Buổi học trực tiếp" />
            {courses.length === 0 ? (
                <Block>
                    <p className="text-sm text-muted-foreground">
                        Bạn chưa được giao khóa nào.
                    </p>
                </Block>
            ) : (
                <Block className="grid gap-3 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Lên lịch buổi mới</h2>
                    <SessionForm courses={courses} />
                </Block>
            )}
            <ul className="grid gap-3">
                {sessions.length === 0 && (
                    <li className="text-sm text-muted-foreground">
                        Chưa có buổi nào.
                    </li>
                )}
                {sessions.map((s) => (
                    <li key={s.id}>
                        <Block className="grid gap-3 p-4 sm:p-5">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div className="grid min-w-0 gap-1">
                                    <p className="text-xs text-muted-foreground">
                                        {s.course}
                                    </p>
                                    <p className="font-semibold wrap-anywhere">
                                        {s.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {vnTime(s.starts_at)} ·{' '}
                                        {s.duration_minutes} phút ·{' '}
                                        {s.cancelled
                                            ? 'Đã hủy'
                                            : s.ended
                                              ? 'Đã kết thúc'
                                              : 'Đã lên lịch'}
                                    </p>
                                    <p className="text-sm wrap-anywhere">
                                        {s.meeting_url}
                                    </p>
                                </div>
                                {!s.cancelled && !s.ended && (
                                    <div className="flex flex-wrap gap-2">
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() =>
                                                setEditing(
                                                    editing === s.id
                                                        ? null
                                                        : s.id,
                                                )
                                            }
                                        >
                                            {editing === s.id ? 'Đóng' : 'Sửa'}
                                        </Button>
                                        <ConfirmAction
                                            size="sm"
                                            variant="outline"
                                            form={teacher.liveSessions.cancel.form(
                                                s.id,
                                            )}
                                            label="Hủy buổi"
                                            title="Hủy buổi này?"
                                            description="Học viên không vào phòng qua EduNest được nữa và không nhận nhắc. Bạn tự đóng phòng trên dịch vụ họp."
                                            confirmLabel="Hủy buổi"
                                        />
                                    </div>
                                )}
                            </div>
                            {editing === s.id && (
                                <div className="border-t pt-3">
                                    <SessionForm session={s} />
                                </div>
                            )}
                            {s.ended && !s.cancelled && (
                                <Form
                                    {...teacher.liveSessions.recording.form(
                                        s.id,
                                    )}
                                    options={{ preserveScroll: true }}
                                    resetOnSuccess
                                    className="flex flex-wrap items-center gap-2 border-t pt-3"
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <span className="text-sm">
                                                {s.recording
                                                    ? `Bản ghi: ${s.recording}`
                                                    : 'Chưa có bản ghi'}
                                            </span>
                                            <Input
                                                type="file"
                                                name="file"
                                                required
                                                accept=".mp4,.webm,.mp3,.m4a,.pdf"
                                                aria-label="Tệp bản ghi"
                                                className="max-w-xs"
                                            />
                                            <Button
                                                variant="outline"
                                                size="sm"
                                                disabled={processing}
                                            >
                                                {s.recording
                                                    ? 'Thay bản ghi'
                                                    : 'Tải bản ghi'}
                                            </Button>
                                            {errors.file && (
                                                <p className="w-full text-sm text-destructive">
                                                    {errors.file}
                                                </p>
                                            )}
                                        </>
                                    )}
                                </Form>
                            )}
                            <details className="border-t pt-3 text-sm">
                                <summary className="cursor-pointer font-medium">
                                    Đã bấm vào phòng ({s.joins.length})
                                </summary>
                                {s.joins.length === 0 ? (
                                    <p className="mt-2 text-muted-foreground">
                                        Chưa có học viên nào.
                                    </p>
                                ) : (
                                    <ul className="mt-2 grid gap-1">
                                        {s.joins.map((j, i) => (
                                            <li key={i}>
                                                {j.name} · {vnTime(j.at)}
                                            </li>
                                        ))}
                                    </ul>
                                )}
                            </details>
                        </Block>
                    </li>
                ))}
            </ul>
        </Page>
    );
}

function SessionForm({
    courses,
    session,
}: {
    courses?: IdName[];
    session?: Session;
}) {
    const form = session
        ? teacher.liveSessions.update.form(session.id)
        : teacher.liveSessions.store.form();
    const locked = session !== undefined && !session.can_reschedule;

    return (
        <Form
            {...form}
            options={{ preserveScroll: true }}
            resetOnSuccess={session === undefined}
            className="grid gap-3 sm:grid-cols-2"
        >
            {({ processing, errors }) => (
                <>
                    {courses && (
                        <label className="grid gap-1 text-sm">
                            Khóa học
                            <select
                                name="course_id"
                                required
                                className="h-9 rounded-md border bg-background px-2"
                            >
                                {courses.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.name}
                                    </option>
                                ))}
                            </select>
                        </label>
                    )}
                    <label className="grid gap-1 text-sm">
                        Tên buổi
                        <Input
                            name="title"
                            required
                            maxLength={150}
                            defaultValue={session?.title}
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Bắt đầu (giờ Việt Nam)
                        <Input
                            type="datetime-local"
                            name="starts_at"
                            required
                            readOnly={locked}
                            defaultValue={
                                session
                                    ? localValue(session.starts_at)
                                    : undefined
                            }
                        />
                    </label>
                    <label className="grid gap-1 text-sm">
                        Thời lượng (phút)
                        <Input
                            type="number"
                            name="duration_minutes"
                            min={15}
                            max={480}
                            required
                            readOnly={locked}
                            defaultValue={session?.duration_minutes ?? 60}
                        />
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">
                        Link phòng họp
                        <Input
                            type="url"
                            name="meeting_url"
                            required
                            readOnly={locked}
                            placeholder="https://meet.google.com/..."
                            defaultValue={session?.meeting_url}
                        />
                    </label>
                    <label className="grid gap-1 text-sm sm:col-span-2">
                        Mô tả
                        <textarea
                            name="description"
                            rows={3}
                            maxLength={5000}
                            defaultValue={session?.description ?? ''}
                            className="rounded-md border bg-background px-3 py-2"
                        />
                    </label>
                    {locked && (
                        <p className="text-xs text-muted-foreground sm:col-span-2">
                            Còn dưới 10 phút tới giờ bắt đầu: chỉ sửa được tên
                            và mô tả.
                        </p>
                    )}
                    <div className="grid justify-items-start gap-1 sm:col-span-2">
                        <Button type="submit" disabled={processing}>
                            {session ? 'Lưu thay đổi' : 'Lên lịch'}
                        </Button>
                        {Object.values(errors)[0] && (
                            <p className="text-sm text-destructive">
                                {Object.values(errors)[0]}
                            </p>
                        )}
                    </div>
                </>
            )}
        </Form>
    );
}
