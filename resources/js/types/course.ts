export type Option = { value: string; label: string };
export type IdName = { id: number; name: string };
/** CR-259: giáo viên kèm chuyên môn (id danh mục môn). */
export type TeacherOption = IdName & { subject_ids: number[] };

export type CourseRow = {
    id: number;
    title: string;
    slug: string;
    price: number;
    sale_status: string;
    sale_status_label: string;
    author: string | null;
    due_date: string | null;
    overdue: boolean;
    open_version: {
        id: number;
        version_no: number;
        status: string;
        status_label: string;
    } | null;
    published_version_id: number | null;
    return_reason?: string | null;
};

export type CourseDetail = CourseRow & {
    category_id: number;
    category: string;
    level_id: number;
    level: string;
    learning_mode: string;
    learning_mode_label: string;
    original_price: number | null;
    access_months: number | null;
    extension_price: number | null;
    tag_ids: number[];
    tags: string[];
    author_id: number | null;
    cover: string | null;
};

export type QuizInfo = {
    pass_percent: number | null;
    duration_minutes: number | null;
    max_attempts: number | null;
    questions: { id: number; stem: string }[];
};

export type LessonNode = {
    id: number;
    number: number;
    title: string;
    type: string;
    type_label: string;
    objectives: string | null;
    content: string | null;
    is_required: boolean;
    is_preview: boolean;
    is_final_test: boolean;
    has_video: boolean;
    video_duration_ms: number | null;
    has_pdf: boolean;
    resources: { id: number; title: string; size_bytes: number }[];
    quiz: QuizInfo | null;
    /** Nhánh staff-preview (NH-61): URL ký cho nhân sự, chỉ có ở trang xem trước/xem để duyệt. */
    files?: {
        video: string | null;
        pdf: string | null;
        resources: Record<string, string>;
    };
};

export type SectionNode = { id: number; title: string; lessons: LessonNode[] };

export type Review = {
    id: number;
    result: string;
    result_label: string;
    reason: string | null;
    reviewer: string;
    created_at: string | null;
    comments: { lesson_id: number; comment: string }[];
};

export type VersionInfo = {
    id: number;
    version_no: number;
    status: string;
    status_label: string;
};

export type ChecklistItem = {
    code: string;
    lesson_id: number | null;
    message: string;
};

export type CourseOptions = {
    categories: IdName[];
    levels: IdName[];
    tags: IdName[];
    teachers: TeacherOption[];
    learningModes: Option[];
};

export const money = (n: number | null | undefined) =>
    n === null || n === undefined ? '—' : n.toLocaleString('vi-VN') + 'đ';

export const dateVi = (iso: string | null | undefined) =>
    iso ? new Date(iso).toLocaleString('vi-VN') : '—';
