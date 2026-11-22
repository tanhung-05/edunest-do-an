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
};

type Props = {
    teachers: Teacher[];
    filters: { q: string };
};

export default function AcademicTeachers({ teachers: list, filters }: Props) {
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
