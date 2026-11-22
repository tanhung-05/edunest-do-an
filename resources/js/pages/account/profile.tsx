import { Form, Head, usePage } from '@inertiajs/react';
import { Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { update } from '@/routes/account/profile';

export default function Profile() {
    const { auth } = usePage().props;
    const user = auth.user;

    return (
        <Page title="Hồ sơ cá nhân" description="Tên hiển thị và ảnh đại diện.">
            <Head title="Hồ sơ cá nhân" />
            <Form
                {...update.form()}
                options={{ preserveScroll: true }}
                className="grid max-w-xl gap-6"
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
                                defaultValue={user.name}
                                required
                                maxLength={120}
                                autoComplete="name"
                            />
                        </Field>
                        <Field
                            label="Email đăng nhập"
                            htmlFor="email"
                            hint="Không đổi được email đăng nhập."
                            error={errors.email}
                        >
                            <Input
                                id="email"
                                value={user.email}
                                readOnly
                                disabled
                            />
                        </Field>
                        <Field
                            label="Ảnh đại diện"
                            htmlFor="avatar"
                            hint="Ảnh JPG/PNG, tối đa 2 MB."
                            error={errors.avatar}
                        >
                            <div className="flex items-center gap-4">
                                {user.avatar && (
                                    <img
                                        src={user.avatar}
                                        alt=""
                                        className="size-14 rounded-full object-cover"
                                    />
                                )}
                                <Input
                                    id="avatar"
                                    name="avatar"
                                    type="file"
                                    accept="image/*"
                                />
                            </div>
                        </Field>
                        <div>
                            <Button type="submit" disabled={processing}>
                                Lưu hồ sơ
                            </Button>
                        </div>
                    </>
                )}
            </Form>
        </Page>
    );
}
