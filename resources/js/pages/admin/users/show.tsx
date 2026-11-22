import { Form, Head, Link } from '@inertiajs/react';
import { ConfirmAction, MoreActions } from '@/components/confirm-action';
import { Block, Page, selectClass, StatusText } from '@/components/page';
import { Button } from '@/components/ui/button';
import users from '@/routes/admin/users';

type Props = {
    user: {
        id: number;
        name: string;
        email: string;
        status: string;
        status_label: string;
        locked_at: string | null;
        locked_reason: string | null;
        two_factor_enabled: boolean;
        must_change_password: boolean;
        roles: { code: string; label: string; pending_two_factor: boolean }[];
    };
    can: { lock: boolean; unlock: boolean; resetTwoFactor: boolean };
    roleOptions: { value: string; label: string }[];
};

const dateTime = new Intl.DateTimeFormat('vi-VN', {
    dateStyle: 'short',
    timeStyle: 'short',
    timeZone: 'Asia/Ho_Chi_Minh',
});

export default function AdminUserShow({ user, can, roleOptions }: Props) {
    const owned = new Set(user.roles.map((r) => r.code));
    const assignable = roleOptions.filter((o) => !owned.has(o.value));

    return (
        <Page
            title={user.name}
            description={user.email}
            actions={
                <Link href={users.index()} className="text-sm underline">
                    Về danh sách
                </Link>
            }
        >
            <Head title={user.name} />

            <div className="grid gap-4 lg:grid-cols-2">
                <Block>
                    <h2 className="mb-3 text-lg font-bold">Trạng thái</h2>
                    <dl className="grid grid-cols-[auto_1fr] gap-x-6 gap-y-2 text-sm">
                        <dt className="text-muted-foreground">Tài khoản</dt>
                        <dd>
                            <StatusText
                                status={user.status}
                                label={user.status_label}
                            />
                        </dd>
                        {user.locked_at && (
                            <>
                                <dt className="text-muted-foreground">
                                    Khóa lúc
                                </dt>
                                <dd>
                                    {dateTime.format(new Date(user.locked_at))}
                                </dd>
                            </>
                        )}
                        {user.locked_reason && (
                            <>
                                <dt className="text-muted-foreground">Lý do</dt>
                                <dd>{user.locked_reason}</dd>
                            </>
                        )}
                        <dt className="text-muted-foreground">2FA</dt>
                        <dd>
                            {user.two_factor_enabled ? 'Đã bật' : 'Chưa bật'}
                        </dd>
                        {user.must_change_password && (
                            <>
                                <dt className="text-muted-foreground">
                                    Mật khẩu
                                </dt>
                                <dd>Cần đổi mật khẩu lần đầu</dd>
                            </>
                        )}
                    </dl>
                    <div className="mt-4">
                        <MoreActions
                            actions={[
                                ...(can.lock
                                    ? [
                                          {
                                              form: users.lock.form(user.id),
                                              label: 'Khóa',
                                              title: `Khóa tài khoản ${user.name}?`,
                                              description:
                                                  'Mọi phiên đăng nhập của tài khoản này bị thu hồi ngay.',
                                              confirmLabel: 'Khóa',
                                              variant: 'destructive' as const,
                                              reasonField: {
                                                  name: 'reason',
                                                  label: 'Lý do (không bắt buộc)',
                                              },
                                          },
                                      ]
                                    : []),
                                ...(can.unlock
                                    ? [
                                          {
                                              form: users.unlock.form(user.id),
                                              label: 'Mở khóa',
                                              title: `Mở khóa tài khoản ${user.name}?`,
                                              confirmLabel: 'Mở khóa',
                                          },
                                      ]
                                    : []),
                                ...(can.resetTwoFactor
                                    ? [
                                          {
                                              form: users.twoFactor.destroy.form(
                                                  user.id,
                                              ),
                                              label: 'Đặt lại 2FA',
                                              title: 'Đặt lại xác thực hai lớp?',
                                              description:
                                                  'Người dùng bị đăng xuất và phải thiết lập lại 2FA ở lần đăng nhập kế tiếp.',
                                              confirmLabel: 'Đặt lại',
                                          },
                                      ]
                                    : []),
                            ]}
                        />
                    </div>
                </Block>

                <Block>
                    <h2 className="mb-3 text-lg font-bold">Vai trò</h2>
                    {user.roles.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có vai.
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {user.roles.map((r) => (
                                <li
                                    key={r.code}
                                    className="flex items-center justify-between gap-3 py-2"
                                >
                                    <div>
                                        <span className="font-medium">
                                            {r.label}
                                        </span>
                                        {r.pending_two_factor && (
                                            <span className="ml-2 text-xs font-medium text-accent-foreground">
                                                (chờ thiết lập 2FA)
                                            </span>
                                        )}
                                    </div>
                                    {r.code !== 'HOC_VIEN' && (
                                        <ConfirmAction
                                            form={users.roles.destroy.form({
                                                user: user.id,
                                                role: r.code,
                                            })}
                                            label="Gỡ"
                                            size="sm"
                                            variant="outline"
                                            title={`Gỡ vai ${r.label}?`}
                                            confirmLabel="Gỡ vai"
                                        />
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                    {assignable.length > 0 && (
                        <Form
                            {...users.roles.store.form(user.id)}
                            options={{ preserveScroll: true }}
                            className="mt-4 flex flex-wrap items-start gap-2"
                        >
                            {({ processing, errors }) => (
                                <>
                                    <select
                                        name="role"
                                        aria-label="Vai cần gán"
                                        className={selectClass}
                                    >
                                        {assignable.map((o) => (
                                            <option
                                                key={o.value}
                                                value={o.value}
                                            >
                                                {o.label}
                                            </option>
                                        ))}
                                    </select>
                                    <Button
                                        type="submit"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Gán vai
                                    </Button>
                                    {errors.role && (
                                        <p
                                            role="alert"
                                            className="w-full text-sm text-destructive"
                                        >
                                            {errors.role}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                    )}
                </Block>
            </div>
        </Page>
    );
}
