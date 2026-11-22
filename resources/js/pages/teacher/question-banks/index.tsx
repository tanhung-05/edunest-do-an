import { Form, Head, Link } from '@inertiajs/react';
import { Database, ListChecks } from 'lucide-react';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import teacher from '@/routes/teacher';

type Bank = { id: number; name: string; questions_count: number };

/** MH-GV-07: các ngân hàng câu hỏi của khóa (QT-04). */
export default function QuestionBanks({
    course,
    banks,
}: {
    course: { id: number; title: string };
    banks: Bank[];
}) {
    return (
        <Page
            title="Ngân hàng câu hỏi"
            description={course.title}
            actions={
                <Button variant="outline" asChild>
                    <Link href={teacher.courses.show(course.id)}>
                        Về trình soạn
                    </Link>
                </Button>
            }
            stats={[
                { label: 'Ngân hàng', value: banks.length, icon: Database },
                {
                    label: 'Tổng câu hỏi',
                    value: banks.reduce((n, b) => n + b.questions_count, 0),
                    icon: ListChecks,
                },
            ]}
        >
            <Head title={`Ngân hàng câu hỏi – ${course.title}`} />
            <Block>
                <Form
                    {...teacher.courses.questionBanks.store.form(course.id)}
                    resetOnSuccess
                    className="flex flex-wrap gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                name="name"
                                placeholder="Tên ngân hàng mới"
                                aria-label="Tên ngân hàng mới"
                                required
                                maxLength={150}
                                className="max-w-sm"
                            />
                            <Button disabled={processing}>Tạo ngân hàng</Button>
                            {errors.name && (
                                <p className="w-full text-sm text-destructive">
                                    {errors.name}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </Block>
            <ul className="grid gap-3 md:grid-cols-2">
                {banks.map((b) => (
                    <li
                        key={b.id}
                        className="flex items-center justify-between rounded-lg border p-4"
                    >
                        <Link
                            href={teacher.questionBanks.show(b.id)}
                            className="font-semibold underline"
                        >
                            {b.name}
                        </Link>
                        <span className="text-sm text-muted-foreground">
                            {b.questions_count} câu
                        </span>
                    </li>
                ))}
                {banks.length === 0 && (
                    <li className="text-muted-foreground">
                        Chưa có ngân hàng nào.
                    </li>
                )}
            </ul>
        </Page>
    );
}
