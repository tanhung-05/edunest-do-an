import { Form, Head, Link, router } from '@inertiajs/react';
import { BookOpen, Copy, Layers, PlayCircle } from 'lucide-react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';

type Props = {
    sets: {
        id: number;
        title: string;
        course: string;
        archived: boolean;
        cards: number;
        url: string;
        archive_url: string;
    }[];
    courses: { id: number; title: string }[];
    store_url: string;
};

/** MH-GV-16: bộ thẻ ghi nhớ do Giáo viên tự tạo cho khóa được giao (BR-XP-53, CR-92). */
export default function FlashcardSets({ sets, courses, store_url }: Props) {
    return (
        <Page
            title="Bộ thẻ ghi nhớ"
            description="Thẻ do bạn tự soạn, không lấy từ ngân hàng câu hỏi. Học viên ôn theo lịch lặp lại ngắt quãng và chơi mini game."
            stats={[
                { label: 'Bộ thẻ', value: sets.length, icon: Layers },
                {
                    label: 'Tổng số thẻ',
                    value: sets.reduce((n, s) => n + s.cards, 0),
                    icon: Copy,
                },
                {
                    label: 'Đang dùng',
                    value: sets.filter((s) => !s.archived).length,
                    icon: PlayCircle,
                },
                {
                    label: 'Khóa được giao',
                    value: courses.length,
                    icon: BookOpen,
                },
            ]}
        >
            <Head title="Bộ thẻ ghi nhớ" />
            <div className="grid items-start gap-5 lg:grid-cols-[1fr_22rem]">
                <Block className="p-0 md:p-0">
                    <ul className="divide-y divide-border">
                        {sets.map((s) => (
                            <li
                                key={s.id}
                                className="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 md:px-6"
                            >
                                <Link
                                    href={s.url}
                                    className="min-w-0 flex-1 hover:underline"
                                >
                                    <p className="font-semibold wrap-anywhere">
                                        {s.title}{' '}
                                        {s.archived && (
                                            <span className="text-sm font-normal text-muted-foreground">
                                                · đã lưu trữ
                                            </span>
                                        )}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {s.course} · {s.cards} thẻ
                                    </p>
                                </Link>
                                <Button
                                    variant="ghost"
                                    size="sm"
                                    onClick={() =>
                                        router.post(
                                            s.archive_url,
                                            { archived: !s.archived },
                                            { preserveScroll: true },
                                        )
                                    }
                                >
                                    {s.archived ? 'Mở lại' : 'Lưu trữ'}
                                </Button>
                            </li>
                        ))}
                        {sets.length === 0 && (
                            <li className="px-6 py-8 text-center text-muted-foreground">
                                Chưa có bộ thẻ.
                            </li>
                        )}
                    </ul>
                </Block>
                <Block>
                    <Form
                        action={store_url}
                        method="post"
                        className="grid gap-3"
                        resetOnSuccess
                    >
                        {({ processing, errors }) => (
                            <>
                                <h2 className="font-semibold">Tạo bộ thẻ</h2>
                                <Field
                                    label="Khóa học"
                                    htmlFor="course_id"
                                    error={errors.course_id}
                                >
                                    <select
                                        id="course_id"
                                        name="course_id"
                                        required
                                        className={selectClass}
                                        defaultValue={courses[0]?.id}
                                    >
                                        {courses.map((c) => (
                                            <option key={c.id} value={c.id}>
                                                {c.title}
                                            </option>
                                        ))}
                                    </select>
                                </Field>
                                <Field
                                    label="Tên bộ thẻ"
                                    htmlFor="title"
                                    error={errors.title}
                                >
                                    <Input
                                        id="title"
                                        name="title"
                                        required
                                        maxLength={200}
                                    />
                                </Field>
                                <Field
                                    label="Mô tả"
                                    htmlFor="description"
                                    error={errors.description}
                                >
                                    <Input
                                        id="description"
                                        name="description"
                                        maxLength={1000}
                                    />
                                </Field>
                                <Button
                                    type="submit"
                                    disabled={
                                        processing || courses.length === 0
                                    }
                                >
                                    Tạo
                                </Button>
                            </>
                        )}
                    </Form>
                </Block>
            </div>
        </Page>
    );
}
