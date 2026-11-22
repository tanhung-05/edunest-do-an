import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type {
    ItemStatus,
    OrderStatus,
    ReconcileStatus,
    TransactionStatus,
} from '@/types/order';

export const orderLabels: Record<OrderStatus, string> = {
    CHO_THANH_TOAN: 'Chờ thanh toán',
    DANG_CAP_QUYEN: 'Đang cấp quyền học',
    CAN_XU_LY: 'Cần xử lý',
    HOAN_TAT: 'Hoàn tất',
    DA_HUY: 'Đã hủy',
    HET_HAN: 'Hết hạn',
};

export const itemLabels: Record<ItemStatus, string> = {
    CHUA_CAP: 'Chưa cấp',
    DA_CAP: 'Đã cấp',
    CAP_LOI: 'Cấp quyền lỗi',
};

const warningStatuses = new Set([
    'CHO_THANH_TOAN',
    'CAN_XU_LY',
    'CAN_HOAN',
    'CAP_LOI',
    'THAT_BAI',
]);

const confirmedStatuses = new Set([
    'DANG_CAP_QUYEN',
    'HOAN_TAT',
    'DA_CAP',
    'THANH_CONG',
    'HOP_LE',
    'CON_HAN',
]);

type Status =
    | OrderStatus
    | ItemStatus
    | TransactionStatus
    | ReconcileStatus
    | 'CON_HAN'
    | 'DA_THU_HOI';

export function OrderBadge({
    status,
    label,
}: {
    status: Status | null;
    label: string | null;
}) {
    if (status === null) {
        return <span aria-label="Chưa đối soát">—</span>;
    }

    return (
        <Badge
            variant="secondary"
            className={cn(
                'max-w-full text-left whitespace-normal',
                warningStatuses.has(status) &&
                    'bg-accent text-accent-foreground',
                confirmedStatuses.has(status) && 'bg-surface text-surface-ink',
            )}
        >
            {label}
        </Badge>
    );
}
