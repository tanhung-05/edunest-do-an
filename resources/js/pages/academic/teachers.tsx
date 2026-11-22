import { Form, Head, router } from '@inertiajs/react';
import { useState } from 'react';
import {
    Block,
    Field,
    Page,
    StatusText,
    textareaClass,
} from '@/components/page';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { show as publicTeacher } from '@/routes/public/teachers';
import teachers from '@/routes/academic/teachers';

type Teacher = {
    id: number;
    name: string;
    email: string;
    status: string;
    status_label: string;
    authoring_count: number;
    has_profile: boolean;
    subject_ids: number[];
};

type Subject = { id: number; name: string };

type Props = {
    teachers: Teacher[];
    filters: { q: string };
    subjects: Subject[];
};

export default function AcademicTeachers({
    teachers: list,
    filters,
    subjects,
}: Props) {
    const [q, setQ] = useState(filters.q);
    const [profileFor, setProfileFor] = useState<number | null>(null);

    return (
        <Page
            title="Tài khoản giáo viên"
            description="Cấp tài khoản Giáo viên kèm hồ sơ. Người nhận phải đổi mật khẩu ở lần đăng nhập đầu."
        >
            <Head title="Tài khoản giáo viên" />

            <Block>
                <h2 className="mb-4 text-lg font-bold">
                    Cấp tài khoản Giáo viên
                </h2>
                <Form
                    {...teachers.store.form()}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="grid gap-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <div className="grid gap-6 lg:grid-cols-2">
                                <div className="grid content-start gap-4">
                                    <Field
                                        label="Họ tên"
                                        htmlFor="name"
                                        error={errors.name}
                                    >
                                        <Input
                                            id="name"
                                            name="name"
                                            required
                                            maxLength={120}
                                        />
                                    </Field>
                                    <Field
                                        label="Email"
                                        htmlFor="email"
                                        error={errors.email}
                                    >
                                        <Input
                                            id="email"
                                            name="email"
                                            type="email"
                                            required
                                        />
                                    </Field>
                                    <Field
                                        label="Mật khẩu tạm"
                                        htmlFor="password"
                                        hint="Tối thiểu 8 ký tự. Gửi riêng cho giáo viên."
                                        error={errors.password}
                                    >
                                        <PasswordInput
                                            id="password"
                                            name="password"
                                            required
                                            autoComplete="new-password"
                                        />
                                    </Field>
                                </div>
                                <div className="grid content-start gap-4">
                                    <Field
                                        label="Ảnh"
                                        htmlFor="photo"
                                        error={errors.photo}
                                    >
                                        <Input
                                            id="photo"
                                            name="photo"
                                            type="file"
                                            accept="image/*"
                                        />
                                    </Field>
                                    <Field
                                        label="Giới thiệu"
                                        htmlFor="bio"
                                        error={errors.bio}
                                    >
                                        <textarea
                                            id="bio"
                                            name="bio"
                                            maxLength={5000}
                                            className={textareaClass}
                                        />
                                    </Field>
                                    <Field
                                        label="Thành tích"
                                        htmlFor="achievements"
                                        error={errors.achievements}
                                    >
                                        <textarea
                                            id="achievements"
                                            name="achievements"
                                            maxLength={5000}
                                            className={textareaClass}
                                        />
                                    </Field>
                                </div>
                            </div>
                            <div>
                                <Button type="submit" disabled={processing}>
                                    Cấp tài khoản Giáo viên
                                </Button>
                            </div>
                        </>
                    )}
                </Form>
            </Block>

            <section className="space-y-4">
                <form
                    className="flex max-w-md gap-2"
                    onSubmit={(e) => {
                        e.preventDefault();
                        router.get(
                            teachers.index.url({ query: { q } }),
                            {},
                            { preserveState: true },
                        );
                    }}
                >
                    <Input
                        aria-label="Tìm giáo viên"
                        placeholder="Tìm theo tên hoặc email"
                        value={q}
                        onChange={(e) => setQ(e.target.value)}
                    />
                    <Button type="submit" variant="secondary">
                        Tìm
                    </Button>
                </form>

                {list.length === 0 ? (
                    <p className="text-muted-foreground">Chưa có giáo viên.</p>
                ) : (
                    <div className="overflow-x-auto rounded-lg border border-border">
                        <table className="w-full text-sm">
                            <thead className="bg-surface text-left text-surface-ink">
                                <tr>
                                    <th className="px-4 py-3 font-semibold">
                                        Họ tên
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Email
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Trạng thái
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Chuyên môn
                                    </th>
                                    <th className="px-4 py-3 font-semibold">
                                        Hồ sơ
                                    </th>
                                </tr>
                            </thead>
                            <tbody>
                                {list.map((t) => (
                                    <tr
                                        key={t.id}
                                        className="border-t border-border align-top"
                                    >
                                        <td className="px-4 py-3 font-medium">
                                            {t.name}
                                        </td>
                                        <td className="px-4 py-3">{t.email}</td>
                                        <td className="px-4 py-3">
                                            <StatusText
                                                status={t.status}
                                                label={t.status_label}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            <SubjectCell
                                                teacher={t}
                                                subjects={subjects}
                                            />
                                        </td>
                                        <td className="px-4 py-3">
                                            {t.has_profile ? (
                                                <a
                                                    href={publicTeacher.url(
                                                        t.id,
                                                    )}
                                                    className="underline"
                                                    target="_blank"
                                                    rel="noreferrer"
                                                >
                                                    Xem trang
                                                </a>
                                            ) : profileFor === t.id ? (
                                                <Form
                                                    {...teachers.profile.store.form(
                                                        t.id,
                                                    )}
                                                    onSuccess={() =>
                                                        setProfileFor(null)
                                                    }
                                                    className="grid min-w-64 gap-2"
                                                >
                                                    {({
                                                        processing,
                                                        errors,
                                                    }) => (
                                                        <>
                                                            <textarea
                                                                name="bio"
                                                                aria-label="Giới thiệu"
                                                                placeholder="Giới thiệu"
                                                                className={
                                                                    textareaClass
                                                                }
                                                            />
                                                            <textarea
                                                                name="achievements"
                                                                aria-label="Thành tích"
                                                                placeholder="Thành tích"
                                                                className={
                                                                    textareaClass
                                                                }
                                                            />
                                                            {errors.bio && (
                                                                <p className="text-destructive">
                                                                    {errors.bio}
                                                                </p>
                                                            )}
                                                            <div className="flex gap-2">
                                                                <Button
                                                                    size="sm"
                                                                    type="submit"
                                                                    disabled={
                                                                        processing
                                                                    }
                                                                >
                                                                    Lập hồ sơ
                                                                </Button>
                                                                <Button
                                                                    size="sm"
                                                                    type="button"
                                                                    variant="ghost"
                                                                    onClick={() =>
                                                                        setProfileFor(
                                                                            null,
                                                                        )
                                                                    }
                                                                >
                                                                    Hủy
                                                                </Button>
                                                            </div>
                                                        </>
                                                    )}
                                                </Form>
                                            ) : (
                                                <Button
                                                    size="sm"
                                                    variant="secondary"
                                                    onClick={() =>
                                                        setProfileFor(t.id)
                                                    }
                                                >
                                                    Lập hồ sơ
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </section>
        </Page>
    );
}

/** CR-259 (UAT 22/11): Giáo vụ ghi chuyên môn để gợi ý người soạn đúng môn khi giao khóa. */
function SubjectCell({
    teacher,
    subjects,
}: {
    teacher: Teacher;
    subjects: Subject[];
}) {
    const [editing, setEditing] = useState(false);
    const [picked, setPicked] = useState<number[]>(teacher.subject_ids);
    const [saving, setSaving] = useState(false);
    const names = subjects
        .filter((s) => teacher.subject_ids.includes(s.id))
        .map((s) => s.name);

    if (!editing) {
        return (
            <div className="grid min-w-40 gap-1">
                <span
                    className={
                        names.length === 0 ? 'text-muted-foreground' : undefined
                    }
                >
                    {names.length === 0 ? 'Chưa ghi' : names.join(', ')}
                </span>
                <button
                    type="button"
                    className="justify-self-start text-sm underline"
                    onClick={() => {
                        setPicked(teacher.subject_ids);
                        setEditing(true);
                    }}
                    aria-label={`Sửa chuyên môn của ${teacher.name}`}
                >
                    Sửa
                </button>
            </div>
        );
    }

    const toggle = (id: number) =>
        setPicked((cur) =>
            cur.includes(id) ? cur.filter((x) => x !== id) : [...cur, id],
        );

    return (
        <fieldset className="grid min-w-56 gap-2">
            <legend className="sr-only">Chuyên môn của {teacher.name}</legend>
            <div className="grid max-h-48 gap-1 overflow-y-auto rounded-md border border-border p-2">
                {subjects.map((s) => (
                    <label key={s.id} className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            checked={picked.includes(s.id)}
                            onChange={() => toggle(s.id)}
                        />
                        {s.name}
                    </label>
                ))}
            </div>
            <div className="flex gap-2">
                <Button
                    size="sm"
                    type="button"
                    disabled={saving}
                    onClick={() =>
                        router.put(
                            teachers.subjects.update.url(teacher.id),
                            { subject_category_ids: picked },
                            {
                                preserveScroll: true,
                                onStart: () => setSaving(true),
                                onFinish: () => setSaving(false),
                                onSuccess: () => setEditing(false),
                            },
                        )
                    }
                >
                    Lưu
                </Button>
                <Button
                    size="sm"
                    type="button"
                    variant="ghost"
                    onClick={() => setEditing(false)}
                >
                    Hủy
                </Button>
            </div>
        </fieldset>
    );
}
