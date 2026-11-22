import { Form } from '@inertiajs/react';
import { Landmark } from 'lucide-react';
import { useState } from 'react';
import { vnd, vnTime } from '@/components/commerce';
import { Block } from '@/components/page';
import { Button } from '@/components/ui/button';
import student from '@/routes/student';

export type TransferInfo = {
    bank: string;
    account_number: string;
    account_name: string;
    amount: number;
    memo: string;
    expires_at: string;
    qr_svg: string;
    /** CR-261: QR chuẩn VietQR, quét được bằng app ngân hàng. */
    vietqr?: boolean;
};

/**
 * QT-30: chọn chuyển khoản (đồng ý điều khoản) rồi hiện QR của đơn. Nội dung chuyển khoản phải đúng mã đơn;
 * QR hết hạn cùng đơn. Môi trường demo có nút cho ngân hàng mô phỏng báo đã nhận tiền.
 */
export function BankTransfer({
    code,
    transfer,
    simulator,
}: {
    code: string;
    transfer: TransferInfo | null;
    simulator: boolean;
}) {
    const [terms, setTerms] = useState(false);

    if (transfer === null) {
        return (
            <Block className="grid gap-3 p-4 sm:p-6">
                <h2 className="flex items-center gap-2 font-semibold">
                    <Landmark aria-hidden className="size-4" />
                    Chuyển khoản qua QR
                </h2>
                <Form
                    {...student.orders.transfer.store.form(code)}
                    className="grid gap-3"
                >
                    {({ processing, errors }) => (
                        <>
                            <label className="flex items-start gap-2 text-sm">
                                <input
                                    type="checkbox"
                                    name="terms"
                                    value="1"
                                    required
                                    checked={terms}
                                    onChange={(e) => setTerms(e.target.checked)}
                                    className="mt-1 size-4 shrink-0 accent-primary"
                                />
                                Tôi đồng ý với điều khoản “Khóa học số, không
                                hoàn tiền sau khi kích hoạt”.
                            </label>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing || !terms}
                            >
                                Hiện mã QR chuyển khoản
                            </Button>
                            {(errors.terms || errors.general) && (
                                <p
                                    role="alert"
                                    className="text-sm text-destructive"
                                >
                                    {errors.terms ?? errors.general}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </Block>
        );
    }

    return (
        <Block className="grid gap-3 p-4 sm:p-6">
            <h2 className="flex items-center gap-2 font-semibold">
                <Landmark aria-hidden className="size-4" />
                Chuyển khoản qua QR
            </h2>
            <div
                role="img"
                aria-label={`Mã QR chuyển ${vnd(transfer.amount)} với nội dung ${transfer.memo}`}
                className="mx-auto w-48 rounded-lg bg-white p-2 [&_svg]:h-auto [&_svg]:w-full"
                dangerouslySetInnerHTML={{ __html: transfer.qr_svg }}
            />
            <dl className="grid grid-cols-[auto_1fr] gap-x-3 gap-y-1 text-sm">
                <dt className="text-muted-foreground">Ngân hàng</dt>
                <dd>{transfer.bank}</dd>
                <dt className="text-muted-foreground">Số tài khoản</dt>
                <dd className="font-mono">{transfer.account_number}</dd>
                <dt className="text-muted-foreground">Chủ tài khoản</dt>
                <dd>{transfer.account_name}</dd>
                <dt className="text-muted-foreground">Số tiền</dt>
                <dd className="font-semibold">{vnd(transfer.amount)}</dd>
                <dt className="text-muted-foreground">Nội dung</dt>
                <dd className="font-mono font-semibold wrap-anywhere">
                    {transfer.memo}
                </dd>
                <dt className="text-muted-foreground">Hạn</dt>
                <dd>{vnTime(transfer.expires_at)}</dd>
            </dl>
            <p className="text-center text-xs text-muted-foreground">
                {transfer.vietqr
                    ? 'Mở app ngân hàng, chọn Quét QR: số tài khoản, số tiền và nội dung được điền sẵn.'
                    : 'QR minh họa của môi trường demo, không quét bằng app ngân hàng.'}
            </p>
            <p className="text-xs text-muted-foreground">
                Chuyển đúng số tiền và nội dung. Chuyển sai số tiền hoặc chuyển
                lần thứ hai sẽ được giáo vụ hoàn lại, không cấp quyền học.
            </p>
            {simulator && (
                <Form
                    {...student.orders.transfer.simulate.form(code)}
                    className="border-t pt-3"
                >
                    {({ processing }) => (
                        <Button
                            type="submit"
                            size="sm"
                            variant="outline"
                            disabled={processing}
                        >
                            Mô phỏng: ngân hàng báo đã nhận tiền
                        </Button>
                    )}
                </Form>
            )}
        </Block>
    );
}
