/** Kỳ thi thử (M18) — dữ liệu dùng chung giữa phòng thi và trang tổ chức. */
export type ExamSummary = {
    id: number;
    title: string;
    status: 'NHAP' | 'SAP_MO' | 'DANG_MO' | 'DA_DONG' | 'DA_CONG_BO';
    status_label: string;
    paper: string | null;
    opens_at: string;
    closes_at: string;
    duration_minutes: number;
    audience: string;
    requires_registration: boolean;
    publish_at: string | null;
    published_at: string | null;
};

export type RankRow = {
    rank: number;
    name: string;
    percent: string;
    mine?: boolean;
};

export type Analytics = {
    progress: {
        assigned: number;
        not_started: number;
        in_progress: number;
        submitted: number;
        timed_out: number;
    };
    scores: {
        count: number;
        average: string | null;
        median: string | null;
        max: string | null;
        min: string | null;
    };
    buckets: { label: string; count: number }[];
    questions: {
        position: number;
        content: string;
        type: string | null;
        answered: number;
        correct: number;
        wrong: number;
        blank: number;
        accuracy: string | null;
        choices: { content: string; is_correct: boolean; count: number }[];
    }[];
};

export const examTone: Record<ExamSummary['status'], string> = {
    NHAP: 'bg-muted text-muted-foreground',
    SAP_MO: 'bg-sky-100 text-sky-900 dark:bg-sky-400/15 dark:text-sky-200',
    DANG_MO:
        'bg-emerald-100 text-emerald-900 dark:bg-emerald-400/15 dark:text-emerald-200',
    DA_DONG: 'bg-amber-soft text-navy',
    DA_CONG_BO: 'bg-navy text-white dark:bg-white/15',
};
