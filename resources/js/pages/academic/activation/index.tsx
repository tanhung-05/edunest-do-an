import { Form, Head, Link } from '@inertiajs/react';
import { CircleCheck, Hourglass, KeyRound, Ticket } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import batches from '@/routes/academic/activation-batches';

export type BatchStats = {
    used: number;
    remaining: number;
    expired: number;
    voided: number;
};

type Batch = {
    id: number;
    course: string;
    quantity: number;
    note: string | null;
    access_months: number | null;
    redeem_until: string;
    created_at: string | null;
    stats: BatchStats;
};

type Course = { id: number; title: string; access_months: number | null };

export default function ActivationBatches({
    batches: list,
    courses,
}: {
    batches: Batch[];
    courses: Course[];
}) {
    return (
        <Page
            title="Mã kích hoạt"
            description="Tạo lô mã 12 ký tự cho một khóa. Số tháng truy cập được chụp từ khóa lúc tạo lô."
            stats={[
                { label: 'Lô mã', value: list.length, icon: KeyRound },
                {
                    label: 'Tổng mã',
                    value: list.reduce((n, b) => n + b.quantity, 0),
                    icon: Ticket,
                },
                {
                    label: 'Đã dùng',
                    value: list.reduce((n, b) => n + b.stats.used, 0),
                    icon: CircleCheck,
                },
                {
                    label: 'Còn dùng được',
                    value: list.reduce((n, b) => n + b.stats.remaining, 0),
                    icon: Hourglass,
                },
            ]}
        >
            <Head title="Mã kích hoạt" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">Tạo lô mã</h2>
                <Form
                    {...batches.store.form()}
                    className="grid gap-4 md:grid-cols-4"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Khóa học"
                                htmlFor="course_id"
                                error={errors.course_id}
                            >
                                <select
                                    id="course_id"
                                    name="course_id"
                                    required
                                    defaultValue=""
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        Chọn khóa
                                    </option>
                                    {courses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.title} (
                                            {c.access_months
                                                ? `${c.access_months} tháng`
                                                : 'vĩnh viễn'}
                                            )
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Số lượng (1–1000)"
                                htmlFor="quantity"
                                error={errors.quantity}
                            >
                                <Input
                                    id="quantity"
                                    name="quantity"
                                    type="number"
                                    min={1}
                                    max={1000}
                                    required
                                    defaultValue={50}
                                />
                            </Field>
                            <Field
                                label="Hạn đổi mã"
                                htmlFor="redeem_until"
                                error={errors.redeem_until}
                            >
                                <Input
                                    id="redeem_until"
                                    name="redeem_until"
                                    type="datetime-local"
                                    required
                                />
                            </Field>
                            <Field
                                label="Ghi chú"
                                htmlFor="note"
                                error={errors.note}
                            >
                                <Input
                                    id="note"
                                    name="note"
                                    maxLength={255}
                                    placeholder="Ví dụ: kèm sách Hóa 12"
                                />
                            </Field>
                            <Button
                                disabled={processing}
                                className="w-fit md:col-span-4"
                            >
                                Tạo lô
                            </Button>
                        </>
                    )}
                </Form>
            </Block>
            <Block className="overflow-x-auto p-0 md:p-0">
                <table className="w-full text-sm">
                    <caption className="sr-only">Các lô mã kích hoạt</caption>
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="p-3 font-medium">Lô</th>
                            <th className="p-3 font-medium">Khóa</th>
                            <th className="p-3 font-medium">Hạn đổi</th>
                            <th className="p-3 font-medium">
                                Đã dùng / còn / hết hạn / vô hiệu
                            </th>
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((b) => (
                            <tr
                                key={b.id}
                                className="border-b border-border last:border-0"
                            >
                                <td className="p-3">
                                    <Link
                                        href={batches.show(b.id)}
                                        className="font-medium underline"
                                    >
                                        Lô #{b.id}
                                    </Link>
                                    {b.note && (
                                        <span className="block text-xs text-muted-foreground">
                                            {b.note}
                                        </span>
                                    )}
                                </td>
                                <td className="p-3">{b.course}</td>
                                <td className="p-3">
                                    {vnTime(b.redeem_until)}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {b.stats.used} / {b.stats.remaining} /{' '}
                                    {b.stats.expired} / {b.stats.voided}
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td
                                    colSpan={4}
                                    className="p-3 text-muted-foreground"
                                >
                                    Chưa có lô mã nào.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </Block>
        </Page>
    );
}
