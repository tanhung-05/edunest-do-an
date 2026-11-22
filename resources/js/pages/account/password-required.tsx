import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import { Button } from '@/components/ui/button';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { logout } from '@/routes';
import required from '@/routes/account/password/required';
import { Link } from '@inertiajs/react';

export default function PasswordRequired() {
    return (
        <>
            <Head title="Đổi mật khẩu" />
            <Form
                {...required.update.form()}
                resetOnSuccess
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <>
                        <div className="grid gap-2">
                            <Label htmlFor="current_password">
                                Mật khẩu được cấp
                            </Label>
                            <PasswordInput
                                id="current_password"
                                name="current_password"
                                required
                                autoFocus
                                autoComplete="current-password"
                            />
                            <InputError message={errors.current_password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Mật khẩu mới</Label>
                            <PasswordInput
                                id="password"
                                name="password"
                                required
                                autoComplete="new-password"
                            />
                            <InputError message={errors.password} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password_confirmation">
                                Nhập lại mật khẩu mới
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                            <InputError
                                message={errors.password_confirmation}
                            />
                        </div>
                        <Button
                            type="submit"
                            className="w-full"
                            disabled={processing}
                        >
                            {processing && <Spinner />}
                            Đổi mật khẩu
                        </Button>
                        <Link
                            href={logout()}
                            as="button"
                            className="text-center text-sm text-muted-foreground underline"
                        >
                            Đăng xuất
                        </Link>
                    </>
                )}
            </Form>
        </>
    );
}

PasswordRequired.layout = {
    title: 'Đổi mật khẩu trước khi tiếp tục',
    description:
        'Tài khoản được cấp mật khẩu tạm. Hãy đặt mật khẩu của riêng bạn.',
};
