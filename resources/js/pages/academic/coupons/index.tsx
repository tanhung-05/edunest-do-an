import { Form, Head } from '@inertiajs/react';
import { CircleCheck, Clock, TicketPercent, Users } from 'lucide-react';
import { useState } from 'react';
import { vnd } from '@/components/commerce';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import coupons from '@/routes/academic/coupons';

type Coupon = {
    id: number;
    code: string;
    discount_type: 'PHAN_TRAM' | 'SO_TIEN';
    value: number;
    max_discount: number | null;
    starts_on: string;
    ends_on: string;
    total_limit: number;
    per_user_limit: number;
    scope_type: 'TOAN_BO' | 'DANH_MUC' | 'KHOA';
    scope_category_id: number | null;
    scope_course_id: number | null;
    min_order: number;
    note: string | null;
    used: number;
    held: number;
    active: boolean;
};

type Opt = { id: number; title?: string; name?: string };

const dmy = (d: string) => d.split('-').reverse().join('/');

export default function Coupons({
    coupons: list,
    categories,
    courses,
}: {
    coupons: Coupon[];
    categories: Opt[];
    courses: Opt[];
}) {
    const [edit, setEdit] = useState<Coupon | null>(null);
    const [type, setType] = useState<Coupon['discount_type']>('PHAN_TRAM');
    const [scope, setScope] = useState<Coupon['scope_type']>('TOAN_BO');

    const startEdit = (c: Coupon | null) => {
        setEdit(c);
        setType(c?.discount_type ?? 'PHAN_TRAM');
        setScope(c?.scope_type ?? 'TOAN_BO');
    };

    return (
        <Page
            title="Mã giảm giá"
            description="Mã áp dụng ở trang đơn hàng, một mã mỗi đơn. Lượt dùng được giữ chỗ khi áp và trả lại khi đơn hết hạn hoặc bị hủy."
            stats={[
                {
                    label: 'Mã giảm giá',
                    value: list.length,
                    icon: TicketPercent,
                },
                {
                    label: 'Đang bật',
                    value: list.filter((c) => c.active).length,
                    icon: CircleCheck,
                },
                {
                    label: 'Lượt đã dùng',
                    value: list.reduce((n, c) => n + c.used, 0),
                    icon: Users,
                },
                {
                    label: 'Đang giữ chỗ',
                    value: list.reduce((n, c) => n + c.held, 0),
                    icon: Clock,
                },
            ]}
        >
            <Head title="Mã giảm giá" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">
                    {edit ? `Sửa mã ${edit.code}` : 'Tạo mã giảm giá'}
                </h2>
                <Form
                    key={edit?.id ?? 'new'}
                    {...(edit
                        ? coupons.update.form(edit.id)
                        : coupons.store.form())}
                    options={{ preserveScroll: true }}
                    onSuccess={() => startEdit(null)}
                    resetOnSuccess
                    className="grid gap-4 md:grid-cols-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Mã"
                                htmlFor="code"
                                error={errors.code}
                                hint="3–40 ký tự A–Z, 0–9, gạch ngang, gạch dưới."
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    required
                                    maxLength={40}
                                    defaultValue={edit?.code}
                                    className="font-mono uppercase"
                                />
                            </Field>
                            <Field
                                label="Kiểu giảm"
                                htmlFor="discount_type"
                                error={errors.discount_type}
                            >
                                <select
                                    id="discount_type"
                                    name="discount_type"
                                    value={type}
                                    onChange={(e) =>
                                        setType(
                                            e.target
                                                .value as Coupon['discount_type'],
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="PHAN_TRAM">Phần trăm</option>
                                    <option value="SO_TIEN">Số tiền</option>
                                </select>
                            </Field>
                            <Field
                                label={
                                    type === 'PHAN_TRAM'
                                        ? 'Phần trăm giảm (1–100)'
                                        : 'Số tiền giảm (đ)'
                                }
                                htmlFor="value"
                                error={errors.value}
                            >
                                <Input
                                    id="value"
                                    name="value"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={edit?.value}
                                />
                            </Field>
                            {type === 'PHAN_TRAM' && (
                                <Field
                                    label="Giảm tối đa (đ, để trống nếu không giới hạn)"
                                    htmlFor="max_discount"
                                    error={errors.max_discount}
                                >
                                    <Input
                                        id="max_discount"
                                        name="max_discount"
                                        type="number"
                                        min={0}
                                        defaultValue={
                                            edit?.max_discount ?? undefined
                                        }
                                    />
                                </Field>
                            )}
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
                            <Field
                                label="Tổng lượt dùng"
                                htmlFor="total_limit"
                                error={errors.total_limit}
                            >
                                <Input
                                    id="total_limit"
                                    name="total_limit"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={edit?.total_limit ?? 100}
                                />
                            </Field>
                            <Field
                                label="Lượt mỗi người"
                                htmlFor="per_user_limit"
                                error={errors.per_user_limit}
                            >
                                <Input
                                    id="per_user_limit"
                                    name="per_user_limit"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={edit?.per_user_limit ?? 1}
                                />
                            </Field>
                            <Field
                                label="Đơn tối thiểu (đ)"
                                htmlFor="min_order"
                                error={errors.min_order}
                            >
                                <Input
                                    id="min_order"
                                    name="min_order"
                                    type="number"
                                    min={0}
                                    defaultValue={edit?.min_order ?? 0}
                                />
                            </Field>
                            <Field
                                label="Phạm vi"
                                htmlFor="scope_type"
                                error={errors.scope_type}
                            >
                                <select
                                    id="scope_type"
                                    name="scope_type"
                                    value={scope}
                                    onChange={(e) =>
                                        setScope(
                                            e.target
                                                .value as Coupon['scope_type'],
                                        )
                                    }
                                    className={selectClass}
                                >
                                    <option value="TOAN_BO">
                                        Toàn bộ khóa
                                    </option>
                                    <option value="DANH_MUC">
                                        Một danh mục
                                    </option>
                                    <option value="KHOA">Một khóa</option>
                                </select>
                            </Field>
                            {scope === 'DANH_MUC' && (
                                <Field
                                    label="Danh mục"
                                    htmlFor="scope_category_id"
                                    error={errors.scope_category_id}
                                >
                                    <select
                                        id="scope_category_id"
                                        name="scope_category_id"
                                        required
                                        defaultValue={
                                            edit?.scope_category_id ?? ''
                                        }
                                        className={selectClass}
                                    >
                                        <option value="" disabled>
                                            Chọn danh mục
                                        </option>
                                        {categories.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.name}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}
                            {scope === 'KHOA' && (
                                <Field
                                    label="Khóa"
                                    htmlFor="scope_course_id"
                                    error={errors.scope_course_id}
                                >
                                    <select
                                        id="scope_course_id"
                                        name="scope_course_id"
                                        required
                                        defaultValue={
                                            edit?.scope_course_id ?? ''
                                        }
                                        className={selectClass}
                                    >
                                        <option value="" disabled>
                                            Chọn khóa
                                        </option>
                                        {courses.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.title}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                            )}
                            <Field
                                label="Ghi chú nội bộ"
                                htmlFor="note"
                                error={errors.note}
                            >
                                <Input
                                    id="note"
                                    name="note"
                                    maxLength={255}
                                    defaultValue={edit?.note ?? ''}
                                />
                            </Field>
                            <div className="flex gap-2 md:col-span-3">
                                <Button disabled={processing}>
                                    {edit ? 'Lưu thay đổi' : 'Tạo mã'}
                                </Button>
                                {edit && (
                                    <Button
                                        type="button"
                                        variant="ghost"
                                        onClick={() => startEdit(null)}
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
                    <caption className="sr-only">Danh sách mã giảm giá</caption>
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="p-3 font-medium">Mã</th>
                            <th className="p-3 font-medium">Giảm</th>
                            <th className="p-3 font-medium">Thời gian</th>
                            <th className="p-3 font-medium">
                                Đã dùng / giữ chỗ / tổng
                            </th>
                            <th className="p-3 font-medium">Trạng thái</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {list.map((c) => (
                            <tr
                                key={c.id}
                                className="border-b border-border last:border-0"
                            >
                                <td className="p-3 font-mono">{c.code}</td>
                                <td className="p-3 tabular-nums">
                                    {c.discount_type === 'PHAN_TRAM'
                                        ? `${c.value}%${c.max_discount ? `, tối đa ${vnd(c.max_discount)}` : ''}`
                                        : vnd(c.value)}
                                    {c.min_order > 0 && (
                                        <span className="block text-xs text-muted-foreground">
                                            Đơn từ {vnd(c.min_order)}
                                        </span>
                                    )}
                                </td>
                                <td className="p-3">
                                    {dmy(c.starts_on)} – {dmy(c.ends_on)}
                                </td>
                                <td className="p-3 tabular-nums">
                                    {c.used} / {c.held} / {c.total_limit}
                                </td>
                                <td className="p-3">
                                    {c.active
                                        ? 'Đang hiệu lực'
                                        : 'Ngoài thời gian'}
                                </td>
                                <td className="p-3 text-right">
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => startEdit(c)}
                                    >
                                        Sửa
                                    </Button>
                                </td>
                            </tr>
                        ))}
                        {list.length === 0 && (
                            <tr>
                                <td
                                    colSpan={6}
                                    className="p-3 text-muted-foreground"
                                >
                                    Chưa có mã giảm giá.
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </Block>
        </Page>
    );
}
