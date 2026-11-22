import { Form, Head, Link, router } from '@inertiajs/react';
import { CircleAlert, Hourglass, Layers, Store } from 'lucide-react';
import { useState } from 'react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import courses from '@/routes/academic/courses';
import type { CourseOptions, CourseRow } from '@/types/course';
import { money } from '@/types/course';

/** MH-GVU-03: danh sách khóa, giao soạn khóa mới (PB-1). */
export default function AcademicCourses({
    courses: rows,
    filters,
    options,
}: {
    courses: CourseRow[];
    filters: { q: string };
    options: CourseOptions;
}) {
    const [creating, setCreating] = useState(false);
    const [q, setQ] = useState(filters.q);

    return (
        <Page
            title="Khóa học"
            actions={
                <Button onClick={() => setCreating((v) => !v)}>
                    {creating ? 'Đóng' : 'Giao soạn khóa mới'}
                </Button>
            }
            stats={[
                { label: 'Khóa học', value: rows.length, icon: Layers },
                {
                    label: 'Đang bán',
                    value: rows.filter((c) => c.sale_status === 'DANG_BAN')
                        .length,
                    icon: Store,
                },
                {
                    label: 'Phiên bản chờ duyệt',
                    value: rows.filter(
                        (c) => c.open_version?.status === 'CHO_DUYET',
                    ).length,
                    icon: Hourglass,
                    attention: rows.some(
                        (c) => c.open_version?.status === 'CHO_DUYET',
                    ),
                },
                {
                    label: 'Quá hạn soạn',
                    value: rows.filter((c) => c.overdue).length,
                    icon: CircleAlert,
                    attention: rows.some((c) => c.overdue),
                },
            ]}
        >
            <Head title="Khóa học" />
            {creating && (
                <Block>
                    <h2 className="mb-4 text-lg font-bold">
                        Giao soạn khóa mới
                    </h2>
                    <CourseForm options={options} />
                </Block>
            )}
            <form
                className="flex gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    router.get(
                        courses.index.url({ query: q ? { q } : {} }),
                        {},
                        { preserveState: true },
                    );
                }}
            >
                <Input
                    aria-label="Tìm theo tên khóa"
                    placeholder="Tìm theo tên khóa"
                    value={q}
                    onChange={(e) => setQ(e.target.value)}
                    className="max-w-sm"
                />
                <Button type="submit" variant="outline">
                    Tìm
                </Button>
            </form>
            <div className="overflow-x-auto rounded-lg border">
                <table className="w-full text-sm">
                    <thead className="bg-surface text-left text-surface-ink">
                        <tr>
                            <th className="p-3">Khóa</th>
                            <th className="p-3">Người soạn</th>
                            <th className="p-3">Bản đang mở</th>
                            <th className="p-3">Bán</th>
                            <th className="p-3">Giá</th>
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-3 text-muted-foreground"
                                >
                                    Chưa có khóa nào.
                                </td>
                            </tr>
                        )}
                        {rows.map((c) => (
                            <tr key={c.id} className="border-t">
                                <td className="p-3">
                                    <Link
                                        href={courses.show(c.id)}
                                        className="font-medium underline"
                                    >
                                        {c.title}
                                    </Link>
                                    {c.overdue && (
                                        <span className="ml-2 rounded bg-accent px-2 py-0.5 text-xs font-semibold text-accent-foreground">
                                            Quá hạn
                                        </span>
                                    )}
                                </td>
                                <td className="p-3">
                                    {c.author ?? '—'}
                                    {c.due_date && (
                                        <div className="text-xs text-muted-foreground">
                                            Hạn {c.due_date}
                                        </div>
                                    )}
                                </td>
                                <td className="p-3">
                                    {c.open_version
                                        ? `Bản ${c.open_version.version_no} · ${c.open_version.status_label}`
                                        : '—'}
                                </td>
                                <td className="p-3">{c.sale_status_label}</td>
                                <td className="p-3">{money(c.price)}</td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>
        </Page>
    );
}

function CourseForm({ options }: { options: CourseOptions }) {
    return (
        <Form
            {...courses.store.form()}
            className="grid gap-4 md:grid-cols-2"
            resetOnSuccess
        >
            {({ processing, errors }) => (
                <>
                    <Field
                        label="Tên khóa"
                        htmlFor="title"
                        error={errors.title}
                    >
                        <Input
                            id="title"
                            name="title"
                            required
                            maxLength={200}
                        />
                    </Field>
                    <Field
                        label="Người soạn (Giáo viên)"
                        htmlFor="author_id"
                        error={errors.author_id}
                    >
                        <select
                            id="author_id"
                            name="author_id"
                            required
                            className={selectClass}
                        >
                            <option value="">Chọn</option>
                            {options.teachers.map((t) => (
                                <option key={t.id} value={t.id}>
                                    {t.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Danh mục"
                        htmlFor="category_id"
                        error={errors.category_id}
                    >
                        <select
                            id="category_id"
                            name="category_id"
                            required
                            className={selectClass}
                        >
                            <option value="">Chọn</option>
                            {options.categories.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Cấp độ"
                        htmlFor="level_id"
                        error={errors.level_id}
                    >
                        <select
                            id="level_id"
                            name="level_id"
                            required
                            className={selectClass}
                        >
                            <option value="">Chọn</option>
                            {options.levels.map((c) => (
                                <option key={c.id} value={c.id}>
                                    {c.name}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Kiểu học"
                        htmlFor="learning_mode"
                        error={errors.learning_mode}
                    >
                        <select
                            id="learning_mode"
                            name="learning_mode"
                            className={selectClass}
                        >
                            {options.learningModes.map((m) => (
                                <option key={m.value} value={m.value}>
                                    {m.label}
                                </option>
                            ))}
                        </select>
                    </Field>
                    <Field
                        label="Hạn soạn"
                        htmlFor="due_date"
                        error={errors.due_date}
                    >
                        <Input id="due_date" name="due_date" type="date" />
                    </Field>
                    <Field
                        label="Giá bán (đ)"
                        htmlFor="price"
                        error={errors.price}
                    >
                        <Input
                            id="price"
                            name="price"
                            type="number"
                            min={1}
                            required
                        />
                    </Field>
                    <Field
                        label="Giá gốc (đ)"
                        htmlFor="original_price"
                        error={errors.original_price}
                        hint="Để trống nếu không giảm giá."
                    >
                        <Input
                            id="original_price"
                            name="original_price"
                            type="number"
                        />
                    </Field>
                    <Field
                        label="Thời hạn truy cập (tháng)"
                        htmlFor="access_months"
                        error={errors.access_months}
                        hint="Để trống: trọn đời."
                    >
                        <Input
                            id="access_months"
                            name="access_months"
                            type="number"
                            min={1}
                            max={120}
                        />
                    </Field>
                    <Field
                        label="Giá gia hạn (đ)"
                        htmlFor="extension_price"
                        error={errors.extension_price}
                        hint="Để trống: chưa bán gia hạn."
                    >
                        <Input
                            id="extension_price"
                            name="extension_price"
                            type="number"
                        />
                    </Field>
                    <div className="md:col-span-2">
                        {errors.general && (
                            <p
                                role="alert"
                                className="mb-2 text-sm text-destructive"
                            >
                                {errors.general}
                            </p>
                        )}
                        <Button disabled={processing}>Giao soạn</Button>
                    </div>
                </>
            )}
        </Form>
    );
}
