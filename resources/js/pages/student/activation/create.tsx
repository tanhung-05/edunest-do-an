import { Form, Head, Link } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import activation from '@/routes/student/activation';

type Redeemed = { course: string; url: string; expires_at: string | null };

export default function Activation({
    lockedUntil,
    redeemed,
}: {
    lockedUntil: string | null;
    redeemed: Redeemed | null;
}) {
    return (
        <Page
            title="Nhập mã kích hoạt"
            description="Mã 12 ký tự đi kèm sách hoặc chương trình đối tác. Không phân biệt chữ hoa, chữ thường."
        >
            <Head title="Nhập mã kích hoạt" />
            {redeemed && (
                <Block tone="accent" className="space-y-1">
                    <p className="font-semibold">
                        Đã kích hoạt "{redeemed.course}".
                    </p>
                    <p className="text-sm">
                        Hạn truy cập đến {vnTime(redeemed.expires_at)}.{' '}
                        <Link href={redeemed.url} className="underline">
                            Vào học
                        </Link>
                    </p>
                </Block>
            )}
            <Block className="max-w-xl">
                {lockedUntil ? (
                    <p role="alert" className="text-destructive">
                        Bạn đã nhập sai quá nhiều lần. Thử lại sau{' '}
                        {vnTime(lockedUntil)}.
                    </p>
                ) : (
                    <Form
                        {...activation.store.form()}
                        resetOnSuccess
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label="Mã kích hoạt"
                                    htmlFor="code"
                                    error={errors.code}
                                    hint="Sai 5 lần trong 15 phút sẽ bị tạm khóa 15 phút."
                                >
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        maxLength={64}
                                        autoComplete="off"
                                        className="font-mono tracking-widest uppercase"
                                    />
                                </Field>
                                <Button disabled={processing} className="w-fit">
                                    Kích hoạt
                                </Button>
                            </>
                        )}
                    </Form>
                )}
            </Block>
        </Page>
    );
}
