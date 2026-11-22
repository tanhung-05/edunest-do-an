import { dateVi } from '@/types/course';

export type Progress = {
    completed: number;
    required: number;
    percent: string | null;
};
export type LearningEntry = {
    lesson_id: number;
    url: string;
    position_ms: number | null;
};
export type Validity = 'CON_HAN' | 'HET_HAN' | 'DA_THU_HOI';
export type LearningCourse = {
    id: number;
    course_id: number;
    cover: string | null;
    title: string;
    validity: Validity;
    expires_at: string | null;
    expires_soon: boolean;
    progress: Progress;
    learning_entry: LearningEntry | null;
    /** Khóa đã học xong: dẫn về bài đầu để ôn lại. */
    review_url?: string | null;
};
export type StudentCourse = LearningCourse & {
    renew_url: string | null;
    extension_price: number | null;
    version: number;
    validity_label: string;
    learning_status: string;
};

export type Attempt = {
    id: number;
    attempt_no: number;
    status: string;
    status_label: string;
    close_reason: string | null;
    started_at: string;
    deadline_at: string;
    closed_at: string | null;
    score_earned: string | null;
    score_max: string | null;
    percent: string | null;
    passed: boolean | null;
    url: string;
    correct_count: number | null;
    question_count: number;
};

/** Câu trả lời: id lựa chọn (sắp theo id, hoặc theo thứ tự với câu sắp xếp) hoặc chuỗi với câu điền từ. */
export type AnswerValue = number[] | string | null;

export type Question = {
    id: number;
    stem: string | null;
    type:
        | 'MOT_DAP_AN'
        | 'NHIEU_DAP_AN'
        | 'DUNG_SAI'
        | 'DIEN_TU'
        | 'SAP_XEP'
        | null;
    choices: {
        id: number;
        content: string;
        is_correct?: boolean;
        position?: number;
    }[];
    answer: AnswerValue;
    save_url: string;
    explanation?: string | null;
    correct?: boolean;
};

export type Paginator<T> = {
    data: T[];
    prev_page_url: string | null;
    next_page_url: string | null;
    current_page: number;
    last_page: number;
};

export type EnrollmentRow = Progress & {
    id: number;
    student: string | null;
    student_url: string;
    course: string | null;
    version: number | null;
    status: string;
    validity: string;
    expires_at: string | null;
    grants: { title: string; url: string }[];
    revoked: boolean;
    revoke_url: string | null;
    restore_url: string | null;
};

export type Certificate = {
    number: string;
    course: string;
    issued_on: string;
    status: string;
    pdf_status: string;
};

export type ReportSection<T> = {
    state: 'ready' | 'empty' | 'error';
    data: T | null;
};
export type Snapshot<T> = { value: T; computed_at: string };
export type Revenue = {
    total: number;
    label: string;
    by_payment_date: Record<string, number> | [];
};
export type CourseOption = { id: number; title: string };

export function localTime(value: string | null | undefined) {
    if (!value) return dateVi(value);
    const date = new Date(value);
    if (date.getTimezoneOffset() === -420) return dateVi(value);
    return date.toLocaleString('vi-VN', { timeZone: 'Asia/Ho_Chi_Minh' });
}

export const localDate = (value: string) =>
    new Date(value + 'T00:00:00+07:00').toLocaleDateString('vi-VN', {
        timeZone: 'Asia/Ho_Chi_Minh',
    });

export const percentage = (value: number | string | null) =>
    value === null
        ? '—'
        : Number(value).toLocaleString('vi-VN', {
              minimumFractionDigits: 1,
              maximumFractionDigits: 1,
          }) + '%';

export function minuteSecond(seconds: number) {
    return (
        Math.floor(seconds / 60) + ':' + String(seconds % 60).padStart(2, '0')
    );
}

export const validityLabels: Record<Validity, string> = {
    CON_HAN: 'Còn hạn',
    HET_HAN: 'Đã hết hạn truy cập',
    DA_THU_HOI: 'Đã thu hồi quyền học',
};

export const pdfLabels: Record<string, string> = {
    'Chờ tạo': 'Đang tạo PDF',
    'Đã tạo': 'PDF sẵn sàng',
    Lỗi: 'Tạo PDF lỗi',
};
