import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { vnTime } from '@/components/commerce';
import { ConfirmDialog } from '@/components/confirm-action';
import type { ConfirmSpec } from '@/components/confirm-action';
import { Block, Page, Stat } from '@/components/page';
import { Button } from '@/components/ui/button';
import batches from '@/routes/academic/activation-batches';
import type { BatchStats } from './index';

type Code = {
    id: number;
    code: string;
    status: string;
    voidable: boolean;
    redeemed_at: string | null;
    void_reason: string | null;
};

type Paged<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export default function ActivationBatch({
    batch,
    codes,
}: {
    batch: {
        id: number;
        course: string;
        quantity: number;
        note: string | null;
        access_months: number | null;
        redeem_until: string;
        stats: BatchStats;
    };
    codes: Paged<Code>;
}) {
    const [spec, setSpec] = useState<ConfirmSpec | null>(null);
    const voidSpec = (code: Code | null): ConfirmSpec => ({
        form: batches.void.form(batch.id),
        label: code ? 'Vô hiệu mã' : 'Vô hiệu cả lô',
        title: code
            ? `Vô hiệu mã ${code.code}?`
            : 'Vô hiệu mọi mã chưa dùng của lô?',
        description: 'Mã đã dùng giữ nguyên. Không hoàn tác được.',
        confirmLabel: 'Vô hiệu',
        variant: 'destructive',
        reasonField: { name: 'reason', label: 'Lý do', required: true },
        hidden: {
            confirmed: '1',
            ...(code ? { code_id: String(code.id) } : {}),
        },
    });

    return (
        <Page
            title={`Lô mã #${batch.id}`}
            eyebrow={<Link href={batches.index()}>Mã kích hoạt</Link>}
            description={`${batch.course} · ${batch.access_months ? `${batch.access_months} tháng truy cập` : 'vĩnh viễn'} · hạn đổi ${vnTime(batch.redeem_until)}${batch.note ? ` · ${batch.note}` : ''}`}
            actions={
                <>
                    <Button asChild variant="outline">
                        <a href={batches.export(batch.id).url}>Xuất CSV</a>
                    </Button>
                    {batch.stats.remaining > 0 && (
                        <Button
                            variant="destructive"
                            onClick={() => setSpec(voidSpec(null))}
                        >
                            Vô hiệu cả lô
                        </Button>
                    )}
                </>
            }
        >
            <Head title={`Lô mã #${batch.id}`} />
            <div className="grid grid-cols-2 gap-4 md:grid-cols-4">
                <Stat label="Đã dùng" value={batch.stats.used} />
                <Stat label="Còn dùng được" value={batch.stats.remaining} />
                <Stat label="Hết hạn" value={batch.stats.expired} />
                <Stat label="Vô hiệu" value={batch.stats.voided} />
            </div>
            <Block className="overflow-x-auto p-0 md:p-0">
                <table className="w-full text-sm">
                    <caption className="sr-only">Mã trong lô</caption>
                    <thead className="text-left text-muted-foreground">
                        <tr className="border-b border-border">
                            <th className="p-3 font-medium">Mã</th>
                            <th className="p-3 font-medium">Trạng thái</th>
                            <th className="p-3 font-medium">Ghi chú</th>
                            <th className="p-3" />
                        </tr>
                    </thead>
                    <tbody>
                        {codes.data.map((c) => (
                            <tr
                                key={c.id}
                                className="border-b border-border last:border-0"
                            >
                                <td className="p-3 font-mono tracking-wider">
                                    {c.code}
                                </td>
                                <td className="p-3">{c.status}</td>
                                <td className="p-3 text-muted-foreground">
                                    {c.redeemed_at
                                        ? `Đổi lúc ${vnTime(c.redeemed_at)}`
                                        : (c.void_reason ?? '')}
                                </td>
                                <td className="p-3 text-right">
                                    {c.voidable && (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            onClick={() => setSpec(voidSpec(c))}
                                        >
                                            Vô hiệu
                                        </Button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </Block>
            {codes.last_page > 1 && (
                <nav
                    aria-label="Phân trang mã"
                    className="flex justify-between text-sm"
                >
                    {codes.prev_page_url ? (
                        <Link href={codes.prev_page_url}>Trang trước</Link>
                    ) : (
                        <span />
                    )}
                    <span>
                        {codes.current_page}/{codes.last_page}
                    </span>
                    {codes.next_page_url ? (
                        <Link href={codes.next_page_url}>Trang sau</Link>
                    ) : (
                        <span />
                    )}
                </nav>
            )}
            <ConfirmDialog
                spec={spec}
                open={spec !== null}
                onOpenChange={(o) => !o && setSpec(null)}
            />
        </Page>
    );
}
