import { Head, Link } from '@inertiajs/react';
import { EnrollmentRecords } from '@/components/learning/enrollments';
import { LearningBadge } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index } from '@/routes/academic/enrollments';
import type { EnrollmentRow } from '@/types/learning';

export default function Student({
    student,
    enrollments,
}: {
    student: { name: string; email: string; status: string };
    enrollments: EnrollmentRow[];
}) {
    return (
        <Page title="Hồ sơ học viên" className="min-w-0">
            <Head title={student.name} />
            <div className="grid min-w-0 items-start gap-4 xl:grid-cols-[minmax(12rem,16rem)_minmax(0,1fr)]">
                <Block className="grid gap-3 p-4 sm:p-6">
                    <h2 className="font-semibold wrap-anywhere">
                        {student.name}
                    </h2>
                    <dl className="grid gap-3">
                        <OrderFact label="Email">{student.email}</OrderFact>
                        <OrderFact label="Trạng thái tài khoản">
                            <LearningBadge
                                attention={student.status === 'Bị khóa'}
                            >
                                {student.status}
                            </LearningBadge>
                        </OrderFact>
                    </dl>
                </Block>
                <section className="grid min-w-0 gap-3">
                    <h2 className="font-semibold">Ghi danh và tiến độ</h2>
                    <EnrollmentRecords rows={enrollments} />
                </section>
            </div>
            <div className="flex flex-wrap items-start gap-3">
                <Button asChild variant="outline" size="sm">
                    <Link href={index()}>Học viên và ghi danh</Link>
                </Button>
                <OrderReload
                    only={['student', 'enrollments']}
                    label="Tải lại hồ sơ"
                    failure="Không tải được hồ sơ học viên."
                />
            </div>
        </Page>
    );
}
