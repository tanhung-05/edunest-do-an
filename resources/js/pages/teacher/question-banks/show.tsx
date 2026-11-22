import { Form, Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import {
    Block,
    Field,
    Page,
    selectClass,
    textareaClass,
} from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import teacher from '@/routes/teacher';
import type { IdName, Option } from '@/types/course';

type QuestionRow = {
    id: number;
    type: string;
    type_label: string;
    difficulty: string;
    difficulty_label: string;
    stem: string;
    points: string;
    hint: string | null;
    explanation: string | null;
    status: string;
    status_label: string;
    source: 'GIAO_VIEN' | 'AI';
    ai_ever_approved: boolean;
    image: string | null;
    tag_ids: number[];
    tags: string[];
    options: { content: string; is_correct: boolean }[];
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Filters = {
    type: string;
    difficulty: string;
    tag: string;
    q: string;
    status: string;
};

type Props = {
    course: { id: number; title: string };
    bank: {
        id: number;
        name: string;
        is_confidential: boolean;
        import_url: string;
        template_url: string;
        confidential_url: string;
    };
    import_errors: { row: number; message: string }[];
    ai: {
        enabled: boolean;
        simulated: boolean;
        max: number;
        generate_url: string;
        lessons: IdName[];
    };
    questions: Paginated<QuestionRow>;
    filters: Filters;
    options: { types: Option[]; difficulties: Option[]; tags: IdName[] };
};

const statusOptions: Option[] = [
    { value: 'NHAP', label: 'Nháp (AI)' },
    { value: 'DANG_DUNG', label: 'Đang dùng' },
    { value: 'NGUNG_DUNG', label: 'Ngừng dùng' },
];

/** MH-GV-08: câu hỏi trong ngân hàng, lọc trên URL (AC-04-60). */
export default function QuestionBankShow({
    course,
    bank,
    questions,
    filters,
    options,
    import_errors,
    ai,
}: Props) {
    const [f, setF] = useState(filters);
    const [adding, setAdding] = useState(false);
    const [editing, setEditing] = useState<number | null>(null);

    const apply = (next: Filters) => {
        setF(next);
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== ''),
        );
        router.get(
            teacher.questionBanks.show.url(bank.id, { query }),
            {},
            { preserveState: true },
        );
    };

    return (
        <Page
            title={bank.name}
            description={`${course.title} · ${questions.total} câu`}
            actions={
                <div className="flex gap-2">
                    <Button variant="outline" asChild>
                        <Link
                            href={teacher.courses.questionBanks.index(
                                course.id,
                            )}
                        >
                            Các ngân hàng
                        </Link>
                    </Button>
                    <Button onClick={() => setAdding((v) => !v)}>
                        {adding ? 'Đóng' : 'Thêm câu'}
                    </Button>
                </div>
            }
        >
            <Head title={bank.name} />
            <div className="grid gap-4 lg:grid-cols-2">
                <Block className="grid gap-3">
                    <h2 className="font-semibold">Nhập từ Excel/CSV</h2>
                    <p className="text-sm text-muted-foreground">
                        Tối đa 500 câu mỗi lần. Có dòng lỗi thì không nhập dòng
                        nào.{' '}
                        <a
                            href={bank.template_url}
                            className="font-medium text-foreground underline underline-offset-4"
                        >
                            Tải tệp mẫu
                        </a>
                    </p>
                    <Form
                        action={bank.import_url}
                        method="post"
                        options={{ preserveScroll: true }}
                        resetOnSuccess
                        className="flex flex-wrap items-center gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Input
                                    type="file"
                                    name="file"
                                    accept=".xlsx,.csv"
                                    required
                                    aria-label="Tệp câu hỏi"
                                    className="max-w-xs"
                                />
                                <Button variant="outline" disabled={processing}>
                                    {processing ? 'Đang nhập…' : 'Nhập'}
                                </Button>
                                {errors.file && (
                                    <p className="w-full text-sm text-destructive">
                                        {errors.file}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                    {import_errors.length > 0 && (
                        <div
                            role="alert"
                            className="max-h-48 overflow-y-auto rounded-lg border border-destructive/40 bg-destructive/5 p-3 text-sm"
                        >
                            <p className="font-medium text-destructive">
                                Chưa nhập câu nào. Sửa các dòng sau rồi nhập
                                lại:
                            </p>
                            <ul className="mt-1 list-disc pl-5">
                                {import_errors.map((e, i) => (
                                    <li key={i}>
                                        Dòng {e.row}: {e.message}
                                    </li>
                                ))}
                            </ul>
                        </div>
                    )}
                </Block>
                <Block className="grid content-start gap-3">
                    <h2 className="font-semibold">Ngân hàng bảo mật</h2>
                    <p className="text-sm text-muted-foreground">
                        {bank.is_confidential
                            ? 'Đang bảo mật: chỉ dùng cho đề thi thử, không gắn vào quiz bài học.'
                            : 'Bật để dùng câu cho đề thi thử. Ngân hàng đang có câu trong quiz bài học thì không bật được.'}
                    </p>
                    <Form
                        action={bank.confidential_url}
                        method="patch"
                        options={{ preserveScroll: true }}
                        transform={() => ({
                            is_confidential: !bank.is_confidential,
                        })}
                    >
                        {({ processing, errors }) => (
                            <div className="grid justify-items-start gap-1">
                                <Button variant="outline" disabled={processing}>
                                    {bank.is_confidential
                                        ? 'Tắt bảo mật'
                                        : 'Bật bảo mật'}
                                </Button>
                                {Object.values(errors)[0] && (
                                    <p className="text-sm text-destructive">
                                        {Object.values(errors)[0]}
                                    </p>
                                )}
                            </div>
                        )}
                    </Form>
                </Block>
            </div>
            {ai.enabled && ai.lessons.length > 0 && (
                <Block className="grid gap-3">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="font-semibold">
                            AI gợi ý câu hỏi từ bài
                        </h2>
                        <span
                            className={
                                ai.simulated
                                    ? 'rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-900'
                                    : 'rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-900'
                            }
                        >
                            {ai.simulated
                                ? 'Chế độ trích xuất tự động'
                                : 'Đang dùng Gemini'}
                        </span>
                    </div>
                    {ai.simulated && (
                        <p className="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-900">
                            Chưa cấu hình khóa Gemini nên hệ thống chỉ trích câu
                            từ nội dung bài và đặt đáp án mặc định. Hãy kiểm tra
                            lại đáp án và lời giải trước khi duyệt.
                        </p>
                    )}
                    <p className="text-sm text-muted-foreground">
                        AI tạo câu Đúng/Sai ở trạng thái Nháp từ nội dung bài
                        bạn chọn. Câu nháp chưa dùng được cho quiz, luyện tập
                        hay đề thi cho tới khi bạn đọc kỹ và duyệt.
                    </p>
                    <Form
                        action={ai.generate_url}
                        method="post"
                        options={{ preserveScroll: true }}
                        className="flex flex-wrap items-end gap-2"
                    >
                        {({ processing, errors }) => (
                            <>
                                <label className="grid gap-1 text-sm">
                                    Bài nguồn
                                    <select
                                        name="lesson_id"
                                        required
                                        className="h-9 max-w-xs rounded-md border bg-background px-2"
                                    >
                                        {ai.lessons.map((l) => (
                                            <option key={l.id} value={l.id}>
                                                {l.name}
                                            </option>
                                        ))}
                                    </select>
                                </label>
                                <label className="grid gap-1 text-sm">
                                    Số câu
                                    <Input
                                        type="number"
                                        name="count"
                                        min={1}
                                        max={ai.max}
                                        defaultValue={5}
                                        className="w-24"
                                    />
                                </label>
                                <Button variant="outline" disabled={processing}>
                                    {processing ? 'Đang gợi ý…' : 'Gợi ý câu'}
                                </Button>
                                {Object.values(errors)[0] && (
                                    <p className="w-full text-sm text-destructive">
                                        {Object.values(errors)[0]}
                                    </p>
                                )}
                            </>
                        )}
                    </Form>
                </Block>
            )}
            <Block>
                <Form
                    {...teacher.questionBanks.update.form(bank.id)}
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                name="name"
                                defaultValue={bank.name}
                                aria-label="Tên ngân hàng"
                                required
                                className="max-w-sm"
                            />
                            <Button variant="outline" disabled={processing}>
                                Đổi tên
                            </Button>
                            {errors.name && (
                                <p className="w-full text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </Block>

            {adding && (
                <Block>
                    <h2 className="mb-4 text-lg font-bold">Câu hỏi mới</h2>
                    <QuestionForm
                        bankId={bank.id}
                        options={options}
                        onDone={() => setAdding(false)}
                    />
                </Block>
            )}

            <div className="flex flex-wrap gap-2">
                <Input
                    aria-label="Tìm nội dung câu"
                    placeholder="Tìm nội dung câu"
                    value={f.q}
                    onChange={(e) => setF({ ...f, q: e.target.value })}
                    onKeyDown={(e) => e.key === 'Enter' && apply(f)}
                    className="max-w-xs"
                />
                <FilterSelect
                    label="Loại"
                    value={f.type}
                    items={options.types}
                    onChange={(v) => apply({ ...f, type: v })}
                />
                <FilterSelect
                    label="Độ khó"
                    value={f.difficulty}
                    items={options.difficulties}
                    onChange={(v) => apply({ ...f, difficulty: v })}
                />
                <FilterSelect
                    label="Thẻ"
                    value={f.tag}
                    items={options.tags.map((t) => ({
                        value: String(t.id),
                        label: t.name,
                    }))}
                    onChange={(v) => apply({ ...f, tag: v })}
                />
                <FilterSelect
                    label="Trạng thái"
                    value={f.status}
                    items={statusOptions}
                    onChange={(v) => apply({ ...f, status: v })}
                />
            </div>

            <ol className="grid gap-3">
                {questions.data.length === 0 && (
                    <li className="text-muted-foreground">
                        Không có câu phù hợp.
                    </li>
                )}
                {questions.data.map((q) => (
                    <li key={q.id} className="rounded-lg border p-4">
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <div className="grid gap-1">
                                <p className="font-medium">{q.stem}</p>
                                <p className="text-xs text-muted-foreground">
                                    {q.type_label} · {q.difficulty_label} ·{' '}
                                    {q.points} điểm · {q.status_label}
                                    {q.source === 'AI' && ' · Nguồn AI'}
                                    {q.tags.length > 0 &&
                                        ` · ${q.tags.join(', ')}`}
                                </p>
                                <ul className="text-sm">
                                    {q.options.map((o, i) => (
                                        <li
                                            key={i}
                                            className={
                                                o.is_correct
                                                    ? 'font-semibold text-success'
                                                    : ''
                                            }
                                        >
                                            {String.fromCharCode(65 + i)}.{' '}
                                            {o.content}
                                        </li>
                                    ))}
                                </ul>
                            </div>
                            <div className="flex gap-2">
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() =>
                                        setEditing(
                                            editing === q.id ? null : q.id,
                                        )
                                    }
                                >
                                    {editing === q.id ? 'Đóng' : 'Sửa'}
                                </Button>
                                {q.status === 'NHAP' ? (
                                    <>
                                        <ConfirmAction
                                            size="sm"
                                            form={teacher.questions.ai.approve.form(
                                                q.id,
                                            )}
                                            label="Duyệt"
                                            title="Duyệt câu AI này?"
                                            description="Bạn đã đọc kỹ nội dung và đáp án. Câu chuyển sang Đang dùng."
                                            confirmLabel="Duyệt"
                                        />
                                        <ConfirmAction
                                            size="sm"
                                            variant="outline"
                                            form={teacher.questions.ai.discard.form(
                                                q.id,
                                            )}
                                            label="Bỏ nháp"
                                            title="Bỏ câu nháp này?"
                                            description="Câu được giữ lại ở Ngừng dùng, có thể khôi phục về nháp."
                                            confirmLabel="Bỏ nháp"
                                        />
                                    </>
                                ) : q.status === 'NGUNG_DUNG' &&
                                  q.source === 'AI' &&
                                  !q.ai_ever_approved ? (
                                    <ConfirmAction
                                        size="sm"
                                        variant="outline"
                                        form={teacher.questions.ai.restore.form(
                                            q.id,
                                        )}
                                        label="Khôi phục nháp"
                                        title="Khôi phục câu về nháp?"
                                        description="Câu cần được duyệt lại trước khi dùng."
                                        confirmLabel="Khôi phục"
                                    />
                                ) : q.status === 'DANG_DUNG' ? (
                                    <ConfirmAction
                                        size="sm"
                                        variant="outline"
                                        form={teacher.questions.deactivate.form(
                                            q.id,
                                        )}
                                        label="Ngừng dùng"
                                        title="Ngừng dùng câu này?"
                                        description="Câu không chọn được vào quiz mới; bản đã công bố giữ bản chụp."
                                        confirmLabel="Ngừng dùng"
                                    />
                                ) : (
                                    <ConfirmAction
                                        size="sm"
                                        variant="outline"
                                        form={teacher.questions.reactivate.form(
                                            q.id,
                                        )}
                                        label="Dùng lại"
                                        title="Dùng lại câu này?"
                                        confirmLabel="Dùng lại"
                                    />
                                )}
                            </div>
                        </div>
                        {editing === q.id && (
                            <div className="mt-4 border-t pt-4">
                                <QuestionForm
                                    bankId={bank.id}
                                    options={options}
                                    question={q}
                                    onDone={() => setEditing(null)}
                                />
                            </div>
                        )}
                    </li>
                ))}
            </ol>
            {questions.links.length > 3 && (
                <nav className="flex flex-wrap gap-1" aria-label="Phân trang">
                    {questions.links.map((l, i) =>
                        l.url ? (
                            <Link
                                key={i}
                                href={l.url}
                                preserveState
                                className={`rounded border px-3 py-1 text-sm ${l.active ? 'bg-surface text-surface-ink' : ''}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ) : null,
                    )}
                </nav>
            )}
        </Page>
    );
}

function FilterSelect({
    label,
    value,
    items,
    onChange,
}: {
    label: string;
    value: string;
    items: Option[];
    onChange: (v: string) => void;
}) {
    return (
        <select
            aria-label={label}
            value={value}
            onChange={(e) => onChange(e.target.value)}
            className={selectClass}
        >
            <option value="">{label}: tất cả</option>
            {items.map((i) => (
                <option key={i.value} value={i.value}>
                    {i.label}
                </option>
            ))}
        </select>
    );
}

function QuestionForm({
    bankId,
    options,
    question,
    onDone,
}: {
    bankId: number;
    options: Props['options'];
    question?: QuestionRow;
    onDone: () => void;
}) {
    const form = useForm<{
        type: string;
        difficulty: string;
        stem: string;
        points: string;
        hint: string;
        explanation: string;
        tag_ids: number[];
        options: { content: string; is_correct: boolean }[];
        image: File | null;
    }>({
        type: question?.type ?? options.types[0]?.value ?? 'MOT_DAP_AN',
        difficulty:
            question?.difficulty ?? options.difficulties[0]?.value ?? '',
        stem: question?.stem ?? '',
        points: question?.points ?? '1',
        hint: question?.hint ?? '',
        explanation: question?.explanation ?? '',
        tag_ids: question?.tag_ids ?? [],
        options: question?.options ?? [
            { content: '', is_correct: true },
            { content: '', is_correct: false },
        ],
        image: null,
    });
    const single = form.data.type !== 'NHIEU_DAP_AN';
    // Loại N: điền từ (mọi phương án là đáp án chấp nhận), sắp xếp (thứ tự trên xuống là thứ tự đúng).
    const allCorrect = ['DIEN_TU', 'SAP_XEP'].includes(form.data.type);
    const setOption = (
        i: number,
        patch: Partial<{ content: string; is_correct: boolean }>,
    ) =>
        form.setData(
            'options',
            form.data.options.map((o, j) =>
                j === i
                    ? { ...o, ...patch }
                    : patch.is_correct && single
                      ? { ...o, is_correct: false }
                      : o,
            ),
        );
    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        const url = question
            ? teacher.questions.update.url(question.id)
            : teacher.questionBanks.questions.store.url(bankId);
        form.transform((d) => {
            const { type, ...rest } = allCorrect
                ? {
                      ...d,
                      options: d.options.map((o) => ({
                          ...o,
                          is_correct: true,
                      })),
                  }
                : d;

            return question ? rest : { type, ...rest };
        });
        form.post(url, {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: () => onDone(),
        });
    };
    const errors = form.errors as Record<string, string>;

    return (
        <form onSubmit={submit} className="grid gap-4">
            <div className="grid gap-4 md:grid-cols-3">
                <Field label="Loại câu" htmlFor="q-type" error={errors.type}>
                    <select
                        id="q-type"
                        className={selectClass}
                        value={form.data.type}
                        disabled={!!question}
                        onChange={(e) => form.setData('type', e.target.value)}
                    >
                        {options.types.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field
                    label="Độ khó"
                    htmlFor="q-diff"
                    error={errors.difficulty}
                >
                    <select
                        id="q-diff"
                        className={selectClass}
                        value={form.data.difficulty}
                        onChange={(e) =>
                            form.setData('difficulty', e.target.value)
                        }
                    >
                        {options.difficulties.map((t) => (
                            <option key={t.value} value={t.value}>
                                {t.label}
                            </option>
                        ))}
                    </select>
                </Field>
                <Field label="Điểm" htmlFor="q-points" error={errors.points}>
                    <Input
                        id="q-points"
                        type="number"
                        step="0.01"
                        value={form.data.points}
                        onChange={(e) => form.setData('points', e.target.value)}
                    />
                </Field>
            </div>
            <Field label="Nội dung câu" htmlFor="q-stem" error={errors.stem}>
                <textarea
                    id="q-stem"
                    className={textareaClass}
                    required
                    value={form.data.stem}
                    onChange={(e) => form.setData('stem', e.target.value)}
                />
            </Field>
            <fieldset className="grid gap-2">
                <legend className="mb-1 text-sm font-medium">
                    {form.data.type === 'DIEN_TU'
                        ? 'Đáp án chấp nhận (không phân biệt hoa/thường, giữ dấu)'
                        : form.data.type === 'SAP_XEP'
                          ? 'Các mục theo thứ tự đúng, từ trên xuống'
                          : 'Phương án (đánh dấu đáp án đúng)'}
                </legend>
                {form.data.options.map((o, i) => (
                    <div key={i} className="flex items-center gap-2">
                        <input
                            hidden={allCorrect}
                            type={single ? 'radio' : 'checkbox'}
                            name="correct"
                            aria-label={`Phương án ${String.fromCharCode(65 + i)} đúng`}
                            checked={o.is_correct}
                            onChange={(e) =>
                                setOption(i, { is_correct: e.target.checked })
                            }
                        />
                        <Input
                            aria-label={`Phương án ${String.fromCharCode(65 + i)}`}
                            value={o.content}
                            onChange={(e) =>
                                setOption(i, { content: e.target.value })
                            }
                        />
                        {form.data.options.length > 2 && (
                            <Button
                                type="button"
                                size="sm"
                                variant="ghost"
                                onClick={() =>
                                    form.setData(
                                        'options',
                                        form.data.options.filter(
                                            (_, j) => j !== i,
                                        ),
                                    )
                                }
                            >
                                Bỏ
                            </Button>
                        )}
                    </div>
                ))}
                {errors.options && (
                    <p className="text-sm text-destructive">{errors.options}</p>
                )}
                {form.data.type !== 'DUNG_SAI' && (
                    <Button
                        type="button"
                        size="sm"
                        variant="outline"
                        className="justify-self-start"
                        onClick={() =>
                            form.setData('options', [
                                ...form.data.options,
                                { content: '', is_correct: false },
                            ])
                        }
                    >
                        Thêm phương án
                    </Button>
                )}
            </fieldset>
            <div className="grid gap-4 md:grid-cols-2">
                <Field label="Gợi ý" htmlFor="q-hint" error={errors.hint}>
                    <textarea
                        id="q-hint"
                        className={textareaClass}
                        value={form.data.hint}
                        onChange={(e) => form.setData('hint', e.target.value)}
                    />
                </Field>
                <Field
                    label="Lời giải"
                    htmlFor="q-exp"
                    error={errors.explanation}
                >
                    <textarea
                        id="q-exp"
                        className={textareaClass}
                        value={form.data.explanation}
                        onChange={(e) =>
                            form.setData('explanation', e.target.value)
                        }
                    />
                </Field>
            </div>
            <div className="flex flex-wrap gap-3">
                {options.tags.map((t) => (
                    <label
                        key={t.id}
                        className="flex items-center gap-2 text-sm"
                    >
                        <input
                            type="checkbox"
                            checked={form.data.tag_ids.includes(t.id)}
                            onChange={(e) =>
                                form.setData(
                                    'tag_ids',
                                    e.target.checked
                                        ? [...form.data.tag_ids, t.id]
                                        : form.data.tag_ids.filter(
                                              (x) => x !== t.id,
                                          ),
                                )
                            }
                        />
                        {t.name}
                    </label>
                ))}
            </div>
            <Field
                label="Ảnh minh họa (tối đa 5 MB)"
                htmlFor="q-image"
                error={errors.image}
            >
                <Input
                    id="q-image"
                    type="file"
                    accept="image/*"
                    onChange={(e) =>
                        form.setData('image', e.target.files?.[0] ?? null)
                    }
                />
            </Field>
            {errors.general && (
                <p role="alert" className="text-sm text-destructive">
                    {errors.general}
                </p>
            )}
            <Button disabled={form.processing} className="justify-self-start">
                {question ? 'Lưu câu' : 'Thêm câu'}
            </Button>
        </form>
    );
}
