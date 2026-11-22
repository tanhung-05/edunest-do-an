import { Block } from '@/components/page';
import { money } from '@/types/course';
import { orderDate } from '@/types/order';
import type { OrderAudit } from '@/types/order';
import { itemLabels, orderLabels } from './status';

const actions: Record<string, string> = {
    ORDER_CANCEL: 'Hủy đơn',
    ORDER_ITEM_REGRANT: 'Cấp lại quyền học',
    ORDER_ITEM_GRANT_FAILED: 'Cấp quyền lỗi',
    PAYMENT_ACCEPT_LATE: 'Chấp nhận khoản thu muộn',
    PAYMENT_MARK_REFUNDED: 'Ghi nhận đã hoàn',
};

const statuses: Record<string, string> = {
    ...orderLabels,
    ...itemLabels,
    HOP_LE: 'Hợp lệ',
    CAN_HOAN: 'Cần hoàn',
    DA_HOAN_LAI: 'Đã hoàn lại',
};

const fields: Record<string, string> = {
    status: 'Trạng thái',
    order_status: 'Trạng thái đơn',
    reconcile_status: 'Đối soát',
    received_amount: 'Số tiền thực thu',
};

function auditValue(key: string, value: unknown) {
    if (key === 'received_amount' && typeof value === 'number') {
        return money(value);
    }
    return typeof value === 'string' ? (statuses[value] ?? value) : '—';
}

export function OrderTimeline({ audit }: { audit: OrderAudit[] }) {
    return (
        <Block className="grid gap-4 p-4 sm:p-6">
            <h2 className="text-lg font-semibold">Lịch sử thao tác</h2>
            {audit.length === 0 && (
                <p className="text-sm text-muted-foreground">
                    Chưa có thao tác.
                </p>
            )}
            <ol className="grid gap-5 border-l-2 border-accent pl-4">
                {audit.map((entry) => {
                    const grantError =
                        entry.action === 'ORDER_ITEM_REGRANT' ||
                        entry.action === 'ORDER_ITEM_GRANT_FAILED';
                    const keys = Object.keys(fields).filter(
                        (key) =>
                            entry.old?.[key] !== undefined ||
                            entry.new?.[key] !== undefined,
                    );
                    return (
                        <li
                            key={entry.id}
                            className="grid gap-1 text-sm wrap-anywhere"
                        >
                            <h3 className="font-semibold">
                                {actions[entry.action] ?? 'Cập nhật đơn hàng'}
                            </h3>
                            <p className="text-muted-foreground">
                                <time dateTime={entry.at}>
                                    {orderDate(entry.at)}
                                </time>{' '}
                                ·{' '}
                                {entry.actor_id === null
                                    ? 'Hệ thống'
                                    : `Mã người thực hiện ${entry.actor_id}`}
                            </p>
                            <dl className="grid gap-1">
                                {keys.map((key) => (
                                    <div
                                        key={key}
                                        className="flex flex-wrap gap-x-2"
                                    >
                                        <dt className="text-muted-foreground">
                                            {fields[key]}
                                        </dt>
                                        <dd>
                                            {auditValue(key, entry.old?.[key])}{' '}
                                            →{' '}
                                            {auditValue(key, entry.new?.[key])}
                                        </dd>
                                    </div>
                                ))}
                            </dl>
                            {entry.reason && !grantError && (
                                <p>Lý do: {entry.reason}</p>
                            )}
                        </li>
                    );
                })}
            </ol>
        </Block>
    );
}
