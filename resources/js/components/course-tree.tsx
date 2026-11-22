import type { Review, SectionNode } from '@/types/course';
import { dateVi } from '@/types/course';

/** Cây chương/bài chỉ đọc (xem duyệt, xem trước). */
export function CourseTree({
    sections,
    renderLessonExtra,
}: {
    sections: SectionNode[];
    renderLessonExtra?: (lessonId: number) => React.ReactNode;
}) {
    if (sections.length === 0) {
        return <p className="text-muted-foreground">Khóa chưa có bài nào.</p>;
    }

    return (
        <div className="grid gap-4">
            {sections.map((s) => (
                <section key={s.id} className="rounded-lg border">
                    <h3 className="bg-surface px-4 py-2 font-semibold text-surface-ink">
                        {s.title}
                    </h3>
                    <ol className="divide-y">
                        {s.lessons.map((l) => (
                            <li key={l.id} className="grid gap-2 p-4">
                                <div className="flex flex-wrap items-baseline gap-2">
                                    <span className="font-semibold">
                                        Bài {l.number}. {l.title}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {l.type_label}
                                        {l.is_required ? ' · bắt buộc' : ''}
                                        {l.is_final_test
                                            ? ' · kiểm tra cuối'
                                            : ''}
                                    </span>
                                </div>
                                {l.content && (
                                    <div
                                        className="prose prose-sm max-w-none"
                                        dangerouslySetInnerHTML={{
                                            __html: l.content,
                                        }}
                                    />
                                )}
                                {l.files?.video && (
                                    <video
                                        controls
                                        preload="metadata"
                                        controlsList="nodownload"
                                        className="aspect-video w-full max-w-2xl bg-black"
                                        src={l.files.video}
                                    />
                                )}
                                {l.files?.pdf && (
                                    <a
                                        href={l.files.pdf}
                                        target="_blank"
                                        rel="noreferrer"
                                        className="text-sm underline underline-offset-4"
                                    >
                                        Mở tệp PDF của bài
                                    </a>
                                )}
                                {l.files && l.resources.length > 0 && (
                                    <ul className="grid gap-1 text-sm">
                                        {l.resources.map((r) =>
                                            l.files?.resources[String(r.id)] ? (
                                                <li key={r.id}>
                                                    <a
                                                        href={
                                                            l.files.resources[
                                                                String(r.id)
                                                            ]
                                                        }
                                                        target="_blank"
                                                        rel="noreferrer"
                                                        className="underline underline-offset-4"
                                                    >
                                                        Tài liệu: {r.title}
                                                    </a>
                                                </li>
                                            ) : null,
                                        )}
                                    </ul>
                                )}
                                {l.type === 'VIDEO' && (
                                    <p className="text-sm">
                                        {l.has_video
                                            ? `Video${l.video_duration_ms ? ` · ${Math.round(l.video_duration_ms / 60000)} phút` : ''}`
                                            : 'Chưa có video'}
                                    </p>
                                )}
                                {l.type === 'PDF' && (
                                    <p className="text-sm">
                                        {l.has_pdf
                                            ? 'Có tệp PDF'
                                            : 'Chưa có PDF'}
                                    </p>
                                )}
                                {l.quiz && (
                                    <div className="text-sm">
                                        Quiz: {l.quiz.questions.length} câu ·
                                        đạt {l.quiz.pass_percent ?? '—'}% ·{' '}
                                        {l.quiz.duration_minutes ?? '—'} phút ·{' '}
                                        {l.quiz.max_attempts ?? '—'} lượt
                                        <ol className="mt-1 list-decimal pl-6">
                                            {l.quiz.questions.map((q) => (
                                                <li key={q.id}>{q.stem}</li>
                                            ))}
                                        </ol>
                                    </div>
                                )}
                                {renderLessonExtra?.(l.id)}
                            </li>
                        ))}
                    </ol>
                </section>
            ))}
        </div>
    );
}

/** Lịch sử duyệt (AC-03-30). */
export function ReviewHistory({
    reviews,
    lessonTitle,
}: {
    reviews: Review[];
    lessonTitle: (id: number) => string;
}) {
    if (reviews.length === 0) {
        return <p className="text-muted-foreground">Chưa có lượt duyệt.</p>;
    }

    return (
        <ol className="grid gap-3">
            {reviews.map((r) => (
                <li key={r.id} className="rounded-md border p-3 text-sm">
                    <div className="font-semibold">
                        {r.result_label} · {r.reviewer} · {dateVi(r.created_at)}
                    </div>
                    {r.reason && <p className="mt-1">Lý do: {r.reason}</p>}
                    {r.comments.length > 0 && (
                        <ul className="mt-1 list-disc pl-5">
                            {r.comments.map((c) => (
                                <li key={c.lesson_id}>
                                    {lessonTitle(c.lesson_id)}: {c.comment}
                                </li>
                            ))}
                        </ul>
                    )}
                </li>
            ))}
        </ol>
    );
}

export function lessonTitleFinder(sections: SectionNode[]) {
    const map = new Map<number, string>();
    sections.forEach((s) =>
        s.lessons.forEach((l) => map.set(l.id, `Bài ${l.number} "${l.title}"`)),
    );

    return (id: number) => map.get(id) ?? `Bài #${id}`;
}
