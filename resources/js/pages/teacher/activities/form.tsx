import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import teacher from '@/routes/teacher';

type Props = {
    activity: {
        id: number;
        course_id: number;
        title: string;
        question_count: number;
        draw_mode: string;
        seconds_per_question: number;
        bank_ids: number[];
        update_url: string;
    } | null;
    default_course_id: number | null;
    courses: { id: number; title: string }[];
    banks: { id: number; name: string; course_id: number; questions: number }[];
    draw_modes: { value: string; label: string }[];
    store_url: string;
};

/** MH-GV-15: tạo/sửa hoạt động — chủ đề, ngân hàng thường, N câu, kiểu rút, giờ mỗi câu Try Hard (CR-153). */
export default function ActivityForm({
    activity,
    default_course_id,
    courses,
    banks,
    draw_modes,
    store_url,
}: Props) {
    const [courseId, setCourseId] = useState<number | null>(
        activity?.course_id ?? default_course_id ?? courses[0]?.id ?? null,
    );
    const [chosen, setChosen] = useState(new Set(activity?.bank_ids ?? []));
    const courseBanks = banks.filter((b) => b.course_id === courseId);
    const available = courseBanks
        .filter((b) => chosen.has(b.id))
        .reduce((n, b) => n + b.questions, 0);

    return (
        <Page
            title={activity ? activity.title : 'Tạo hoạt động luyện tập'}
            eyebrow={
                <Link
                    href={teacher.activities.index()}
                    className="hover:underline"
                >
                    Hoạt động luyện tập
                </Link>
            }
            className="max-w-3xl"
        >
            <Head title={activity ? 'Sửa hoạt động' : 'Tạo hoạt động'} />
            <Form
                action={activity ? activity.update_url : store_url}
                method={activity ? 'put' : 'post'}
                transform={(data) => ({
                    ...data,
                    course_id: courseId,
                    bank_ids: [...chosen],
                })}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <Block className="grid gap-4">
                        <Field
                            label="Khóa học"
                            htmlFor="course_id"
                            error={errors.course_id}
                        >
                            <select
                                id="course_id"
                                className={selectClass}
                                value={courseId ?? ''}
                                disabled={activity !== null}
                                onChange={(e) => {
                                    setCourseId(Number(e.target.value));
                                    setChosen(new Set());
                                }}
                            >
                                {courses.map((c) => (
                                    <option key={c.id} value={c.id}>
                                        {c.title}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Chủ đề"
                            htmlFor="title"
                            error={errors.title}
                        >
                            <Input
                                id="title"
                                name="title"
                                required
                                maxLength={200}
                                defaultValue={activity?.title ?? ''}
                            />
                        </Field>
                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">
                                Ngân hàng câu hỏi (thường)
                            </legend>
                            {courseBanks.map((b) => (
                                <label
                                    key={b.id}
                                    className="flex items-center gap-2 text-sm"
                                >
                                    <Checkbox
                                        checked={chosen.has(b.id)}
                                        onCheckedChange={(v) => {
                                            const next = new Set(chosen);
                                            if (v) {
                                                next.add(b.id);
                                            } else {
                                                next.delete(b.id);
                                            }
                                            setChosen(next);
                                        }}
                                    />
                                    {b.name}{' '}
                                    <span className="text-muted-foreground">
                                        · {b.questions} câu đang dùng
                                    </span>
                                </label>
                            ))}
                            {courseBanks.length === 0 && (
                                <p className="text-sm text-muted-foreground">
                                    Khóa chưa có ngân hàng thường.
                                </p>
                            )}
                            {errors.bank_ids && (
                                <p className="text-sm text-destructive">
                                    {errors.bank_ids}
                                </p>
                            )}
                        </fieldset>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field
                                label="Số câu mỗi phiên (N)"
                                htmlFor="question_count"
                                error={errors.question_count}
                                hint={`Hiện có ${available} câu khả dụng; thiếu thì lấy hết.`}
                            >
                                <Input
                                    id="question_count"
                                    name="question_count"
                                    type="number"
                                    min={1}
                                    max={50}
                                    required
                                    defaultValue={
                                        activity?.question_count ?? 10
                                    }
                                />
                            </Field>
                            <Field
                                label="Kiểu rút"
                                htmlFor="draw_mode"
                                error={errors.draw_mode}
                            >
                                <select
                                    id="draw_mode"
                                    name="draw_mode"
                                    className={selectClass}
                                    defaultValue={
                                        activity?.draw_mode ?? 'NGAU_NHIEN'
                                    }
                                >
                                    {draw_modes.map((m) => (
                                        <option key={m.value} value={m.value}>
                                            {m.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Giây mỗi câu (Try Hard)"
                                htmlFor="seconds_per_question"
                                error={errors.seconds_per_question}
                            >
                                <Input
                                    id="seconds_per_question"
                                    name="seconds_per_question"
                                    type="number"
                                    min={10}
                                    max={300}
                                    required
                                    defaultValue={
                                        activity?.seconds_per_question ?? 30
                                    }
                                />
                            </Field>
                        </div>
                        <p className="text-xs text-muted-foreground">
                            "Ưu tiên điểm yếu" rút trước câu học viên sai từ 2
                            lần trong 10 phiên gần nhất của khóa. Phiên đang làm
                            giữ bản chụp cũ khi bạn sửa.
                        </p>
                        <Button
                            type="submit"
                            disabled={processing || chosen.size === 0}
                            className="justify-self-start"
                        >
                            {activity ? 'Lưu' : 'Tạo hoạt động'}
                        </Button>
                    </Block>
                )}
            </Form>
        </Page>
    );
}
