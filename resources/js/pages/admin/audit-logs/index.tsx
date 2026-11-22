import { Form, Head } from '@inertiajs/react';
import { Pagination, Records } from '@/components/learning/records';
import { OrderFact } from '@/components/order/details';
import { OrderReload } from '@/components/order/refresh';
import { itemLabels, orderLabels } from '@/components/order/status';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { index } from '@/routes/admin/audit-logs';
import { money } from '@/types/course';
import type { Paginator } from '@/types/learning';
import { localTime } from '@/types/learning';

type Value = string | number | boolean | null | Value[];
type Values = Record<string, Value>;
type Row = {
    id: number;
    action: string;
    actor_type: string;
    actor_id: number | null;
    subject_type: string;
    subject_id: number;
    reason: string | null;
    created_at: string;
    old_values: Values | null;
    new_values: Values | null;
};
type Filters = {
    action?: string;
    actor_id?: number;
    subject_type?: string;
    subject_id?: number;
};

const actors: Record<string, string> = {
    TAI_KHOAN: 'Tài khoản',
    LENH_MAY_CHU: 'Lệnh quản trị trên máy chủ',
    HE_THONG: 'Hệ thống',
};
const subjects: Record<string, string> = {
    user: 'Tài khoản',
    course: 'Khóa học',
    course_version: 'Phiên bản nội dung',
    order: 'Đơn hàng',
    order_item: 'Dòng đơn',
    payment: 'Khoản thanh toán',
    certificate: 'Chứng nhận',
    quiz_attempt_grant: 'Lượt quiz bổ sung',
    setting: 'Thiết lập',
};
const actions: Record<string, string> = {
    COURSE_PRICE: 'Đổi giá khóa học',
    COURSE_UPDATE: 'Cập nhật khóa học',
    COURSE_REASSIGN: 'Đổi giáo viên phụ trách',
    COURSE_ASSIGN: 'Phân công giáo viên',
    COURSE_TAGS: 'Cập nhật thẻ khóa học',
    COURSE_SELL_START: 'Mở bán khóa học',
    COURSE_SELL_STOP: 'Ngừng bán khóa học',
    COURSE_SELL_RESUME: 'Mở bán lại khóa học',
    VERSION_SUBMIT: 'Gửi duyệt phiên bản',
    VERSION_WITHDRAW: 'Rút bản gửi duyệt',
    VERSION_REOPEN: 'Mở lại bản nháp',
    VERSION_RETURN: 'Trả lại phiên bản',
    VERSION_APPROVE: 'Duyệt phiên bản',
    VERSION_PUBLISH: 'Công bố phiên bản',
    VERSION_CREATE_DRAFT: 'Tạo bản nháp',
    ROLE_ASSIGN: 'Gán vai trò',
    ROLE_REMOVE: 'Gỡ vai trò',
    ORDER_ITEM_REGRANT: 'Cấp lại quyền học',
    ORDER_ITEM_GRANT_FAILED: 'Cấp quyền học thất bại',
    ORDER_CANCEL: 'Hủy đơn',
    PAYMENT_ACCEPT_LATE: 'Chấp nhận khoản thu muộn',
    PAYMENT_MARK_REFUNDED: 'Ghi nhận hoàn tiền',
    CERTIFICATE_REVOKE: 'Thu hồi chứng nhận',
    CERTIFICATE_PDF_RETRY: 'Yêu cầu tạo lại PDF',
    QUIZ_EXTRA_ATTEMPT_GRANT: 'Cấp thêm lượt quiz',
    ACCOUNT_LOCK: 'Khóa tài khoản',
    ACCOUNT_UNLOCK: 'Mở khóa tài khoản',
    ACCOUNT_CREATE: 'Tạo tài khoản',
    TWO_FACTOR_RESET: 'Đặt lại 2FA',
    GRANT_FAILURE_MODE_SET: 'Đổi chế độ mô phỏng lỗi cấp quyền',
};
const statuses: Record<string, string> = {
    ...orderLabels,
    ...itemLabels,
    HOAT_DONG: 'Hoạt động',
    BI_KHOA: 'Bị khóa',
    NHAP: 'Nháp',
    CHO_DUYET: 'Chờ duyệt',
    TRA_LAI: 'Trả lại',
    DA_DUYET: 'Đã duyệt',
    DA_CONG_BO: 'Đã công bố',
    DA_THAY_THE: 'Đã thay thế',
    CHUA_BAN: 'Chưa bán',
    DANG_BAN: 'Đang bán',
    NGUNG_BAN: 'Ngừng bán',
    HOP_LE: 'Hợp lệ',
    CAN_HOAN: 'Cần hoàn',
    DA_HOAN_LAI: 'Đã hoàn lại',
    HIEU_LUC: 'Hiệu lực',
    DA_THU_HOI: 'Đã thu hồi',
    KHOI_TAO: 'Khởi tạo',
    THANH_CONG: 'Thành công',
    THAT_BAI: 'Thất bại',
    CHUA_THIET_LAP: 'Chưa thiết lập',
    DA_THIET_LAP: 'Đã thiết lập',
    TU_DO: 'Tự do',
    TUAN_TU: 'Tuần tự',
    HOC_VIEN: 'Học viên',
    GIAO_VIEN: 'Giáo viên',
    GIAO_VU: 'Giáo vụ',
    QUAN_TRI: 'Quản trị',
};
const fields: Record<string, string> = {
    status: 'Trạng thái',
    roles: 'Vai trò',
    two_factor: 'Xác thực hai lớp',
    value: 'Giá trị',
    title: 'Tên khóa',
    slug: 'Đường dẫn',
    category_id: 'Danh mục',
    level_id: 'Cấp độ',
    learning_mode: 'Cách học',
    price: 'Giá bán',
    original_price: 'Giá gốc',
    extension_price: 'Giá gia hạn',
    amount: 'Số tiền',
    received_amount: 'Số tiền thực thu',
    requested_amount: 'Số tiền yêu cầu',
    access_months: 'Số tháng truy cập',
    sale_status: 'Trạng thái bán',
    author_id: 'Mã giáo viên',
    tag_ids: 'Mã thẻ',
    due_date: 'Hạn soạn',
    version_no: 'Phiên bản',
    source_version_id: 'Bản nguồn',
    current_version_id: 'Bản hiện hành',
    previous_version_id: 'Bản trước',
    enrollment_id: 'Mã ghi danh',
    quiz_id: 'Mã quiz',
    extra_attempts: 'Số lượt cấp thêm',
    max_attempts: 'Số lượt tối đa',
    order_status: 'Trạng thái đơn',
    reconcile_status: 'Đối soát',
    txn_status: 'Giao dịch',
    reconciled_at: 'Đối soát lúc',
    refunded_at: 'Hoàn lại lúc',
    accepted_late_at: 'Chấp nhận thu muộn lúc',
};

function formatValue(key: string, value: Value): string {
    if (value === null) return '—';
    if (Array.isArray(value))
        return value.map((entry) => formatValue(key, entry)).join(', ') || '—';
    if (typeof value === 'boolean') return value ? 'Có' : 'Không';
    if (/price|amount/.test(key) && Number.isFinite(Number(value)))
        return money(Number(value));
    if (key.endsWith('_at') && typeof value === 'string')
        return localTime(value);
    return typeof value === 'string'
        ? (statuses[value] ?? value)
        : String(value);
}

function Changes({ label, values }: { label: string; values: Values | null }) {
    const entries = Object.entries(values ?? {}).filter(([key]) =>
        Object.hasOwn(fields, key),
    );
    return (
        <div className="min-w-0 rounded-md bg-secondary p-2">
            <p className="mb-2 text-xs font-semibold">{label}</p>
            {entries.length ? (
                <dl className="grid gap-2">
                    {entries.map(([key, value]) => (
                        <OrderFact key={key} label={fields[key]}>
                            {formatValue(key, value)}
                        </OrderFact>
                    ))}
                </dl>
            ) : (
                '—'
            )}
        </div>
    );
}

export default function AuditLogs({
    logs,
    filters,
}: {
    logs: Paginator<Row>;
    filters: Filters;
}) {
    return (
        <Page title="Nhật ký thao tác" className="min-w-0">
            <Head title="Nhật ký thao tác" />
            <Block className="p-4 sm:p-6">
                <Form
                    {...index.form()}
                    className="grid min-w-0 items-end gap-3 sm:grid-cols-2 2xl:grid-cols-[repeat(4,minmax(0,1fr))_auto]"
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Thao tác"
                                htmlFor="audit-action"
                                error={errors.action}
                            >
                                <Input
                                    id="audit-action"
                                    name="action"
                                    maxLength={100}
                                    defaultValue={filters.action}
                                />
                            </Field>
                            <Field
                                label="Mã tài khoản"
                                htmlFor="audit-actor"
                                error={errors.actor_id}
                            >
                                <Input
                                    id="audit-actor"
                                    name="actor_id"
                                    type="number"
                                    min="1"
                                    defaultValue={filters.actor_id}
                                />
                            </Field>
                            <Field
                                label="Loại đối tượng"
                                htmlFor="audit-subject-type"
                                error={errors.subject_type}
                            >
                                <Input
                                    id="audit-subject-type"
                                    name="subject_type"
                                    maxLength={100}
                                    defaultValue={filters.subject_type}
                                />
                            </Field>
                            <Field
                                label="Mã đối tượng"
                                htmlFor="audit-subject"
                                error={errors.subject_id}
                            >
                                <Input
                                    id="audit-subject"
                                    name="subject_id"
                                    type="number"
                                    min="1"
                                    defaultValue={filters.subject_id}
                                />
                            </Field>
                            <Button type="submit" disabled={processing}>
                                {processing ? 'Đang tải nhật ký…' : 'Lọc'}
                            </Button>
                        </>
                    )}
                </Form>
            </Block>
            <div aria-live="polite">
                <Records
                    title="Nhật ký thao tác"
                    rows={logs.data}
                    rowKey={(row) => row.id}
                    empty="Chưa có bản ghi nhật ký."
                    columns={[
                        {
                            label: 'Thời điểm',
                            cell: (row) => localTime(row.created_at),
                        },
                        {
                            label: 'Thao tác',
                            cell: (row) => (
                                <strong>
                                    {actions[row.action] ?? row.action}
                                </strong>
                            ),
                        },
                        {
                            label: 'Tác nhân',
                            cell: (row) => (
                                <>
                                    {actors[row.actor_type] ?? '—'}
                                    {row.actor_id !== null &&
                                        ` #${row.actor_id}`}
                                </>
                            ),
                        },
                        {
                            label: 'Đối tượng',
                            cell: (row) =>
                                `${subjects[row.subject_type] ?? row.subject_type} #${row.subject_id}`,
                        },
                        {
                            label: 'Thay đổi',
                            cell: (row) => (
                                <div className="grid gap-2">
                                    <div className="grid gap-2 2xl:grid-cols-2">
                                        <Changes
                                            label="Giá trị cũ"
                                            values={row.old_values}
                                        />
                                        <Changes
                                            label="Giá trị mới"
                                            values={row.new_values}
                                        />
                                    </div>
                                    {row.reason && (
                                        <dl>
                                            <OrderFact label="Lý do">
                                                {row.reason}
                                            </OrderFact>
                                        </dl>
                                    )}
                                </div>
                            ),
                        },
                    ]}
                />
            </div>
            <Pagination page={logs} />
            <OrderReload
                only={['logs']}
                label="Tải lại nhật ký"
                failure="Không tải được nhật ký."
            />
        </Page>
    );
}
