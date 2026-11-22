import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import { ArrowDown, ArrowUp, Trash2 } from 'lucide-react';
import { useEffect, useState } from 'react';
import { ConfirmAction, MoreActions } from '@/components/confirm-action';
import type { ConfirmSpec } from '@/components/confirm-action';
import { lessonTitleFinder, ReviewHistory } from '@/components/course-tree';
import {
    Block,
    Field,
    Page,
    selectClass,
    textareaClass,
} from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import coursePreview from '@/routes/course-versions';
import teacher from '@/routes/teacher';
import type {
    ChecklistItem,
    CourseDetail,
    LessonNode,
    Option,
    Review,
    SectionNode,
    VersionInfo,
} from '@/types/course';

type BankQuestions = {
    id: number;
    name: string;
    questions: { id: number; stem: string }[];
}[];

type Props = {
    course: CourseDetail;
    version: VersionInfo | null;
    sections: SectionNode[];
    reviews: Review[];
    checklist: ChecklistItem[];
    bankQuestions: BankQuestions;
    can: {
        edit: boolean;
        submit: boolean;
        withdraw: boolean;
        reopen: boolean;
        createDraft: boolean;
        preview: boolean;
    };
    lessonTypes: Option[];
};

const AUTOSAVE_MS = 30_000;

/** MH-GV-03..06: trình soạn bản nháp (QT-03 A2–A4). */
export default function CourseEditor(props: Props) {
    const { course, version, sections, reviews, checklist, can } = props;
    const lessons = sections.flatMap((s) => s.lessons);
    const [selected, setSelected] = useState<number | null>(
        lessons[0]?.id ?? null,
    );
    const current = lessons.find((l) => l.id === selected) ?? null;
    const title = lessonTitleFinder(sections);

    const more: ConfirmSpec[] = [];
    if (can.withdraw && version) {
        more.push({
            form: teacher.courseVersions.withdraw.form(version.id),
            label: 'Rút về nháp',
            title: 'Rút bản về nháp?',
            description: 'Bản rời hàng chờ duyệt; bạn sửa tiếp rồi gửi lại.',
            confirmLabel: 'Rút về nháp',
            variant: 'destructive',
        });
    }

    return (
        <Page
            title={course.title}
            description={
                version
                    ? `Bản ${version.version_no} · ${version.status_label}${course.due_date ? ` · hạn ${course.due_date}` : ''}`
                    : 'Chưa có bản'
            }
            actions={
                <div className="flex flex-wrap gap-2">
                    {can.preview && version && (
                        <Button variant="outline" asChild>
                            <Link href={coursePreview.preview(version.id)}>
                                Xem trước
                            </Link>
                        </Button>
                    )}
                    {can.submit && version && (
                        <ConfirmAction
                            form={teacher.courseVersions.submit.form(
                                version.id,
                            )}
                            label="Gửi duyệt"
                            title="Gửi bản này để Giáo vụ duyệt?"
                            description="Khi chờ duyệt bạn không sửa được; muốn sửa hãy rút về nháp."
                            confirmLabel="Gửi duyệt"
                        />
                    )}
                    {can.reopen && version && (
                        <ConfirmAction
                            form={teacher.courseVersions.reopen.form(
                                version.id,
                            )}
                            label="Bắt đầu sửa"
                            title="Đưa bản về nháp để sửa?"
                            confirmLabel="Bắt đầu sửa"
                        />
                    )}
                    {can.createDraft && (
                        <ConfirmAction
                            form={teacher.courses.versions.store.form(
                                course.id,
                            )}
                            label="Tạo bản nháp mới"
                            title="Tạo bản nháp mới từ bản đang công bố?"
                            description="Học viên vẫn học bản hiện tại cho tới khi bản mới được công bố."
                            confirmLabel="Tạo bản nháp"
                        />
                    )}
                    <MoreActions actions={more} />
                </div>
            }
        >
            <Head title={`Soạn – ${course.title}`} />

            {course.overdue && (
                <Block tone="accent">
                    <p className="font-semibold">
                        Quá hạn soạn ({course.due_date}).
                    </p>
                </Block>
            )}
            {version?.status === 'TRA_LAI' && reviews.length > 0 && (
                <Block tone="accent">
                    <p className="font-semibold">
                        Bị trả lại: {reviews[reviews.length - 1].reason}
                    </p>
                    <p className="text-sm">
                        Bấm “Bắt đầu sửa” để sửa rồi gửi lại.
                    </p>
                </Block>
            )}
            {version?.status === 'CHO_DUYET' && (
                <Block tone="surface">
                    <p>Bản đang chờ duyệt: hãy rút về nháp để sửa.</p>
                </Block>
            )}

            {can.edit && checklist.length > 0 && (
                <Block>
                    <h2 className="mb-2 text-lg font-bold">
                        Cần hoàn thiện trước khi gửi duyệt
                    </h2>
                    <ul className="grid gap-1 text-sm">
                        {checklist.map((c, i) => (
                            <li key={i}>
                                {c.lesson_id ? (
                                    <button
                                        type="button"
                                        className="text-left underline"
                                        onClick={() => setSelected(c.lesson_id)}
                                    >
                                        {c.message}
                                    </button>
                                ) : (
                                    c.message
                                )}
                            </li>
                        ))}
                    </ul>
                </Block>
            )}

            <div className="grid gap-6 lg:grid-cols-[1fr_2fr]">
                <div className="grid content-start gap-4">
                    <Outline
                        sections={sections}
                        canEdit={can.edit}
                        selected={selected}
                        onSelect={setSelected}
                        lessonTypes={props.lessonTypes}
                        versionId={version?.id ?? null}
                    />
                    <CoverForm course={course} />
                </div>
                <div className="grid content-start gap-4">
                    {current ? (
                        <LessonEditor
                            key={current.id}
                            lesson={current}
                            canEdit={can.edit}
                            banks={props.bankQuestions}
                        />
                    ) : (
                        <Block>
                            <p className="text-muted-foreground">
                                Chọn một bài để soạn.
                            </p>
                        </Block>
                    )}
                    <Block>
                        <h2 className="mb-3 text-lg font-bold">
                            Lịch sử duyệt
                        </h2>
                        <ReviewHistory reviews={reviews} lessonTitle={title} />
                    </Block>
                </div>
            </div>
        </Page>
    );
}

function MoveButtons({ kind, id }: { kind: 'section' | 'lesson'; id: number }) {
    const move = (direction: 'up' | 'down') =>
        router.post(
            teacher.content.move.url({ kind, id }),
            { direction },
            { preserveScroll: true },
        );

    return (
        <>
            <Button
                size="icon"
                variant="ghost"
                aria-label="Lên"
                onClick={() => move('up')}
            >
                <ArrowUp />
            </Button>
            <Button
                size="icon"
                variant="ghost"
                aria-label="Xuống"
                onClick={() => move('down')}
            >
                <ArrowDown />
            </Button>
        </>
    );
}

function RemoveButton({
    kind,
    id,
    label,
}: {
    kind: 'section' | 'lesson' | 'resource';
    id: number;
    label: string;
}) {
    const [open, setOpen] = useState(false);

    return (
        <>
            <Button
                size="icon"
                variant="ghost"
                aria-label={`Gỡ ${label}`}
                onClick={() => setOpen(true)}
            >
                <Trash2 />
            </Button>
            {open && (
                <ConfirmInline
                    text={`Gỡ ${label} khỏi bản nháp?`}
                    onCancel={() => setOpen(false)}
                    onConfirm={() =>
                        router.delete(
                            teacher.content.destroy.url({ kind, id }),
                            {
                                preserveScroll: true,
                                onFinish: () => setOpen(false),
                            },
                        )
                    }
                />
            )}
        </>
    );
}

function ConfirmInline({
    text,
    onConfirm,
    onCancel,
}: {
    text: string;
    onConfirm: () => void;
    onCancel: () => void;
}) {
    return (
        <span className="flex items-center gap-2 text-sm">
            {text}
            <Button size="sm" variant="destructive" onClick={onConfirm}>
                Gỡ
            </Button>
            <Button size="sm" variant="outline" onClick={onCancel}>
                Hủy
            </Button>
        </span>
    );
}

function Outline({
    sections,
    canEdit,
    selected,
    onSelect,
    lessonTypes,
    versionId,
}: {
    sections: SectionNode[];
    canEdit: boolean;
    selected: number | null;
    onSelect: (id: number) => void;
    lessonTypes: Option[];
    versionId: number | null;
}) {
    return (
        <Block>
            <h2 className="mb-3 text-lg font-bold">Chương và bài</h2>
            {sections.length === 0 && (
                <p className="mb-3 text-sm text-muted-foreground">
                    Chưa có chương nào.
                </p>
            )}
            <div className="grid gap-4">
                {sections.map((s) => (
                    <div key={s.id} className="rounded-md border">
                        <div className="flex flex-wrap items-center gap-1 bg-surface px-3 py-2 text-surface-ink">
                            <SectionTitle section={s} canEdit={canEdit} />
                            {canEdit && (
                                <>
                                    <MoveButtons kind="section" id={s.id} />
                                    <RemoveButton
                                        kind="section"
                                        id={s.id}
                                        label="chương"
                                    />
                                </>
                            )}
                        </div>
                        <ol>
                            {s.lessons.map((l) => (
                                <li
                                    key={l.id}
                                    className={`flex flex-wrap items-center gap-1 border-t px-3 py-1 ${selected === l.id ? 'bg-secondary' : ''}`}
                                >
                                    <button
                                        type="button"
                                        className="flex-1 text-left text-sm"
                                        onClick={() => onSelect(l.id)}
                                    >
                                        Bài {l.number}. {l.title}
                                        <span className="ml-1 text-xs text-muted-foreground">
                                            {l.type_label}
                                            {l.is_required
                                                ? ''
                                                : ' · không bắt buộc'}
                                        </span>
                                    </button>
                                    {canEdit && (
                                        <>
                                            <MoveButtons
                                                kind="lesson"
                                                id={l.id}
                                            />
                                            <RemoveButton
                                                kind="lesson"
                                                id={l.id}
                                                label="bài"
                                            />
                                        </>
                                    )}
                                </li>
                            ))}
                        </ol>
                        {canEdit && (
                            <Form
                                {...teacher.sections.lessons.store.form(s.id)}
                                options={{ preserveScroll: true }}
                                resetOnSuccess
                                className="flex flex-wrap gap-2 border-t p-3"
                            >
                                {({ processing, errors }) => (
                                    <>
                                        <Input
                                            name="title"
                                            placeholder="Tên bài mới"
                                            aria-label="Tên bài mới"
                                            required
                                            className="min-w-40 flex-1"
                                        />
                                        <select
                                            name="type"
                                            aria-label="Loại bài"
                                            className={selectClass}
                                        >
                                            {lessonTypes.map((t) => (
                                                <option
                                                    key={t.value}
                                                    value={t.value}
                                                >
                                                    {t.label}
                                                </option>
                                            ))}
                                        </select>
                                        <Button size="sm" disabled={processing}>
                                            Thêm bài
                                        </Button>
                                        {errors.title && (
                                            <p className="w-full text-sm text-destructive">
                                                {errors.title}
                                            </p>
                                        )}
                                    </>
                                )}
                            </Form>
                        )}
                    </div>
                ))}
            </div>
            {canEdit && versionId && (
                <Form
                    {...teacher.courseVersions.sections.store.form(versionId)}
                    options={{ preserveScroll: true }}
                    resetOnSuccess
                    className="mt-4 flex gap-2"
                >
                    {({ processing }) => (
                        <>
                            <Input
                                name="title"
                                placeholder="Tên chương mới"
                                aria-label="Tên chương mới"
                                required
                            />
                            <Button variant="outline" disabled={processing}>
                                Thêm chương
                            </Button>
                        </>
                    )}
                </Form>
            )}
        </Block>
    );
}

function SectionTitle({
    section,
    canEdit,
}: {
    section: SectionNode;
    canEdit: boolean;
}) {
    const [editing, setEditing] = useState(false);

    if (!canEdit || !editing) {
        return (
            <button
                type="button"
                className="flex-1 text-left font-semibold"
                onClick={() => canEdit && setEditing(true)}
            >
                {section.title}
            </button>
        );
    }

    return (
        <Form
            {...teacher.sections.update.form(section.id)}
            options={{ preserveScroll: true }}
            onSuccess={() => setEditing(false)}
            className="flex flex-1 gap-2"
        >
            <Input
                name="title"
                defaultValue={section.title}
                aria-label="Tên chương"
                className="bg-background text-foreground"
                required
            />
            <Button size="sm" variant="secondary">
                Lưu
            </Button>
        </Form>
    );
}

function CoverForm({ course }: { course: CourseDetail }) {
    return (
        <Block>
            <h2 className="mb-3 text-lg font-bold">Ảnh bìa</h2>
            {course.cover && (
                <img
                    src={course.cover}
                    alt="Ảnh bìa"
                    className="mb-3 aspect-video w-full rounded object-cover"
                />
            )}
            <Form
                {...teacher.courses.cover.form(course.id)}
                options={{ preserveScroll: true }}
                className="grid gap-2"
            >
                {({ processing, errors }) => (
                    <>
                        <Input
                            type="file"
                            name="cover"
                            accept="image/*"
                            aria-label="Chọn ảnh bìa"
                            required
                        />
                        {errors.cover && (
                            <p className="text-sm text-destructive">
                                {errors.cover}
                            </p>
                        )}
                        <Button
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            Tải ảnh bìa (tối đa 5 MB)
                        </Button>
                    </>
                )}
            </Form>
        </Block>
    );
}

/** Soạn một bài: tự lưu mỗi 30 giây khi có thay đổi, hỏi trước khi rời trang (MH-GV-04). */
function LessonEditor({
    lesson,
    canEdit,
    banks,
}: {
    lesson: LessonNode;
    canEdit: boolean;
    banks: BankQuestions;
}) {
    const form = useForm({
        title: lesson.title,
        objectives: lesson.objectives ?? '',
        content: lesson.content ?? '',
        is_required: lesson.is_required,
        is_preview: lesson.is_preview,
        is_final_test: lesson.is_final_test,
    });
    const [savedAt, setSavedAt] = useState<Date | null>(null);
    const save = () =>
        form.patch(teacher.lessons.update.url(lesson.id), {
            preserveScroll: true,
            preserveState: true,
            onSuccess: () => {
                form.setDefaults();
                setSavedAt(new Date());
            },
        });

    useEffect(() => {
        if (!canEdit) {
            return;
        }

        const timer = window.setInterval(() => {
            if (form.isDirty && !form.processing) {
                save();
            }
        }, AUTOSAVE_MS);

        return () => window.clearInterval(timer);
    });

    useEffect(() => {
        if (!form.isDirty) {
            return;
        }

        const onBeforeUnload = (e: BeforeUnloadEvent) => e.preventDefault();
        const off = router.on('before', (event) => {
            const visit = event.detail.visit;

            if (
                visit.method === 'get' &&
                !window.confirm('Bài có thay đổi chưa lưu. Rời trang?')
            ) {
                event.preventDefault();
            }
        });
        window.addEventListener('beforeunload', onBeforeUnload);

        return () => {
            window.removeEventListener('beforeunload', onBeforeUnload);
            off();
        };
    }, [form.isDirty]);

    const flag = (
        name: 'is_required' | 'is_preview' | 'is_final_test',
        label: string,
    ) => (
        <label className="flex items-center gap-2 text-sm">
            <input
                type="checkbox"
                checked={form.data[name]}
                disabled={!canEdit}
                onChange={(e) => form.setData(name, e.target.checked)}
            />
            {label}
        </label>
    );

    return (
        <Block>
            <div className="mb-4 flex flex-wrap items-baseline justify-between gap-2">
                <h2 className="text-lg font-bold">
                    Bài {lesson.number} · {lesson.type_label}
                </h2>
                <span className="text-xs text-muted-foreground">
                    {form.isDirty
                        ? 'Có thay đổi chưa lưu'
                        : savedAt
                          ? `Đã lưu ${savedAt.toLocaleTimeString('vi-VN')}`
                          : ''}
                </span>
            </div>
            <form
                className="grid gap-4"
                onSubmit={(e) => {
                    e.preventDefault();
                    save();
                }}
            >
                <Field
                    label="Tên bài"
                    htmlFor="l-title"
                    error={form.errors.title}
                >
                    <Input
                        id="l-title"
                        value={form.data.title}
                        disabled={!canEdit}
                        onChange={(e) => form.setData('title', e.target.value)}
                    />
                </Field>
                <Field
                    label="Mục tiêu bài"
                    htmlFor="l-obj"
                    error={form.errors.objectives}
                >
                    <textarea
                        id="l-obj"
                        className={textareaClass}
                        value={form.data.objectives}
                        disabled={!canEdit}
                        onChange={(e) =>
                            form.setData('objectives', e.target.value)
                        }
                    />
                </Field>
                {lesson.type === 'BAI_DOC' && (
                    <Field
                        label="Nội dung (HTML)"
                        htmlFor="l-content"
                        error={form.errors.content}
                        hint="Hệ thống lọc thẻ không an toàn khi lưu."
                    >
                        <textarea
                            id="l-content"
                            className={`${textareaClass} min-h-64 font-mono`}
                            value={form.data.content}
                            disabled={!canEdit}
                            onChange={(e) =>
                                form.setData('content', e.target.value)
                            }
                        />
                    </Field>
                )}
                <div className="flex flex-wrap gap-4">
                    {flag('is_required', 'Bắt buộc')}
                    {flag('is_preview', 'Cho học thử')}
                    {lesson.type === 'QUIZ' &&
                        flag('is_final_test', 'Bài kiểm tra cuối')}
                </div>
                {(form.errors as Record<string, string>).general && (
                    <p role="alert" className="text-sm text-destructive">
                        {(form.errors as Record<string, string>).general}
                    </p>
                )}
                {canEdit && (
                    <Button
                        disabled={form.processing || !form.isDirty}
                        className="justify-self-start"
                    >
                        Lưu bài
                    </Button>
                )}
            </form>

            {lesson.type === 'VIDEO' && (
                <UploadForm
                    action={teacher.lessons.video.form(lesson.id)}
                    name="video"
                    accept="video/mp4"
                    canEdit={canEdit}
                    status={
                        lesson.has_video
                            ? `Đã có video${lesson.video_duration_ms ? ` · ${Math.round(lesson.video_duration_ms / 1000)} giây` : ' · đang đọc thời lượng'}`
                            : 'Chưa có video'
                    }
                    label="Tải MP4 (tối đa 500 MB)"
                />
            )}
            {lesson.type === 'PDF' && (
                <UploadForm
                    action={teacher.lessons.pdf.form(lesson.id)}
                    name="pdf"
                    accept="application/pdf"
                    canEdit={canEdit}
                    status={lesson.has_pdf ? 'Đã có tệp PDF' : 'Chưa có PDF'}
                    label="Tải PDF (tối đa 50 MB)"
                />
            )}

            <div className="mt-6 border-t pt-4">
                <h3 className="mb-2 font-semibold">Tài nguyên kèm bài</h3>
                <ul className="mb-2 grid gap-1 text-sm">
                    {lesson.resources.map((r) => (
                        <li key={r.id} className="flex items-center gap-2">
                            {r.title}
                            {canEdit && (
                                <RemoveButton
                                    kind="resource"
                                    id={r.id}
                                    label="tài nguyên"
                                />
                            )}
                        </li>
                    ))}
                    {lesson.resources.length === 0 && (
                        <li className="text-muted-foreground">Chưa có.</li>
                    )}
                </ul>
                {canEdit && (
                    <Form
                        {...teacher.lessons.resources.store.form(lesson.id)}
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-wrap gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    type="file"
                                    name="file"
                                    aria-label="Tệp tài nguyên"
                                    required
                                    className="max-w-xs"
                                />
                                <Input
                                    name="title"
                                    placeholder="Tên hiển thị"
                                    aria-label="Tên hiển thị"
                                    className="max-w-xs"
                                />
                                <Button
                                    size="sm"
                                    variant="outline"
                                    disabled={processing}
                                >
                                    Thêm tài nguyên
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
            </div>

            {lesson.type === 'QUIZ' && lesson.quiz && (
                <QuizEditor lesson={lesson} canEdit={canEdit} banks={banks} />
            )}
        </Block>
    );
}

function UploadForm({
    action,
    name,
    accept,
    canEdit,
    status,
    label,
}: {
    action: ReturnType<typeof teacher.lessons.video.form>;
    name: string;
    accept: string;
    canEdit: boolean;
    status: string;
    label: string;
}) {
    return (
        <div className="mt-6 border-t pt-4">
            <p className="mb-2 text-sm font-medium">{status}</p>
            {canEdit && (
                <Form
                    {...action}
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap gap-2"
                >
                    {({ processing, errors, progress }) => (
                        <>
                            <Input
                                type="file"
                                name={name}
                                accept={accept}
                                aria-label={label}
                                required
                                className="max-w-xs"
                            />
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                            >
                                {processing && progress
                                    ? `${progress.percentage}%`
                                    : label}
                            </Button>
                            {errors[name] && (
                                <p className="w-full text-sm text-destructive">
                                    {errors[name]}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            )}
        </div>
    );
}

function QuizEditor({
    lesson,
    canEdit,
    banks,
}: {
    lesson: LessonNode;
    canEdit: boolean;
    banks: BankQuestions;
}) {
    const quiz = lesson.quiz!;
    const [picked, setPicked] = useState<number[]>(
        quiz.questions.map((q) => q.id),
    );
    const [errors, setErrors] = useState<Record<string, string>>({});
    const toggle = (id: number) =>
        setPicked((p) =>
            p.includes(id) ? p.filter((x) => x !== id) : [...p, id],
        );

    return (
        <div className="mt-6 grid gap-4 border-t pt-4">
            <h3 className="font-semibold">Cấu hình quiz</h3>
            <Form
                {...teacher.lessons.quiz.update.form(lesson.id)}
                options={{ preserveScroll: true }}
                className="grid gap-3 md:grid-cols-4"
            >
                {({ processing, errors: e }) => (
                    <>
                        <Field
                            label="Điểm đạt (%)"
                            htmlFor="pass_percent"
                            error={e.pass_percent}
                        >
                            <Input
                                id="pass_percent"
                                name="pass_percent"
                                type="number"
                                min={1}
                                max={100}
                                defaultValue={quiz.pass_percent ?? ''}
                                disabled={!canEdit}
                            />
                        </Field>
                        <Field
                            label="Thời lượng (phút)"
                            htmlFor="duration_minutes"
                            error={e.duration_minutes}
                        >
                            <Input
                                id="duration_minutes"
                                name="duration_minutes"
                                type="number"
                                min={1}
                                max={180}
                                defaultValue={quiz.duration_minutes ?? ''}
                                disabled={!canEdit}
                            />
                        </Field>
                        <Field
                            label="Số lượt"
                            htmlFor="max_attempts"
                            error={e.max_attempts}
                        >
                            <Input
                                id="max_attempts"
                                name="max_attempts"
                                type="number"
                                min={1}
                                max={10}
                                defaultValue={quiz.max_attempts ?? ''}
                                disabled={!canEdit}
                            />
                        </Field>
                        {canEdit && (
                            <Button
                                size="sm"
                                variant="outline"
                                disabled={processing}
                                className="self-end"
                            >
                                Lưu cấu hình
                            </Button>
                        )}
                    </>
                )}
            </Form>
            <div>
                <h4 className="mb-2 text-sm font-semibold">
                    Câu hỏi ({picked.length} đã chọn)
                </h4>
                {banks.length === 0 && (
                    <p className="text-sm text-muted-foreground">
                        Khóa chưa có ngân hàng câu hỏi.
                    </p>
                )}
                <div className="grid max-h-80 gap-3 overflow-y-auto">
                    {banks.map((b) => (
                        <fieldset key={b.id} className="rounded-md border p-3">
                            <legend className="px-1 text-sm font-medium">
                                {b.name}
                            </legend>
                            {b.questions.map((q) => (
                                <label
                                    key={q.id}
                                    className="flex items-start gap-2 py-0.5 text-sm"
                                >
                                    <input
                                        type="checkbox"
                                        checked={picked.includes(q.id)}
                                        disabled={!canEdit}
                                        onChange={() => toggle(q.id)}
                                    />
                                    {q.stem}
                                </label>
                            ))}
                        </fieldset>
                    ))}
                </div>
                {Object.values(errors).map((m) => (
                    <p
                        key={m}
                        role="alert"
                        className="mt-2 text-sm text-destructive"
                    >
                        {m}
                    </p>
                ))}
                {canEdit && (
                    <Button
                        size="sm"
                        className="mt-3"
                        onClick={() =>
                            router.put(
                                teacher.lessons.quiz.questions.url(lesson.id),
                                { question_ids: picked },
                                {
                                    preserveScroll: true,
                                    onError: setErrors,
                                    onSuccess: () => setErrors({}),
                                },
                            )
                        }
                    >
                        Lưu danh sách câu
                    </Button>
                )}
            </div>
        </div>
    );
}
