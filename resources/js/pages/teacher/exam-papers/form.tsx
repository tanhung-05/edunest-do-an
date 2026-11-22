import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    bank: { id: number; name: string; course: string | null };
    paper: { id: number; title: string; locked: boolean } | null;
    questions: {
        id: number;
        stem: string;
        type: string;
        difficulty: string;
        points: string;
    }[];
    selected: number[];
    submit_url: string;
    index_url: string;
};

export default function ExamPaperForm({
    bank,
    paper,
    questions,
    selected,
    submit_url,
    index_url,
}: Props) {
    const [chosen, setChosen] = useState(new Set(selected));
    const locked = paper?.locked ?? false;
    const points = questions
        .filter((q) => chosen.has(q.id))
        .reduce((sum, q) => sum + Number(q.points), 0);

    return (
        <Page
            title={paper ? paper.title : 'Tạo đề thi'}
            eyebrow={
                <Link href={index_url} className="hover:underline">
                    Đề thi thử
                </Link>
            }
            description={`Ngân hàng bảo mật "${bank.name}" · ${bank.course ?? ''}`}
        >
            <Head title={paper ? 'Sửa đề' : 'Tạo đề'} />
            <Form
                action={submit_url}
                method={paper ? 'put' : 'post'}
                transform={(data) => ({
                    ...data,
                    bank_id: bank.id,
                    question_ids: [...chosen],
                })}
                className="grid gap-4"
            >
                {({ processing, errors }) => (
                    <>
                        <Block className="grid gap-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-end">
                            <Field
                                label="Tên đề"
                                htmlFor="title"
                                error={errors.title}
                            >
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    maxLength={200}
                                    defaultValue={paper?.title}
                                    disabled={locked}
                                />
                            </Field>
                            <div className="flex items-center gap-3">
                                <span className="text-sm text-muted-foreground tabular-nums">
                                    {chosen.size} câu ·{' '}
                                    {points.toLocaleString('vi-VN')} điểm
                                </span>
                                {!locked && (
                                    <Button
                                        disabled={
                                            processing || chosen.size === 0
                                        }
                                    >
                                        {processing ? 'Đang lưu…' : 'Lưu đề'}
                                    </Button>
                                )}
                            </div>
                            {(errors.question_ids || errors.questions) && (
                                <p className="text-sm text-destructive sm:col-span-2">
                                    {errors.question_ids ?? errors.questions}
                                </p>
                            )}
                            {locked && (
                                <p className="text-sm text-muted-foreground sm:col-span-2">
                                    Đề đã khóa vì có kỳ thi sắp mở hoặc đã diễn
                                    ra.
                                </p>
                            )}
                        </Block>
                        <Block className="p-0 md:p-0">
                            <ul className="divide-y divide-border">
                                {questions.map((q) => (
                                    <li key={q.id}>
                                        <label className="flex cursor-pointer items-start gap-3 px-5 py-3 hover:bg-accent/40 md:px-6">
                                            <input
                                                type="checkbox"
                                                className="mt-1 size-4 accent-sky-600"
                                                checked={chosen.has(q.id)}
                                                disabled={locked}
                                                onChange={(e) => {
                                                    const next = new Set(
                                                        chosen,
                                                    );
                                                    if (e.target.checked)
                                                        next.add(q.id);
                                                    else next.delete(q.id);
                                                    setChosen(next);
                                                }}
                                            />
                                            <span className="min-w-0 flex-1">
                                                <span className="block wrap-anywhere">
                                                    {q.stem}
                                                </span>
                                                <span className="text-xs text-muted-foreground">
                                                    {q.type} · {q.difficulty} ·{' '}
                                                    {q.points} điểm
                                                </span>
                                            </span>
                                        </label>
                                    </li>
                                ))}
                                {questions.length === 0 && (
                                    <li className="px-6 py-8 text-center text-muted-foreground">
                                        Ngân hàng chưa có câu đang dùng.
                                    </li>
                                )}
                            </ul>
                        </Block>
                    </>
                )}
            </Form>
        </Page>
    );
}
