import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    scope: 'teacher' | 'academic';
    papers: { id: number; title: string; question_count: number }[];
    courses: { id: number; title: string }[];
    event: {
        title: string;
        quiz_id: number;
        duration_minutes: number;
        opens_at: string;
        closes_at: string;
        publish_at: string | null;
        audience: 'CONG_KHAI' | 'KHOA';
        requires_registration: boolean;
        course_ids: number[];
    } | null;
    submit_url: string;
    method: 'post' | 'put';
    index_url: string;
};

/** Tạo/sửa kỳ thi ở trạng thái Nháp (KT-1, A6). Giờ nhập theo giờ Việt Nam. */
export default function ExamForm({
    papers,
    courses,
    event,
    submit_url,
    method,
    index_url,
}: Props) {
    const [audience, setAudience] = useState(event?.audience ?? 'CONG_KHAI');

    return (
        <Page
            title={event ? 'Sửa kỳ thi (Nháp)' : 'Tạo kỳ thi'}
            eyebrow={
                <Link href={index_url} className="hover:underline">
                    Kỳ thi thử
                </Link>
            }
        >
            <Head title={event ? 'Sửa kỳ thi' : 'Tạo kỳ thi'} />
            <Block className="max-w-3xl">
                <Form
                    action={submit_url}
                    method={method}
                    className="grid gap-5"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Tên kỳ thi"
                                htmlFor="title"
                                error={errors.title}
                            >
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    maxLength={200}
                                    defaultValue={event?.title}
                                />
                            </Field>
                            <Field
                                label="Đề thi"
                                htmlFor="quiz_id"
                                error={errors.quiz_id}
                                hint={
                                    papers.length === 0
                                        ? 'Chưa có đề khả dụng.'
                                        : undefined
                                }
                            >
                                <select
                                    id="quiz_id"
                                    name="quiz_id"
                                    required
                                    defaultValue={event?.quiz_id}
                                    className={selectClass}
                                >
                                    {papers.map((p) => (
                                        <option key={p.id} value={p.id}>
                                            {p.title} ({p.question_count} câu)
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <div className="grid gap-4 sm:grid-cols-3">
                                <Field
                                    label="Giờ mở"
                                    htmlFor="opens_at"
                                    error={errors.opens_at}
                                >
                                    <Input
                                        id="opens_at"
                                        name="opens_at"
                                        type="datetime-local"
                                        required
                                        defaultValue={event?.opens_at}
                                    />
                                </Field>
                                <Field
                                    label="Giờ đóng"
                                    htmlFor="closes_at"
                                    error={errors.closes_at}
                                >
                                    <Input
                                        id="closes_at"
                                        name="closes_at"
                                        type="datetime-local"
                                        required
                                        defaultValue={event?.closes_at}
                                    />
                                </Field>
                                <Field
                                    label="Thời lượng (phút)"
                                    htmlFor="duration_minutes"
                                    error={errors.duration_minutes}
                                >
                                    <Input
                                        id="duration_minutes"
                                        name="duration_minutes"
                                        type="number"
                                        min={1}
                                        max={600}
                                        required
                                        defaultValue={
                                            event?.duration_minutes ?? 60
                                        }
                                    />
                                </Field>
                            </div>
                            <Field
                                label="Đối tượng"
                                htmlFor="audience"
                                error={errors.audience}
                            >
                                <select
                                    id="audience"
                                    name="audience"
                                    value={audience}
                                    onChange={(e) =>
                                        setAudience(
                                            e.target.value as
                                                | 'CONG_KHAI'
                                                | 'KHOA',
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="CONG_KHAI">
                                        Công khai (mọi tài khoản)
                                    </option>
                                    <option value="KHOA">
                                        Học viên các khóa chỉ định
                                    </option>
                                </select>
                            </Field>
                            {audience === 'KHOA' && (
                                <fieldset className="grid gap-2">
                                    <legend className="text-sm font-medium">
                                        Khóa chỉ định
                                    </legend>
                                    <div className="grid max-h-56 gap-1.5 overflow-y-auto rounded-lg border border-border p-3 sm:grid-cols-2">
                                        {courses.map((c) => (
                                            <label
                                                key={c.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="course_ids[]"
                                                    value={c.id}
                                                    defaultChecked={event?.course_ids.includes(
                                                        c.id,
                                                    )}
                                                    className="size-4 accent-sky-600"
                                                />
                                                <span className="wrap-anywhere">
                                                    {c.title}
                                                </span>
                                            </label>
                                        ))}
                                    </div>
                                    {errors.course_ids && (
                                        <p className="text-sm text-destructive">
                                            {errors.course_ids}
                                        </p>
                                    )}
                                </fieldset>
                            )}
                            <label className="flex items-center gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="requires_registration"
                                    value="1"
                                    defaultChecked={
                                        event?.requires_registration
                                    }
                                    className="size-4 accent-sky-600"
                                />
                                Bắt buộc đăng ký trước khi làm bài
                            </label>
                            <Field
                                label="Hẹn giờ công bố (tùy chọn)"
                                htmlFor="publish_at"
                                error={errors.publish_at}
                                hint="Để trống: công bố ngay khi kỳ đóng và mọi lượt đã chốt."
                            >
                                <Input
                                    id="publish_at"
                                    name="publish_at"
                                    type="datetime-local"
                                    defaultValue={
                                        event?.publish_at ?? undefined
                                    }
                                />
                            </Field>
                            <div className="flex gap-2">
                                <Button disabled={processing}>
                                    {processing ? 'Đang lưu…' : 'Lưu nháp'}
                                </Button>
                                <Button variant="ghost" asChild>
                                    <Link href={index_url}>Hủy</Link>
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Block>
        </Page>
    );
}
