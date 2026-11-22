import { Form, Head, Link } from '@inertiajs/react';
import { ConfirmAction, MoreActions } from '@/components/confirm-action';
import { AuthorSelect } from '@/components/author-select';
import type { ConfirmSpec } from '@/components/confirm-action';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import academic from '@/routes/academic';
import courses from '@/routes/academic/courses';
import type { CourseDetail, CourseOptions } from '@/types/course';
import { dateVi, money } from '@/types/course';

type Version = {
    id: number;
    version_no: number;
    status: string;
    status_label: string;
    submitted_at: string | null;
    published_at: string | null;
};

/** MH-GVU-04: thông tin khóa, giá, thẻ, người soạn, bán (QT-03, QT-05). */
export default function AcademicCourseShow({
    course,
    versions,
    options,
    prerequisite,
}: {
    course: CourseDetail;
    versions: Version[];
    options: CourseOptions;
    prerequisite: {
        id: number | null;
        options: { id: number; title: string }[];
        url: string;
    };
}) {
    const more: ConfirmSpec[] = [];
    if (course.sale_status === 'DANG_BAN') {
        more.push({
            form: courses.stopSelling.form(course.id),
            label: 'Ngừng bán',
            title: 'Ngừng bán khóa này?',
            description:
                'Khóa rời trang công khai, không nhận đơn mới. Học viên đã ghi danh vẫn học bình thường.',
            confirmLabel: 'Ngừng bán',
            variant: 'destructive',
        });
    }

    return (
        <Page
            title={course.title}
            description={`${course.category} · ${course.level} · ${course.sale_status_label}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    {course.sale_status === 'CHUA_BAN' && (
                        <ConfirmAction
                            form={courses.sell.form(course.id)}
                            label="Bật bán"
                            title="Bật bán khóa này?"
                            description="Khóa hiện trên trang công khai và nhận đơn."
                            confirmLabel="Bật bán"
                        />
                    )}
                    {course.sale_status === 'NGUNG_BAN' && (
                        <ConfirmAction
                            form={courses.resumeSelling.form(course.id)}
                            label="Bán lại"
                            title="Bán lại khóa này?"
                            confirmLabel="Bán lại"
                        />
                    )}
                    <MoreActions actions={more} />
                </div>
            }
        >
            <Head title={course.title} />
            {course.overdue && (
                <Block tone="accent">
                    <p className="font-semibold">
                        Quá hạn soạn ({course.due_date}).
                    </p>
                </Block>
            )}

            <Block>
                <h2 className="mb-3 text-lg font-bold">Các bản</h2>
                <ul className="divide-y">
                    {versions.map((v) => (
                        <li
                            key={v.id}
                            className="flex flex-wrap items-center justify-between gap-2 py-2"
                        >
                            <span>
                                Bản {v.version_no} ·{' '}
                                <strong>{v.status_label}</strong>
                                {v.published_at &&
                                    ` · công bố ${dateVi(v.published_at)}`}
                            </span>
                            <Link
                                href={academic.courseVersions.show(v.id)}
                                className="text-sm font-medium underline"
                            >
                                Xem bản
                            </Link>
                        </li>
                    ))}
                </ul>
            </Block>

            <div className="grid gap-4 lg:grid-cols-2">
                <Block>
                    <h2 className="mb-4 text-lg font-bold">Thông tin chung</h2>
                    <Form
                        {...courses.update.form(course.id)}
                        options={{ preserveScroll: true }}
                        className="grid gap-4"
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
                                        defaultValue={course.title}
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Danh mục"
                                    htmlFor="category_id"
                                    error={errors.category_id}
                                >
                                    <select
                                        id="category_id"
                                        name="category_id"
                                        defaultValue={course.category_id}
                                        className={selectClass}
                                    >
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
                                        defaultValue={course.level_id}
                                        className={selectClass}
                                    >
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
                                        defaultValue={course.learning_mode}
                                        className={selectClass}
                                    >
                                        {options.learningModes.map((m) => (
                                            <option
                                                key={m.value}
                                                value={m.value}
                                            >
                                                {m.label}
                                            </option>
                                        ))}
                                    </select>
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
                                        defaultValue={
                                            course.access_months ?? ''
                                        }
                                    />
                                </Field>
                                {errors.general && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.general}
                                    </p>
                                )}
                                <Button disabled={processing}>
                                    Lưu thông tin
                                </Button>
                            </>
                        )}
                    </Form>
                </Block>

                <div className="grid gap-4">
                    <Block>
                        <h2 className="mb-4 text-lg font-bold">Giá</h2>
                        <Form
                            {...courses.pricing.form(course.id)}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label={`Giá bán (hiện ${money(course.price)})`}
                                        htmlFor="price"
                                        error={errors.price}
                                    >
                                        <Input
                                            id="price"
                                            name="price"
                                            type="number"
                                            defaultValue={course.price}
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label="Giá gốc"
                                        htmlFor="original_price"
                                        error={errors.original_price}
                                    >
                                        <Input
                                            id="original_price"
                                            name="original_price"
                                            type="number"
                                            defaultValue={
                                                course.original_price ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Giá gia hạn"
                                        htmlFor="extension_price"
                                        error={errors.extension_price}
                                        hint="Để trống: chưa bán gia hạn."
                                    >
                                        <Input
                                            id="extension_price"
                                            name="extension_price"
                                            type="number"
                                            defaultValue={
                                                course.extension_price ?? ''
                                            }
                                        />
                                    </Field>
                                    <Button disabled={processing}>
                                        Lưu giá
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>

                    <Block>
                        <h2 className="mb-4 text-lg font-bold">Người soạn</h2>
                        <Form
                            {...courses.author.form(course.id)}
                            options={{ preserveScroll: true }}
                            className="grid gap-4"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Field
                                        label="Giáo viên"
                                        htmlFor="author_id"
                                        error={errors.author_id}
                                    >
                                        <AuthorSelect
                                            teachers={options.teachers}
                                            categoryId={course.category_id}
                                            defaultValue={
                                                course.author_id ?? ''
                                            }
                                        />
                                    </Field>
                                    <Field
                                        label="Hạn soạn"
                                        htmlFor="due_date"
                                        error={errors.due_date}
                                    >
                                        <Input
                                            id="due_date"
                                            name="due_date"
                                            type="date"
                                            defaultValue={course.due_date ?? ''}
                                        />
                                    </Field>
                                    <Button
                                        disabled={processing}
                                        variant="outline"
                                    >
                                        Giao lại
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>

                    <Block>
                        <h2 className="mb-1 text-lg font-bold">
                            Khóa tiên quyết
                        </h2>
                        <p className="mb-4 text-sm text-muted-foreground">
                            Học viên chưa bắt đầu học phải hoàn thành khóa này
                            trước; người đã học dở không bị ảnh hưởng.
                        </p>
                        <Form
                            action={prerequisite.url}
                            method="put"
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <label
                                        htmlFor="prerequisite_course_id"
                                        className="sr-only"
                                    >
                                        Khóa tiên quyết
                                    </label>
                                    <select
                                        id="prerequisite_course_id"
                                        name="prerequisite_course_id"
                                        defaultValue={prerequisite.id ?? ''}
                                        className={selectClass}
                                    >
                                        <option value="">Không có</option>
                                        {prerequisite.options.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.title}
                                            </option>
                                        ))}
                                    </select>
                                    {errors.prerequisite_course_id && (
                                        <p className="text-sm text-destructive">
                                            {errors.prerequisite_course_id}
                                        </p>
                                    )}
                                    <Button
                                        disabled={processing}
                                        variant="outline"
                                    >
                                        Lưu khóa tiên quyết
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>

                    <Block>
                        <h2 className="mb-4 text-lg font-bold">Thẻ</h2>
                        <Form
                            {...courses.tags.form(course.id)}
                            options={{ preserveScroll: true }}
                            className="grid gap-3"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <input
                                        type="hidden"
                                        name="tag_ids"
                                        value=""
                                    />
                                    <div className="flex flex-wrap gap-3">
                                        {options.tags.map((t) => (
                                            <label
                                                key={t.id}
                                                className="flex items-center gap-2 text-sm"
                                            >
                                                <input
                                                    type="checkbox"
                                                    name="tag_ids[]"
                                                    value={t.id}
                                                    defaultChecked={course.tag_ids.includes(
                                                        t.id,
                                                    )}
                                                />
                                                {t.name}
                                            </label>
                                        ))}
                                        {options.tags.length === 0 && (
                                            <span className="text-sm text-muted-foreground">
                                                Chưa có thẻ.
                                            </span>
                                        )}
                                    </div>
                                    {errors.tag_ids && (
                                        <p className="text-sm text-destructive">
                                            {errors.tag_ids}
                                        </p>
                                    )}
                                    <Button
                                        disabled={processing}
                                        variant="outline"
                                    >
                                        Lưu thẻ
                                    </Button>
                                </>
                            )}
                        </Form>
                    </Block>
                </div>
            </div>
        </Page>
    );
}
