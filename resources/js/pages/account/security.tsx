import { Form, Head, usePage } from '@inertiajs/react';
import ManageTwoFactor from '@/components/manage-two-factor';
import { Block, Field, Page } from '@/components/page';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { update } from '@/routes/account/security/password';

type Props = {
    canSetupTwoFactor: boolean;
    twoFactorEnabled: boolean;
    twoFactorPending: boolean;
    mustSetupTwoFactor: boolean;
    status?: string | null;
};

export default function Security({
    canSetupTwoFactor,
    twoFactorEnabled,
    mustSetupTwoFactor,
}: Props) {
    const { flash } = usePage().props;

    return (
        <Page
            title="Mật khẩu và bảo mật"
            description="Đổi mật khẩu, bật xác thực hai lớp và quản lý mã khôi phục cho tài khoản của bạn."
        >
            <Head title="Mật khẩu và bảo mật" />

            {mustSetupTwoFactor && !flash?.status && (
                <Block tone="accent">
                    <p className="font-semibold">
                        Vai trò của bạn cần xác thực hai lớp. Hãy thiết lập 2FA
                        để dùng các chức năng của vai này.
                    </p>
                </Block>
            )}

            <div className="grid gap-8 lg:grid-cols-2">
                <Block>
                    <h2 className="mb-4 text-lg font-bold">Đổi mật khẩu</h2>
                    <Form
                        {...update.form()}
                        resetOnSuccess
                        options={{ preserveScroll: true }}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label="Mật khẩu hiện tại"
                                    htmlFor="current_password"
                                    error={errors.current_password}
                                >
                                    <PasswordInput
                                        id="current_password"
                                        name="current_password"
                                        autoComplete="current-password"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Mật khẩu mới"
                                    htmlFor="password"
                                    hint="Tối thiểu 8 ký tự."
                                    error={errors.password}
                                >
                                    <PasswordInput
                                        id="password"
                                        name="password"
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                                <Field
                                    label="Nhập lại mật khẩu mới"
                                    htmlFor="password_confirmation"
                                >
                                    <PasswordInput
                                        id="password_confirmation"
                                        name="password_confirmation"
                                        autoComplete="new-password"
                                        required
                                    />
                                </Field>
                                <div>
                                    <Button type="submit" disabled={processing}>
                                        Đổi mật khẩu
                                    </Button>
                                </div>
                            </>
                        )}
                    </Form>
                </Block>

                {canSetupTwoFactor && (
                    <Block>
                        <ManageTwoFactor
                            canManageTwoFactor
                            requiresConfirmation
                            twoFactorEnabled={twoFactorEnabled}
                        />
                    </Block>
                )}
            </div>
        </Page>
    );
}
