import { Head } from '@inertiajs/react';
import { vnd, vnTime } from '@/components/commerce';
import { Block, Page, Stat } from '@/components/page';

type Props = {
    affiliate: {
        code: string;
        rate_bp: number;
        is_active: boolean;
        link: string;
    };
    commissions: {
        id: number;
        order: string | null;
        base_amount: number;
        amount: number;
        status: string;
        status_label: string;
        recorded_at: string;
    }[];
};

/** QT-26 A8: cộng tác viên xem đường dẫn và hoa hồng của chính mình. */
export default function StudentAffiliate({ affiliate, commissions }: Props) {
    const paid = commissions
        .filter((c) => c.status === 'DA_CHI')
        .reduce((s, c) => s + c.amount, 0);
    const pending = commissions
        .filter((c) => c.status === 'CHO_DUYET')
        .reduce((s, c) => s + c.amount, 0);

    return (
        <Page
            title="Cộng tác viên"
            description="Thêm mã giới thiệu vào cuối đường dẫn trang khóa hoặc combo. Đơn đặt từ trang đó và hoàn tất sẽ ghi hoa hồng; tự mua không tính, đơn hoàn tiền bị hủy hoa hồng."
        >
            <Head title="Cộng tác viên" />
            <div className="grid gap-3 sm:grid-cols-3">
                <Block tone="accent" className="grid gap-1 p-4 md:p-5">
                    <div className="text-sm">Mã giới thiệu</div>
                    <div className="font-mono text-2xl font-bold">
                        {affiliate.link}
                    </div>
                    <div className="text-xs">
                        Tỷ lệ{' '}
                        {(affiliate.rate_bp / 100).toLocaleString('vi-VN')}%
                        {affiliate.is_active ? '' : ' · đang tạm ngừng'}
                    </div>
                </Block>
                <Stat label="Chờ duyệt" value={vnd(pending)} />
                <Stat label="Đã chi (mô phỏng)" value={vnd(paid)} />
            </div>
            <Block className="grid gap-3">
                <h2 className="text-lg font-semibold">Hoa hồng</h2>
                {commissions.length === 0 ? (
                    <p className="text-sm text-muted-foreground">
                        Chưa có hoa hồng nào.
                    </p>
                ) : (
                    <ul className="grid gap-2 text-sm">
                        {commissions.map((c) => (
                            <li
                                key={c.id}
                                className="flex flex-wrap justify-between gap-2 border-t border-border pt-2"
                            >
                                <span className="font-mono">{c.order}</span>
                                <span className="text-muted-foreground">
                                    {vnTime(c.recorded_at)}
                                </span>
                                <span>
                                    {vnd(c.amount)} / {vnd(c.base_amount)}
                                </span>
                                <span className="font-medium">
                                    {c.status_label}
                                </span>
                            </li>
                        ))}
                    </ul>
                )}
            </Block>
        </Page>
    );
}
