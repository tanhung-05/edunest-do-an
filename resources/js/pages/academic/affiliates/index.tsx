import { Form, Head } from '@inertiajs/react';
import { Handshake, Hourglass, UserCheck, Wallet } from 'lucide-react';
import { useState } from 'react';
import { vnd, vnTime } from '@/components/commerce';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import affiliatesRoute from '@/routes/academic/affiliates';
import commissionsRoute from '@/routes/academic/commissions';

type Affiliate = {
    id: number;
    name: string | null;
    email: string | null;
    code: string;
    rate_bp: number;
    is_active: boolean;
    link: string;
};
type Commission = {
    id: number;
    order: string | null;
    affiliate: string | null;
    base_amount: number;
    rate_bp: number;
    amount: number;
    status: string;
    status_label: string;
    recorded_at: string;
    cancel_reason: string | null;
};

/** QT-26 bước 11–12, 17–18: hồ sơ cộng tác viên và duyệt chi hoa hồng mô phỏng. */
export default function AcademicAffiliates({
    affiliates,
    commissions,
}: {
    affiliates: Affiliate[];
    commissions: Commission[];
}) {
    const [edit, setEdit] = useState<Affiliate | null>(null);
    const form = edit
        ? affiliatesRoute.update.form(edit.id)
        : affiliatesRoute.store.form();

    return (
        <Page
            title="Cộng tác viên"
            description="Cộng tác viên là Học viên có hồ sơ riêng. Hoa hồng = tổng đơn sau giảm × tỷ lệ, làm tròn xuống, ghi khi đơn hoàn tất lần đầu; đơn có khoản hoàn bị hủy hoa hồng."
            stats={[
                { label: 'Đối tác', value: affiliates.length, icon: Handshake },
                {
                    label: 'Đang hoạt động',
                    value: affiliates.filter((a) => a.is_active).length,
                    icon: UserCheck,
                },
                {
                    label: 'Hoa hồng chờ duyệt',
                    value: commissions.filter((c) => c.status === 'CHO_DUYET')
                        .length,
                    icon: Hourglass,
                    attention: commissions.some(
                        (c) => c.status === 'CHO_DUYET',
                    ),
                },
                {
                    label: 'Tổng hoa hồng ghi nhận',
                    value: vnd(commissions.reduce((n, c) => n + c.amount, 0)),
                    icon: Wallet,
                },
            ]}
        >
            <Head title="Cộng tác viên" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">
                    {edit
                        ? `Sửa hồ sơ của ${edit.name}`
                        : 'Tạo hồ sơ cộng tác viên'}
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
                            {!edit && (
                                <Field
                                    label="Email học viên"
                                    htmlFor="email"
                                    error={errors.email}
                                >
                                    <Input
                                        id="email"
                                        name="email"
                                        type="email"
                                        required
                                    />
                                </Field>
                            )}
                            <Field
                                label="Mã giới thiệu"
                                htmlFor="code"
                                error={errors.code}
                            >
                                <Input
                                    id="code"
                                    name="code"
                                    required
                                    maxLength={32}
                                    defaultValue={edit?.code}
                                />
                            </Field>
                            <Field
                                label="Tỷ lệ (%)"
                                htmlFor="rate_percent"
                                error={errors.rate_percent ?? errors.rate_bp}
                            >
                                <Input
                                    id="rate_percent"
                                    name="rate_percent"
                                    type="number"
                                    min={0}
                                    max={50}
                                    step={0.01}
                                    required
                                    defaultValue={
                                        edit ? edit.rate_bp / 100 : 10
                                    }
                                />
                            </Field>
                            <div className="flex items-center gap-2 self-end pb-2">
                                <Checkbox
                                    id="is_active"
                                    name="is_active"
                                    value="1"
                                    defaultChecked={edit?.is_active ?? true}
                                />
                                <Label htmlFor="is_active">
                                    Đang hoạt động
                                </Label>
                            </div>
                            <div className="flex gap-2 md:col-span-4">
                                <Button type="submit" disabled={processing}>
                                    Lưu
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
            <div className="grid gap-5 lg:grid-cols-2">
                <Block className="grid content-start gap-3">
                    <h2 className="text-lg font-bold">Hồ sơ</h2>
                    <ul className="grid gap-2 text-sm">
                        {affiliates.map((a) => (
                            <li
                                key={a.id}
                                className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3"
                            >
                                <div className="min-w-0">
                                    <p className="font-medium wrap-anywhere">
                                        {a.name}
                                    </p>
                                    <p className="text-xs wrap-anywhere text-muted-foreground">
                                        {a.email} ·{' '}
                                        <span className="font-mono">
                                            {a.link}
                                        </span>{' '}
                                        ·{' '}
                                        {(a.rate_bp / 100).toLocaleString(
                                            'vi-VN',
                                        )}
                                        %{a.is_active ? '' : ' · tạm ngừng'}
                                    </p>
                                </div>
                                <Button
                                    size="sm"
                                    variant="outline"
                                    onClick={() => setEdit(a)}
                                >
                                    Sửa
                                </Button>
                            </li>
                        ))}
                    </ul>
                </Block>
                <Block className="grid content-start gap-3">
                    <h2 className="text-lg font-bold">Hoa hồng</h2>
                    {commissions.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có hoa hồng.
                        </p>
                    ) : (
                        <ul className="grid gap-2 text-sm">
                            {commissions.map((c) => (
                                <li
                                    key={c.id}
                                    className="grid gap-1 rounded-xl border border-border p-3"
                                >
                                    <div className="flex flex-wrap justify-between gap-2">
                                        <span className="font-mono">
                                            {c.order}
                                        </span>
                                        <span className="font-medium">
                                            {c.status_label}
                                        </span>
                                    </div>
                                    <div className="flex flex-wrap items-center justify-between gap-2 text-muted-foreground">
                                        <span>
                                            {c.affiliate} · {vnd(c.amount)} (
                                            {(c.rate_bp / 100).toLocaleString(
                                                'vi-VN',
                                            )}
                                            % của {vnd(c.base_amount)}) ·{' '}
                                            {vnTime(c.recorded_at)}
                                        </span>
                                        {c.status === 'CHO_DUYET' && (
                                            <Form
                                                {...commissionsRoute.pay.form(
                                                    c.id,
                                                )}
                                                options={{
                                                    preserveScroll: true,
                                                }}
                                                onBefore={() =>
                                                    window.confirm(
                                                        `Ghi chi trả mô phỏng ${vnd(c.amount)}?`,
                                                    )
                                                }
                                            >
                                                <Button size="sm" type="submit">
                                                    Duyệt chi
                                                </Button>
                                            </Form>
                                        )}
                                    </div>
                                    {c.cancel_reason && (
                                        <p className="text-xs text-destructive">
                                            {c.cancel_reason}
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </Block>
            </div>
        </Page>
    );
}
