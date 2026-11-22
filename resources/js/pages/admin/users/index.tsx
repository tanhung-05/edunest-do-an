import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field, Page, selectClass, StatusText } from '@/components/page';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import users from '@/routes/admin/users';

type Row = {
    id: number;
    name: string;
    email: string;
    status: string;
    status_label: string;
    roles: string[];
};

type Paginated<T> = {
    data: T[];
    links: { url: string | null; label: string; active: boolean }[];
    total: number;
};

type Option = { value: string; label: string };

type Props = {
    users: Paginated<Row>;
    filters: { q: string; role: string; status: string };
    roleOptions: Option[];
};

export default function AdminUsers({
    users: page,
    filters,
    roleOptions,
}: Props) {
    const [f, setF] = useState(filters);
    const [creating, setCreating] = useState(false);

    const apply = (next: typeof f) => {
        const query = Object.fromEntries(
            Object.entries(next).filter(([, v]) => v !== ''),
        );
        router.get(users.index.url({ query }), {}, { preserveState: true });
    };

    return (
        <Page
            title="Tài khoản và vai trò"
            actions={
                <Button onClick={() => setCreating((v) => !v)}>
                    {creating ? 'Đóng' : 'Cấp tài khoản'}
                </Button>
            }
        >
            <Head title="Tài khoản và vai trò" />

            {creating && (
                <Block>
                    <h2 className="mb-4 text-lg font-bold">Cấp tài khoản</h2>
                    <Form
                        {...users.store.form()}
                        className="grid gap-4 md:grid-cols-2"
                    >
                        {({ processing, errors }) => (
                            <>
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
                                    hint="Tối thiểu 8 ký tự. Người nhận phải đổi ở lần đăng nhập đầu."
                                    error={errors.password}
                                >
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        required
                                        autoComplete="new-password"
                                    />
                                </Field>
                                <Field
                                    label="Vai"
                                    htmlFor="role"
                                    error={errors.role}
                                >
                                    <select
                                        id="role"
                                        name="role"
                                        required
                                        className={selectClass}
                                    >
                                        {roleOptions.map((o) => (
                                            <option
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <div className="md:col-span-2">
                                    <Button type="submit" disabled={processing}>
                                        Cấp tài khoản
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </Block>
            )}

            <form
                className="flex flex-wrap items-end gap-2"
                onSubmit={(e) => {
                    e.preventDefault();
                    apply(f);
                }}
            >
                <Input
                    aria-label="Tìm theo tên hoặc email"
                    placeholder="Tìm theo tên hoặc email"
                    className="max-w-xs"
                    value={f.q}
                    onChange={(e) => setF({ ...f, q: e.target.value })}
                />
                <select
                    aria-label="Lọc theo vai"
                    className={selectClass}
                    value={f.role}
                    onChange={(e) => apply({ ...f, role: e.target.value })}
                >
                    <option value="">Mọi vai</option>
                    {roleOptions.map((o) => (
                        <option key={o.value} value={o.value}>
                            {o.label}
                        </option>
                    ))}
                </select>
                <select
                    aria-label="Lọc theo trạng thái"
                    className={selectClass}
                    value={f.status}
                    onChange={(e) => apply({ ...f, status: e.target.value })}
                >
                    <option value="">Mọi trạng thái</option>
                    <option value="HOAT_DONG">Hoạt động</option>
                    <option value="BI_KHOA">Bị khóa</option>
                </select>
                <Button type="submit" variant="secondary">
                    Tìm
                </Button>
            </form>

            {page.data.length === 0 ? (
                <p className="text-muted-foreground">
                    Không có tài khoản phù hợp.
                </p>
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
                                <th className="px-4 py-3 font-semibold">Vai</th>
                                <th className="px-4 py-3 font-semibold">
                                    Trạng thái
                                </th>
                            </tr>
                        </thead>
                        <tbody>
                            {page.data.map((u) => (
                                <tr
                                    key={u.id}
                                    className="border-t border-border"
                                >
                                    <td className="px-4 py-3">
                                        <Link
                                            href={users.show(u.id)}
                                            className="font-medium underline"
                                        >
                                            {u.name}
                                        </Link>
                                    </td>
                                    <td className="px-4 py-3">{u.email}</td>
                                    <td className="px-4 py-3">
                                        {u.roles.join(', ') || '—'}
                                    </td>
                                    <td className="px-4 py-3">
                                        <StatusText
                                            status={u.status}
                                            label={u.status_label}
                                        />
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {page.links.length > 3 && (
                <nav aria-label="Phân trang" className="flex flex-wrap gap-1">
                    {page.links.map((l, i) =>
                        l.url ? (
                            <Link
                                key={i}
                                href={l.url}
                                preserveState
                                className={`rounded-md px-3 py-1.5 text-sm ${l.active ? 'bg-surface text-surface-ink' : 'border border-border'}`}
                                dangerouslySetInnerHTML={{ __html: l.label }}
                            />
                        ) : null,
                    )}
                </nav>
            )}
        </Page>
    );
}
