import { Form, Head } from '@inertiajs/react';
import { BadgePercent, BookOpen, CalendarClock } from 'lucide-react';
import { useState } from 'react';
import { vnd } from '@/components/commerce';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import promotions from '@/routes/academic/promotions';

type Course = { id: number; title: string; price: number };
type Promotion = {
    id: number;
    course_id: number;
    course: string;
    list_price: number;
    price: number;
    starts_on: string;
    ends_on: string;
    state: string;
};

const dmy = (d: string) => d.split('-').reverse().join('/');

export default function Promotions({
    courses,
    promotions: list,
}: {
    courses: Course[];
    promotions: Promotion[];
}) {
    const [edit, setEdit] = useState<Promotion | null>(null);
    const form = edit
        ? promotions.update.form(edit.id)
        : promotions.store.form();

    return (
        <Page
            title="Khuyến mãi khóa"
            description="Giá khuyến mãi có hiệu lực từ 00:00 ngày bắt đầu đến hết ngày kết thúc (giờ Việt Nam). Các đợt của một khóa không được trùng ngày."
            stats={[
                {
                    label: 'Đợt khuyến mãi',
                    value: list.length,
                    icon: BadgePercent,
                },
                {
                    label: 'Chưa kết thúc',
                    value: list.filter((p) => p.state !== 'Đã kết thúc').length,
                    icon: CalendarClock,
                },
                {
                    label: 'Khóa được giảm',
                    value: new Set(list.map((p) => p.course_id)).size,
                    icon: BookOpen,
                },
            ]}
        >
            <Head title="Khuyến mãi khóa" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">
                    {edit
                        ? `Sửa đợt của "${edit.course}"`
                        : 'Tạo đợt khuyến mãi'}
                </h2>
                <Form
                    key={edit?.id ?? 'new'}
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEdit(null)}
                    resetOnSuccess
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
                                    defaultValue={edit?.course_id ?? ''}
                                    className={selectClass}
                                >
                                    <option value="" disabled>
                                        Chọn khóa
                                    </option>
                                    {courses.map((c) => (
                                        <option key={c.id} value={c.id}>
                                            {c.title} ({vnd(c.price)})
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Field
                                label="Giá khuyến mãi (đ)"
                                htmlFor="price"
                                error={errors.price}
                            >
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min={0}
                                    required
                                    defaultValue={edit?.price}
                                />
                            </Field>
                            <Field
                                label="Ngày bắt đầu"
                                htmlFor="starts_on"
                                error={errors.starts_on}
                            >
                                <Input
                                    id="starts_on"
                                    name="starts_on"
                                    type="date"
                                    required
                                    defaultValue={edit?.starts_on}
                                />
                            </Field>
                            <Field
                                label="Ngày kết thúc"
                                htmlFor="ends_on"
                                error={errors.ends_on}
                            >
                                <Input
                                    id="ends_on"
                                    name="ends_on"
                                    type="date"
                                    required
                                    defaultValue={edit?.ends_on}
                                />
                            </Field>
                            <div className="flex gap-2 md:col-span-4">
                                <Button disabled={processing}>
                                    {edit ? 'Lưu thay đổi' : 'Tạo đợt'}
                                </Button>
                                {edit && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => setEdit(null)}
                                    >
                                        Hủy sửa
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </Form>
            </Block>
            <Block className="overflow-x-auto p-0 md:p-0">
                <table className="w-full text-sm">
                    <caption className="sr-only">Các đợt khuyến mãi</caption>
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="p-3 font-medium">Khóa</th>
                            <th className="p-3 font-medium">Giá</th>
                            <th className="p-3 font-medium">Thời gian</th>
                            <th className="p-3 font-medium">Trạng thái</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((p) => (
                            <tr
                                key={p.id}
                                className="border-b border-border last:border-0"
                            >
                                <td className="p-3">{p.course}</td>
                                <td className="p-3 tabular-nums">
                                    {vnd(p.price)}{' '}
                                    <s className="text-muted-foreground">
                                        {vnd(p.list_price)}
                                    </s>
                                </td>
                                <td className="p-3">
                                    {dmy(p.starts_on)} – {dmy(p.ends_on)}
                                </td>
                                <td className="p-3">{p.state}</td>
                                <td className="p-3 text-right">
                                    {p.state !== 'Đã kết thúc' && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setEdit(p)}
                                        >
                                            Sửa
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td
                                    colSpan={5}
                                    className="p-3 text-muted-foreground"
                                >
                                    Chưa có đợt khuyến mãi.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </Block>
        </Page>
    );
}
