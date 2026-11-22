import { Form, Head } from '@inertiajs/react';
import { Award } from 'lucide-react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';

type Badge = {
    id: number;
    code: string;
    name: string;
    description: string | null;
    rule: string;
    threshold: number;
    is_active: boolean;
    awarded: number;
    update_url: string;
};

type Props = {
    badges: Badge[];
    rules: { value: string; label: string }[];
    store_url: string;
};

function BadgeForm({
    badge,
    rules,
    action,
}: {
    badge?: Badge;
    rules: Props['rules'];
    action: string;
}) {
    const p = badge ? `b${badge.id}-` : 'new-';

    return (
        <Form
            action={action}
            method={badge ? 'put' : 'post'}
            className="grid gap-3"
            options={{ preserveScroll: true }}
            resetOnSuccess={!badge}
        >
            {({ processing, errors }) => (
                <>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <Field
                            label="Tên"
                            htmlFor={`${p}name`}
                            error={errors.name}
                        >
                            <Input
                                id={`${p}name`}
                                name="name"
                                required
                                maxLength={120}
                                defaultValue={badge?.name}
                            />
                        </Field>
                        <Field
                            label="Mô tả"
                            htmlFor={`${p}description`}
                            error={errors.description}
                        >
                            <Input
                                id={`${p}description`}
                                name="description"
                                maxLength={500}
                                defaultValue={badge?.description ?? ''}
                            />
                        </Field>
                        <Field
                            label="Điều kiện"
                            htmlFor={`${p}rule`}
                            error={errors.rule}
                        >
                            <select
                                id={`${p}rule`}
                                name="rule"
                                className={selectClass}
                                defaultValue={badge?.rule ?? rules[0]?.value}
                            >
                                {rules.map((r) => (
                                    <option key={r.value} value={r.value}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                        </Field>
                        <Field
                            label="Ngưỡng"
                            htmlFor={`${p}threshold`}
                            error={errors.threshold}
                        >
                            <Input
                                id={`${p}threshold`}
                                name="threshold"
                                type="number"
                                min={1}
                                required
                                defaultValue={badge?.threshold ?? 1}
                            />
                        </Field>
                    </div>
                    <div className="flex flex-wrap items-center gap-4">
                        <label className="flex items-center gap-2 text-sm">
                            <Checkbox
                                name="is_active"
                                value="1"
                                defaultChecked={badge?.is_active ?? true}
                            />{' '}
                            Đang dùng
                        </label>
                        <Button type="submit" size="sm" disabled={processing}>
                            {badge ? 'Lưu' : 'Tạo huy hiệu'}
                        </Button>
                    </div>
                </>
            )}
        </Form>
    );
}

/** QT-23 A1a: định nghĩa huy hiệu; sửa giữ mã và các lần đã trao, tắt thì ngừng trao mới. */
export default function BadgeIndex({ badges, rules, store_url }: Props) {
    return (
        <Page
            title="Huy hiệu"
            description="Huy hiệu trao một lần cho mỗi học viên. Đổi ngưỡng chỉ áp cho lần xét sau, không thu hồi huy hiệu đã trao."
        >
            <Head title="Huy hiệu" />
            {badges.map((b) => (
                <Block key={b.id} className="grid gap-3">
                    <div className="flex items-center gap-2">
                        <Award className="size-5 text-amber" />
                        <span className="font-semibold">{b.name}</span>
                        <code className="text-xs text-muted-foreground">
                            {b.code}
                        </code>
                        <span className="ml-auto text-sm text-muted-foreground">
                            Đã trao {b.awarded}
                        </span>
                    </div>
                    <BadgeForm badge={b} rules={rules} action={b.update_url} />
                </Block>
            ))}
            <Block className="grid gap-3">
                <h2 className="font-semibold">Tạo huy hiệu</h2>
                <BadgeForm rules={rules} action={store_url} />
            </Block>
        </Page>
    );
}
