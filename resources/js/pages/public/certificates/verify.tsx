import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { verify } from '@/routes/public/certificates';

export default function Verify() {
    const [failure, setFailure] = useState('');
    return (
        <section className="mx-auto grid w-full max-w-7xl items-center gap-10 px-4 py-12 md:px-8 lg:grid-cols-[1fr_1fr] lg:py-20">
            <Head title="Xác minh chứng nhận" />
            <div className="grid gap-6">
                <h1 className="display-tight text-[clamp(2.2rem,4.6vw,4rem)]">
                    Xác minh chứng nhận EduNest
                </h1>
                <p className="max-w-md text-lg text-muted-foreground">
                    Nhập số in trên chứng nhận, hoặc quét mã QR trên bản PDF, để
                    xem chứng nhận còn hiệu lực hay đã bị thu hồi.
                </p>
                <Block className="shadow-lift p-5 sm:p-6">
                    <Form
                        {...verify.form()}
                        onStart={() => setFailure('')}
                        onNetworkError={() => {
                            setFailure('Không xác minh được lúc này.');
                            return false;
                        }}
                        onHttpException={(response) => {
                            if (![404, 429].includes(response.status)) return;
                            setFailure(
                                response.status === 404
                                    ? 'Không tìm thấy chứng nhận.'
                                    : 'Bạn đã tra quá nhiều lần. Hãy thử lại sau.',
                            );
                            return false;
                        }}
                        className="grid justify-items-start gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="w-full">
                                    <Field
                                        label="Số chứng nhận"
                                        htmlFor="number"
                                        hint="Ví dụ 2026-ABCD2345"
                                    >
                                        <Input
                                            id="number"
                                            name="number"
                                            required
                                            autoCapitalize="characters"
                                            spellCheck={false}
                                            aria-invalid={Boolean(
                                                failure || errors.number,
                                            )}
                                            aria-describedby={
                                                failure || errors.number
                                                    ? 'number-error'
                                                    : undefined
                                            }
                                        />
                                    </Field>
                                    {(failure || errors.number) && (
                                        <p
                                            id="number-error"
                                            role="alert"
                                            className="mt-2 text-sm text-destructive"
                                        >
                                            {failure || errors.number}
                                        </p>
                                    )}
                                </div>
                                <Button type="submit" disabled={processing}>
                                    {processing ? 'Đang xác minh…' : 'Xác minh'}
                                </Button>
                            </>
                        )}
                    </Form>
                </Block>
            </div>
            <div
                aria-hidden
                className="hidden aspect-square overflow-hidden rounded-3xl bg-slate-200 lg:block"
            >
                <img
                    src="/images/stock/viet-sach.jpg"
                    alt=""
                    className="h-full w-full object-cover"
                />
            </div>
        </section>
    );
}
