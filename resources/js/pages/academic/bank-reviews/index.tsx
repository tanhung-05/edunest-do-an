import { Head } from '@inertiajs/react';
import { Landmark, Wallet } from 'lucide-react';
import { vnd, vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';

type Review = {
    id: number;
    bank_code: string;
    received_at: string;
    amount: number | null;
    memo: string;
    reason: string;
};

/** QT-30 A4, CR-179: bản chuyển khoản X4 chờ tra soát — 5 trường, nội dung đã che dữ liệu cá nhân; chỉ đọc. */
export default function BankReviews({ reviews }: { reviews: Review[] }) {
    return (
        <Page
            title="Chuyển khoản chờ tra soát"
            description="Giao dịch đã liên kết với một khoản nhưng nội dung chuyển không khớp mã đơn nào. Trang chỉ đọc; mỗi lần xem được ghi nhật ký. Xử lý tiền ở mục Đơn cần xử lý."
            stats={[
                {
                    label: 'Đang chờ tra soát',
                    value: reviews.length,
                    icon: Landmark,
                    attention: reviews.length > 0,
                },
                {
                    label: 'Tổng tiền',
                    value: vnd(
                        reviews.reduce((n, r) => n + (r.amount ?? 0), 0),
                    ),
                    icon: Wallet,
                },
            ]}
        >
            <Head title="Chuyển khoản chờ tra soát" />
            <Block className="overflow-x-auto p-0">
                {reviews.length === 0 ? (
                    <p className="p-4 text-sm text-muted-foreground">
                        Không có bản nào đang chờ.
                    </p>
                ) : (
                    <table className="w-full min-w-[560px] text-sm">
                        <thead className="bg-muted/50 text-left">
                            <tr>
                                <th className="px-4 py-2 font-medium">
                                    Mã giao dịch
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Nhận lúc
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Số tiền
                                </th>
                                <th className="px-4 py-2 font-medium">
                                    Nội dung
                                </th>
                                <th className="px-4 py-2 font-medium">Lý do</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y">
                            {reviews.map((r) => (
                                <tr key={r.id}>
                                    <td className="px-4 py-2 font-mono">
                                        {r.bank_code}
                                    </td>
                                    <td className="px-4 py-2">
                                        {vnTime(r.received_at)}
                                    </td>
                                    <td className="px-4 py-2">
                                        {r.amount === null
                                            ? '—'
                                            : vnd(r.amount)}
                                    </td>
                                    <td className="px-4 py-2 wrap-anywhere">
                                        {r.memo}
                                    </td>
                                    <td className="px-4 py-2">{r.reason}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                )}
            </Block>
        </Page>
    );
}
