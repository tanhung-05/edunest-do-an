import { Form, Head } from '@inertiajs/react';
import { BookOpen, Package, Store } from 'lucide-react';
import { useState } from 'react';
import { vnd } from '@/components/commerce';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import bundlesRoute from '@/routes/academic/bundles';

type Course = { id: number; title: string; price: number };
type Bundle = {
    id: number;
    title: string;
    slug: string;
    description: string | null;
    price: number;
    deduct_owned: boolean;
    accepts_orders: boolean;
    course_ids: number[];
    courses: string[];
};

/** QT-26 bước 1–2, A1: Giáo vụ tạo/sửa combo, giá chung, trừ khóa đã sở hữu, cờ nhận đơn mới. */
export default function AcademicBundles({
    bundles,
    courses,
}: {
    bundles: Bundle[];
    courses: Course[];
}) {
    const [edit, setEdit] = useState<Bundle | null>(null);
    const form = edit
        ? bundlesRoute.update.form(edit.id)
        : bundlesRoute.store.form();

    return (
        <Page
            title="Combo"
            description="Combo gồm ít nhất 2 khóa đã công bố. Giá combo không vượt tổng giá niêm yết. Đơn đã đặt giữ giá đã chụp khi sửa combo."
            stats={[
                { label: 'Combo', value: bundles.length, icon: Package },
                {
                    label: 'Đang nhận đơn',
                    value: bundles.filter((b) => b.accepts_orders).length,
                    icon: Store,
                },
                {
                    label: 'Khóa có thể ghép',
                    value: courses.length,
                    icon: BookOpen,
                },
            ]}
        >
            <Head title="Combo" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">
                    {edit ? `Sửa "${edit.title}"` : 'Tạo combo'}
                </h2>
                <Form
                    key={edit?.id ?? 'new'}
                    {...form}
                    options={{ preserveScroll: true }}
                    onSuccess={() => setEdit(null)}
                    resetOnSuccess
                    className="grid gap-4 md:grid-cols-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Tên combo"
                                htmlFor="title"
                                error={errors.title}
                            >
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    maxLength={200}
                                    defaultValue={edit?.title}
                                />
                            </Field>
                            <Field
                                label="Giá combo (đồng)"
                                htmlFor="price"
                                error={errors.price}
                            >
                                <Input
                                    id="price"
                                    name="price"
                                    type="number"
                                    min={1}
                                    required
                                    defaultValue={edit?.price}
                                />
                            </Field>
                            <Field
                                label="Mô tả"
                                htmlFor="description"
                                error={errors.description}
                            >
                                <Input
                                    id="description"
                                    name="description"
                                    maxLength={2000}
                                    defaultValue={edit?.description ?? ''}
                                />
                            </Field>
                            <div className="flex items-center gap-2 self-end pb-2">
                                <Checkbox
                                    id="deduct_owned"
                                    name="deduct_owned"
                                    value="1"
                                    defaultChecked={edit?.deduct_owned ?? true}
                                />
                                <Label htmlFor="deduct_owned">
                                    Trừ giá lẻ khóa học viên đã sở hữu
                                </Label>
                            </div>
                            <fieldset className="grid gap-2 md:col-span-2">
                                <legend className="mb-1 text-sm font-medium">
                                    Khóa trong combo
                                </legend>
                                <div className="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                                    {courses.map((c) => (
                                        <label
                                            key={c.id}
                                            className="flex items-center gap-2 rounded-lg border border-border p-2 text-sm"
                                        >
                                            <input
                                                type="checkbox"
                                                name="course_ids[]"
                                                value={c.id}
                                                defaultChecked={edit?.course_ids.includes(
                                                    c.id,
                                                )}
                                                className="size-4 accent-navy"
                                            />
                                            <span className="min-w-0 flex-1 wrap-anywhere">
                                                {c.title}
                                            </span>
                                            <span className="text-muted-foreground">
                                                {vnd(c.price)}
                                            </span>
                                        </label>
                                    ))}
                                </div>
                                {errors.course_ids && (
                                    <p className="text-sm text-destructive">
                                        {errors.course_ids}
                                    </p>
                                )}
                            </fieldset>
                            <div className="flex gap-2 md:col-span-2">
                                <Button type="submit" disabled={processing}>
                                    Lưu combo
                                </Button>
                                {edit && (
                                    <Button
                                        type="button"
                                        variant="outline"
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
            <Block className="grid gap-3">
                <h2 className="text-lg font-bold">Danh sách combo</h2>
                {bundles.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Chưa có combo.
                    </p>
                ) : (
                    <ul className="grid gap-3">
                        {bundles.map((b) => (
                            <li
                                key={b.id}
                                className="grid gap-2 rounded-xl border border-border p-4 md:grid-cols-[1fr_auto] md:items-center"
                            >
                                <div className="min-w-0">
                                    <p className="font-semibold wrap-anywhere">
                                        {b.title} · {vnd(b.price)}
                                    </p>
                                    <p className="text-sm wrap-anywhere text-muted-foreground">
                                        {b.courses.join(', ')}
                                    </p>
                                    <p className="text-xs text-muted-foreground">
                                        {b.deduct_owned
                                            ? 'Trừ khóa đã sở hữu'
                                            : 'Không trừ khóa đã sở hữu'}{' '}
                                        ·{' '}
                                        <span
                                            className={
                                                b.accepts_orders
                                                    ? 'text-success'
                                                    : 'text-destructive'
                                            }
                                        >
                                            {b.accepts_orders
                                                ? 'Đang nhận đơn'
                                                : 'Ngừng nhận đơn mới'}
                                        </span>
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        onClick={() => setEdit(b)}
                                    >
                                        Sửa
                                    </Button>
                                    <Form
                                        {...bundlesRoute.accepting.form(b.id)}
                                        options={{ preserveScroll: true }}
                                        onBefore={() =>
                                            window.confirm(
                                                b.accepts_orders
                                                    ? 'Ngừng nhận đơn mới cho combo này? Đơn đã đặt vẫn giữ giá và hạn.'
                                                    : 'Mở lại nhận đơn mới cho combo này?',
                                            )
                                        }
                                    >
                                        <input
                                            type="hidden"
                                            name="accepts_orders"
                                            value={b.accepts_orders ? '0' : '1'}
                                        />
                                        <input
                                            type="hidden"
                                            name="confirmed"
                                            value="1"
                                        />
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                        >
                                            {b.accepts_orders
                                                ? 'Ngừng nhận đơn'
                                                : 'Mở nhận đơn'}
                                        </Button>
                                    </Form>
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </Block>
        </Page>
    );
}
