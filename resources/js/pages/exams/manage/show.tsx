import { Form, Head, Link } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { AnalyticsPanel, ExamBadge, ExamFacts } from '@/components/exams';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { Analytics, ExamSummary } from '@/types/exams';

type Props = {
    scope: 'teacher' | 'academic';
    event: ExamSummary & {
        courses: string[];
        opens_input: string;
        closes_input: string;
        publish_input: string | null;
        question_count: number;
        registered: number;
    };
    actions: {
        edit_url: string | null;
        schedule_url: string | null;
        reschedule_url: string | null;
        publish_at_url: string | null;
        export_url: string | null;
        room_url: string;
        index_url: string;
    };
    participants: {
        id: number;
        name: string | null;
        status: string;
        reason: string | null;
        percent: string | null;
        rank: number | null;
        ranked: boolean;
        started_at: string;
        tab_leaves: number | null;
        tab_leave_seconds: number | null;
    }[];
    analytics: Analytics | null;
};

export default function ManageExam({
    scope,
    event,
    actions,
    participants,
    analytics,
}: Props) {
    const teacher = scope === 'teacher';

    return (
        <Page
            title={event.title}
            eyebrow={
                <Link href={actions.index_url} className="hover:underline">
                    Kỳ thi thử
                </Link>
            }
            actions={
                <>
                    <ExamBadge exam={event} />
                    <Button variant="outline" asChild>
                        <Link href={actions.room_url}>
                            Xem như người dự thi
                        </Link>
                    </Button>
                </>
            }
        >
            <Head title={event.title} />
            <Block className="grid gap-4">
                <ExamFacts
                    exam={event}
                    extra={[
                        [
                            'Đề',
                            `${event.paper ?? '—'} · ${event.question_count} câu`,
                        ],
                        ['Đã đăng ký', event.registered],
                        ...(event.courses.length
                            ? ([
                                  ['Khóa chỉ định', event.courses.join(', ')],
                              ] as [string, string][])
                            : []),
                    ]}
                />
                <div className="flex flex-wrap gap-2">
                    {actions.edit_url && (
                        <Button variant="outline" asChild>
                            <Link href={actions.edit_url}>Sửa nháp</Link>
                        </Button>
                    )}
                    {actions.schedule_url && (
                        <Form
                            action={actions.schedule_url}
                            method="post"
                            onBefore={() =>
                                window.confirm(
                                    'Chuyển sang Sắp mở? Đề sẽ bị khóa, không sửa được câu.',
                                )
                            }
                        >
                            {({ processing, errors }) => (
                                <div className="grid gap-1">
                                    <Button disabled={processing}>
                                        Chuyển sang Sắp mở
                                    </Button>
                                    {Object.values(errors)[0] && (
                                        <p className="text-sm text-destructive">
                                            {Object.values(errors)[0]}
                                        </p>
                                    )}
                                </div>
                            )}
                        </Form>
                    )}
                </div>
            </Block>
            <div className="grid gap-4 md:grid-cols-2">
                {actions.reschedule_url && (
                    <Block>
                        <Form
                            action={actions.reschedule_url}
                            method="post"
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <h2 className="font-semibold">
                                        Dời lịch (trước giờ mở)
                                    </h2>
                                    <div className="grid gap-3 sm:grid-cols-2">
                                        <Field
                                            label="Giờ mở mới"
                                            htmlFor="r_opens"
                                            error={errors.opens_at}
                                        >
                                            <Input
                                                id="r_opens"
                                                name="opens_at"
                                                type="datetime-local"
                                                required
                                                defaultValue={event.opens_input}
                                            />
                                        </Field>
                                        <Field
                                            label="Giờ đóng mới"
                                            htmlFor="r_closes"
                                            error={errors.closes_at}
                                        >
                                            <Input
                                                id="r_closes"
                                                name="closes_at"
                                                type="datetime-local"
                                                required
                                                defaultValue={
                                                    event.closes_input
                                                }
                                            />
                                        </Field>
                                    </div>
                                    <Field
                                        label="Thời lượng (phút)"
                                        htmlFor="r_duration"
                                        error={errors.duration_minutes}
                                    >
                                        <Input
                                            id="r_duration"
                                            name="duration_minutes"
                                            type="number"
                                            min={1}
                                            max={600}
                                            required
                                            defaultValue={
                                                event.duration_minutes
                                            }
                                        />
                                    </Field>
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                        className="justify-self-start"
                                    >
                                        Dời lịch
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>
                )}
                {actions.publish_at_url && (
                    <Block>
                        <Form
                            action={actions.publish_at_url}
                            method="put"
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <h2 className="font-semibold">
                                        Giờ công bố kết quả
                                    </h2>
                                    <Field
                                        label="Hẹn công bố lúc"
                                        htmlFor="p_at"
                                        error={errors.publish_at}
                                        hint="Để trống: công bố ngay khi đóng."
                                    >
                                        <Input
                                            id="p_at"
                                            name="publish_at"
                                            type="datetime-local"
                                            defaultValue={
                                                event.publish_input ?? undefined
                                            }
                                        />
                                    </Field>
                                    <Button
                                        variant="outline"
                                        disabled={processing}
                                        className="justify-self-start"
                                    >
                                        Lưu giờ công bố
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>
                )}
            </div>
            <Block className="grid gap-3">
                <h2 className="text-lg font-semibold">
                    Người dự thi ({participants.length})
                </h2>
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[40rem] text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-2 pr-3 font-medium">
                                    Người dự thi
                                </th>
                                <th className="py-2 pr-3 font-medium">
                                    Bắt đầu
                                </th>
                                <th className="py-2 pr-3 font-medium">
                                    Trạng thái
                                </th>
                                <th className="py-2 pr-3 text-right font-medium">
                                    Điểm
                                </th>
                                <th className="py-2 pr-3 text-right font-medium">
                                    Hạng
                                </th>
                                {teacher && (
                                    <th className="py-2 text-right font-medium">
                                        Rời tab
                                    </th>
                                )}
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-border">
                            {participants.map((p) => (
                                <tr key={p.id}>
                                    <td className="py-2 pr-3">{p.name}</td>
                                    <td className="py-2 pr-3 tabular-nums">
                                        {vnTime(p.started_at)}
                                    </td>
                                    <td className="py-2 pr-3">
                                        {p.status}
                                        {p.reason && (
                                            <span className="text-muted-foreground">
                                                {' '}
                                                · {p.reason}
                                            </span>
                                        )}
                                    </td>
                                    <td className="py-2 pr-3 text-right tabular-nums">
                                        {p.percent === null
                                            ? '—'
                                            : `${p.percent}%`}
                                    </td>
                                    <td className="py-2 pr-3 text-right tabular-nums">
                                        {p.rank ??
                                            (event.status === 'DA_CONG_BO'
                                                ? 'Không xếp hạng'
                                                : '—')}
                                    </td>
                                    {teacher && (
                                        <td className="py-2 text-right tabular-nums">
                                            {p.tab_leaves
                                                ? `${p.tab_leaves} lần · ${p.tab_leave_seconds}s`
                                                : '0'}
                                        </td>
                                    )}
                                </tr>
                            ))}
                            {participants.length === 0 && (
                                <tr>
                                    <td
                                        colSpan={teacher ? 6 : 5}
                                        className="py-6 text-center text-muted-foreground"
                                    >
                                        Chưa có ai làm bài.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            </Block>
            {analytics && (
                <AnalyticsPanel
                    data={analytics}
                    exportUrl={actions.export_url}
                />
            )}
        </Page>
    );
}
