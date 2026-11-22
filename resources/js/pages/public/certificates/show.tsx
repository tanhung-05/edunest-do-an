import { Head, Link } from '@inertiajs/react';
import { useEffect, useRef } from 'react';
import { LogoMark } from '@/components/app-logo';
import { LearningBadge } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { Button } from '@/components/ui/button';
import { verify } from '@/routes/public/certificates';
import { localDate } from '@/types/learning';

type Certificate = {
    holder_name: string;
    course_title: string;
    issued_on: string;
    number: string;
    status: string;
};

export default function VerifyResult({
    certificate,
    qr_svg,
}: {
    certificate: Certificate;
    qr_svg: string;
}) {
    const heading = useRef<HTMLHeadingElement>(null);
    useEffect(() => {
        heading.current?.focus();
    }, [certificate.number]);
    const revoked = certificate.status === 'Đã thu hồi';

    return (
        <section className="mx-auto grid w-full max-w-4xl gap-6 px-4 py-10 sm:py-14">
            <Head title="Kết quả xác minh" />
            <h1
                ref={heading}
                tabIndex={-1}
                className="font-display text-3xl font-bold tracking-tight outline-none"
            >
                Kết quả xác minh
            </h1>
            <article className="shadow-lift overflow-hidden rounded-3xl border border-border bg-card">
                <div aria-hidden className="h-2 bg-amber" />
                <div className="grid gap-8 p-6 sm:grid-cols-[minmax(0,1fr)_auto] sm:p-10">
                    <div className="grid min-w-0 content-start gap-5">
                        <div className="flex flex-wrap items-center gap-3">
                            <LogoMark className="size-8" />
                            <LearningBadge attention={revoked}>
                                {certificate.status}
                            </LearningBadge>
                        </div>
                        <div className="grid gap-1">
                            <p className="text-sm text-muted-foreground">
                                Chứng nhận hoàn thành khóa học cấp cho
                            </p>
                            <p className="font-display text-3xl font-bold tracking-tight wrap-anywhere sm:text-4xl">
                                {certificate.holder_name}
                            </p>
                        </div>
                        <dl className="grid min-w-0 gap-4 sm:grid-cols-2">
                            <OrderFact label="Khóa học">
                                {certificate.course_title}
                            </OrderFact>
                            <OrderFact label="Ngày cấp">
                                {localDate(certificate.issued_on)}
                            </OrderFact>
                            <OrderFact label="Số chứng nhận">
                                <span className="tracking-wide tabular-nums">
                                    {certificate.number}
                                </span>
                            </OrderFact>
                            <OrderFact label="Trạng thái">
                                {certificate.status}
                            </OrderFact>
                        </dl>
                    </div>
                    <img
                        width="144"
                        height="144"
                        className="self-center rounded-xl border border-border bg-white p-2"
                        alt="Mã QR xác minh chứng nhận"
                        src={`data:image/svg+xml,${encodeURIComponent(qr_svg)}`}
                    />
                </div>
            </article>
            <Button asChild variant="outline" className="justify-self-start">
                <Link href={verify()}>Tra cứu chứng nhận khác</Link>
            </Button>
        </section>
    );
}
