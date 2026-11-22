import { answerText, hasAnswer } from '@/components/learning/question-input';
import { LearningBadge } from '@/components/learning/records';
import type { Question } from '@/types/learning';

/** Đáp án và lời giải sau khi chốt; loại N hiện câu đã điền / thứ tự đã chọn so với đáp án. */
export function AttemptAnswers({ questions }: { questions: Question[] }) {
    return (
        <ol className="grid gap-3">
            {questions.map((question, index) => {
                const ordered = question.type === 'SAP_XEP';
                const filled = question.type === 'DIEN_TU';
                const expected = ordered
                    ? question.choices
                          .toSorted(
                              (a, b) => (a.position ?? 0) - (b.position ?? 0),
                          )
                          .map((c) => c.content)
                          .join(' → ')
                    : null;

                return (
                    <li
                        key={question.id}
                        className="grid gap-3 rounded-md border p-3"
                    >
                        <div className="flex flex-wrap items-start justify-between gap-2">
                            <h3 className="min-w-0 font-medium wrap-anywhere whitespace-pre-wrap">
                                Câu {index + 1}. {question.stem}
                            </h3>
                            <LearningBadge attention={!question.correct}>
                                {question.correct
                                    ? 'Đúng'
                                    : hasAnswer(question.answer)
                                      ? 'Sai'
                                      : 'Bỏ trống'}
                            </LearningBadge>
                        </div>
                        {ordered || filled ? (
                            <dl className="grid gap-2 text-sm">
                                <div className="rounded-md bg-secondary p-2 wrap-anywhere">
                                    <dt className="font-medium">Bạn trả lời</dt>
                                    <dd>{answerText(question)}</dd>
                                </div>
                                <div className="rounded-md bg-secondary p-2 wrap-anywhere">
                                    <dt className="font-medium">
                                        {filled
                                            ? 'Đáp án chấp nhận'
                                            : 'Thứ tự đúng'}
                                    </dt>
                                    <dd>
                                        {filled
                                            ? question.choices
                                                  .map((c) => c.content)
                                                  .join(' · ')
                                            : expected}
                                    </dd>
                                </div>
                            </dl>
                        ) : (
                            <ul className="grid gap-2 text-sm">
                                {question.choices.map((choice) => (
                                    <li
                                        className="rounded-md bg-secondary p-2 wrap-anywhere whitespace-pre-wrap"
                                        key={choice.id}
                                    >
                                        {choice.content}
                                        {choice.is_correct && (
                                            <strong> · Đáp án đúng</strong>
                                        )}
                                        {Array.isArray(question.answer) &&
                                            question.answer.includes(
                                                choice.id,
                                            ) && <strong> · Đã chọn</strong>}
                                    </li>
                                ))}
                            </ul>
                        )}
                        {question.explanation && (
                            <p className="border-l-4 border-accent pl-3 text-sm wrap-anywhere whitespace-pre-wrap">
                                {question.explanation}
                            </p>
                        )}
                    </li>
                );
            })}
        </ol>
    );
}
