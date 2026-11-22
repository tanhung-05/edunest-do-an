export type OrderStatus =
    | 'CHO_THANH_TOAN'
    | 'DANG_CAP_QUYEN'
    | 'CAN_XU_LY'
    | 'HOAN_TAT'
    | 'DA_HUY'
    | 'HET_HAN';

export type ItemStatus = 'CHUA_CAP' | 'DA_CAP' | 'CAP_LOI';
export type TransactionStatus = 'KHOI_TAO' | 'THANH_CONG' | 'THAT_BAI';
export type ReconcileStatus = 'HOP_LE' | 'CAN_HOAN' | 'DA_HOAN_LAI';

export type OrderRow = {
    code: string;
    status: OrderStatus;
    status_label: string;
    total: number;
    created_at: string | null;
    expires_at: string;
    courses: string[];
    student: string | null;
};

export type OrderItem = {
    id: number;
    title: string;
    amount: number;
    access_months: number | null;
    status: ItemStatus;
    status_label: string;
    expiry_after: string | null;
    error: string | null;
    can_regrant: boolean;
    kind: 'MUA_KHOA' | 'GIA_HAN';
    kind_label: string;
    list_price: number;
    effective_price: number;
    discount: number;
};

export type PaymentRow = {
    id: number;
    code: string;
    requested_amount: number;
    received_amount: number | null;
    channel: string;
    txn_status: TransactionStatus;
    txn_status_label: string;
    reconcile_status: ReconcileStatus | null;
    reconcile_status_label: string | null;
    reason: string | null;
    refunded_at: string | null;
    can_accept: boolean;
    can_refund: boolean;
    order_code: string;
    order_status: string;
    simulated: boolean;
};

export type OrderDetail = OrderRow & {
    source: string;
    completed_at: string | null;
    can_pay: boolean;
    can_cancel: boolean;
    subtotal: number;
    discount_total: number;
    coupon: string | null;
    coupon_url: string | null;
    invoice: { number: string; status: string; url: string | null } | null;
    stop_refund: string | null;
    items: OrderItem[];
    payments: PaymentRow[];
};

export type OrderPage<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
};

export type OrderAudit = {
    id: number;
    action: string;
    actor_id: number | null;
    at: string;
    old: Record<string, unknown> | null;
    new: Record<string, unknown> | null;
    reason: string | null;
};

export type EnrollmentRow = {
    id: number;
    course_id: number;
    title: string;
    version: number;
    learning_status: 'Đang học' | 'Hoàn thành';
    validity: 'CON_HAN' | 'HET_HAN' | 'DA_THU_HOI';
    validity_label: string;
    expires_at: string | null;
};

export type CheckoutCourse = {
    slug: string;
    title: string;
    price: number;
    access_months: number | null;
};

export type SimulatedPayment = {
    payment: PaymentRow;
    selectedResult: string | null;
    canConfirm: boolean;
};

export const orderDate = (date: string | null) =>
    date
        ? new Date(date).toLocaleString('vi-VN', {
              timeZone: 'Asia/Ho_Chi_Minh',
          })
        : '—';

export const accessDuration = (months: number | null) =>
    months === null ? 'Vĩnh viễn' : `${months} tháng`;
