import { Head, Link } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { RankingTable } from '@/components/exams';
import { AttemptAnswers } from '@/components/learning/attempt-answers';
import { Block, Page, Stat } from '@/components/page';
import type { RankRow } from '@/types/exams';
import type { Question } from '@/types/learning';

type Props = {
    event: { id: number; title: string; status_label: string; url: string };
    attempt: {
        status_label: string;
        close_reason: string | null;
        started_at: string;
        closed_at: string | null;
        percent: string | null;
        correct_count: number | null;
        question_count: number;
        rank: number | null;
        unranked: boolean;
    };
    published: boolean;
    answers_visible: boolean;
    questions: Question[];
    ranking: RankRow[];
};

/** MH-HV-15: kết quả lượt thi; điểm và hạng chỉ hiện khi kỳ công bố, đáp án khi mọi kỳ dùng đề đã đóng (CR-71). */
export default function ExamResult({
    event,
    attempt,
    published,
    answers_visible,
    questions,
    ranking,
}: Props) {
    return (
        <Page
            title={`Kết quả: ${event.title}`}
            eyebrow={
                <Link href={event.url} className="hover:underline">
                    Về trang kỳ thi
                </Link>
            }
        >
            <Head title="Kết quả thi thử" />
            <div className="grid gap-4 sm:grid-cols-3">
                <Stat label="Trạng thái" value={attempt.status_label} />
                <Stat
                    label="Điểm"
                    value={
                        published ? `${attempt.percent ?? '0'}%` : 'Chờ công bố'
                    }
                    tone={published ? 'accent' : 'plain'}
                />
                <Stat
                    label="Hạng"
                    value={
                        !published
                            ? '—'
                            : attempt.unranked
                              ? 'Không xếp hạng'
                              : (attempt.rank ?? '—')
                    }
                />
            </div>
            <Block className="text-sm">
                Bắt đầu {vnTime(attempt.started_at)} · Chốt{' '}
                {vnTime(attempt.closed_at)}
                {attempt.close_reason && (
                    <> · Lý do chốt: {attempt.close_reason}</>
                )}
                {published && attempt.correct_count !== null && (
                    <>
                        {' '}
                        · Đúng {attempt.correct_count}/{attempt.question_count}{' '}
                        câu
                    </>
                )}
                {!published && (
                    <p className="mt-2 text-muted-foreground">
                        Kỳ thi ({event.status_label.toLowerCase()}) chưa công bố
                        kết quả. Bạn sẽ nhận thông báo khi có kết quả.
                    </p>
                )}
            </Block>
            {published && (
                <Block className="grid gap-3">
                    <h2 className="text-lg font-semibold">
                        Bảng xếp hạng (top 10)
                    </h2>
                    <RankingTable rows={ranking} />
                </Block>
            )}
            <Block className="grid gap-3">
                <h2 className="text-lg font-semibold">Đáp án</h2>
                {answers_visible ? (
                    <AttemptAnswers questions={questions} />
                ) : (
                    <p className="text-sm text-muted-foreground">
                        Đáp án mở khi mọi kỳ dùng đề này đã đóng.
                    </p>
                )}
            </Block>
        </Page>
    );
}
