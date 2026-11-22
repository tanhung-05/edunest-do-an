import { Form, Head } from '@inertiajs/react';
import InputError from '@/components/input-error';
import PasswordInput from '@/components/password-input';
import TextLink from '@/components/text-link';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Spinner } from '@/components/ui/spinner';
import { login } from '@/routes';
import guardian from '@/routes/register/guardian';

/** QT-25 bước 3a: Khách đăng ký tài khoản Phụ huynh kèm mã mời của con (BR-MR-02). */
export default function RegisterGuardian({ code }: { code: string }) {
    return (
        <>
            <Head title="Đăng ký phụ huynh" />
            <Form
                {...guardian.store.form()}
                resetOnSuccess={['password', 'password_confirmation']}
                disableWhileProcessing
                className="flex flex-col gap-6"
            >
                {({ processing, errors }) => (
                    <div className="grid gap-6">
                        <div className="grid gap-2">
                            <Label htmlFor="code">Mã mời của con</Label>
                            <Input
                                id="code"
                                name="code"
                                required
                                autoFocus
                                maxLength={12}
                                defaultValue={code}
                                autoComplete="off"
                                className="font-mono uppercase"
                            />
                            <InputError message={errors.code} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="name">Họ tên</Label>
                            <Input
                                id="name"
                                name="name"
                                required
                                autoComplete="name"
                            />
                            <InputError message={errors.name} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="email">Email</Label>
                            <Input
                                id="email"
                                type="email"
                                name="email"
                                required
                                autoComplete="email"
                            />
                            <InputError message={errors.email} />
                        </div>
                        <div className="grid gap-2">
                            <Label htmlFor="password">Mật khẩu</Label>
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
                                Nhập lại mật khẩu
                            </Label>
                            <PasswordInput
                                id="password_confirmation"
                                name="password_confirmation"
                                required
                                autoComplete="new-password"
                            />
                        </div>
                        <Button type="submit" className="w-full">
                            {processing && <Spinner />}
                            Tạo tài khoản phụ huynh
                        </Button>
                        <div className="text-center text-sm text-muted-foreground">
                            Đã có tài khoản phụ huynh?{' '}
                            <TextLink href={login()}>Đăng nhập</TextLink> rồi
                            nhập mã ở trang "Con của tôi".
                        </div>
                    </div>
                )}
            </Form>
        </>
    );
}

RegisterGuardian.layout = {
    title: 'Tạo tài khoản phụ huynh',
    description: 'Dùng mã mời con tạo để theo dõi việc học của con',
};
