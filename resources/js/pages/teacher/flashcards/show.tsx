import { Form, Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import { Block, Field, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import teacher from '@/routes/teacher';

type CardRow = {
    id: number;
    front: string;
    back: string;
    speech_lang: string | null;
    image_url: string | null;
    back_version: number;
    update_url: string;
    destroy_url: string;
};

type Props = {
    set: {
        id: number;
        title: string;
        description: string | null;
        course_section_id: number | null;
        course: string;
        archived: boolean;
        update_url: string;
        card_store_url: string;
        archive_url: string;
    };
    sections: { id: number; title: string }[];
    cards: CardRow[];
    max_cards: number;
};

const langs = [
    ['', 'Không phát âm'],
    ['vi-VN', 'Tiếng Việt'],
    ['en-US', 'Tiếng Anh (Mỹ)'],
    ['en-GB', 'Tiếng Anh (Anh)'],
    ['fr-FR', 'Tiếng Pháp'],
    ['ja-JP', 'Tiếng Nhật'],
    ['zh-CN', 'Tiếng Trung'],
    ['ko-KR', 'Tiếng Hàn'],
];

function CardFields({
    card,
    errors,
}: {
    card?: CardRow;
    errors: Record<string, string>;
}) {
    const p = card ? `c${card.id}-` : 'new-';

    return (
        <div className="grid gap-3 sm:grid-cols-2">
            <Field label="Mặt trước" htmlFor={`${p}front`} error={errors.front}>
                <Input
                    id={`${p}front`}
                    name="front"
                    required
                    maxLength={500}
                    defaultValue={card?.front}
                />
            </Field>
            <Field
                label="Mặt sau"
                htmlFor={`${p}back`}
                error={errors.back}
                hint={
                    card
                        ? 'Đổi mặt sau sẽ đặt lại lịch ôn của mọi học viên.'
                        : undefined
                }
            >
                <Input
                    id={`${p}back`}
                    name="back"
                    required
                    maxLength={1000}
                    defaultValue={card?.back}
                />
            </Field>
            <Field
                label="Phát âm"
                htmlFor={`${p}lang`}
                error={errors.speech_lang}
            >
                <select
                    id={`${p}lang`}
                    name="speech_lang"
                    className={selectClass}
                    defaultValue={card?.speech_lang ?? ''}
                >
                    {langs.map(([v, l]) => (
                        <option key={v} value={v}>
                            {l}
                        </option>
                    ))}
                </select>
            </Field>
            <Field
                label="Ảnh (tùy chọn, ≤ 2 MB)"
                htmlFor={`${p}image`}
                error={errors.image}
            >
                <Input
                    id={`${p}image`}
                    name="image"
                    type="file"
                    accept="image/*"
                />
            </Field>
        </div>
    );
}

/** MH-GV-16: soạn thẻ của một bộ (A1 sửa mặt sau đặt lại lịch, A2 sửa phần khác giữ lịch). */
export default function FlashcardSetShow({
    set,
    sections,
    cards,
    max_cards,
}: Props) {
    const [editing, setEditing] = useState<number | null>(null);

    return (
        <Page
            title={set.title}
            eyebrow={
                <Link
                    href={teacher.flashcardSets.index()}
                    className="hover:underline"
                >
                    Bộ thẻ ghi nhớ · {set.course}
                </Link>
            }
            description={`${cards.length}/${max_cards} thẻ${set.archived ? ' · đã lưu trữ' : ''}`}
        >
            <Head title={set.title} />
            <Block>
                <Form
                    action={set.update_url}
                    method="put"
                    className="grid gap-3 sm:grid-cols-[1fr_1fr_14rem_auto] sm:items-end"
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <Field
                                label="Tên bộ thẻ"
                                htmlFor="title"
                                error={errors.title}
                            >
                                <Input
                                    id="title"
                                    name="title"
                                    required
                                    defaultValue={set.title}
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
                                    defaultValue={set.description ?? ''}
                                />
                            </Field>
                            <Field
                                label="Chương"
                                htmlFor="course_section_id"
                                error={errors.course_section_id}
                            >
                                <select
                                    id="course_section_id"
                                    name="course_section_id"
                                    className={selectClass}
                                    defaultValue={set.course_section_id ?? ''}
                                >
                                    <option value="">Cả khóa</option>
                                    {sections.map((s) => (
                                        <option key={s.id} value={s.id}>
                                            {s.title}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                            <Button
                                type="submit"
                                variant="outline"
                                disabled={processing}
                            >
                                Lưu bộ thẻ
                            </Button>
                        </>
                    )}
                </Form>
            </Block>

            <Block>
                <Form
                    action={set.card_store_url}
                    method="post"
                    className="grid gap-3"
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                >
                    {({ processing, errors }) => (
                        <>
                            <h2 className="font-semibold">Thêm thẻ</h2>
                            <CardFields errors={errors} />
                            <Button
                                type="submit"
                                disabled={
                                    processing || cards.length >= max_cards
                                }
                                className="justify-self-start"
                            >
                                Thêm thẻ
                            </Button>
                        </>
                    )}
                </Form>
            </Block>

            <Block className="p-0 md:p-0">
                <ol className="divide-y divide-border">
                    {cards.map((c, i) => (
                        <li key={c.id} className="grid gap-3 px-5 py-4 md:px-6">
                            {editing === c.id ? (
                                <Form
                                    action={c.update_url}
                                    method="post"
                                    className="grid gap-3"
                                    options={{ preserveScroll: true }}
                                    onSuccess={() => setEditing(null)}
                                >
                                    {({ processing, errors }) => (
                                        <>
                                            <CardFields
                                                card={c}
                                                errors={errors}
                                            />
                                            <div className="flex gap-2">
                                                <Button
                                                    type="submit"
                                                    size="sm"
                                                    disabled={processing}
                                                >
                                                    Lưu thẻ
                                                </Button>
                                                <Button
                                                    type="button"
                                                    size="sm"
                                                    variant="ghost"
                                                    onClick={() =>
                                                        setEditing(null)
                                                    }
                                                >
                                                    Hủy
                                                </Button>
                                            </div>
                                        </>
                                    )}
                                </Form>
                            ) : (
                                <div className="flex flex-wrap items-center gap-x-4 gap-y-2">
                                    <span className="w-6 text-right text-sm text-muted-foreground tabular-nums">
                                        {i + 1}
                                    </span>
                                    {c.image_url && (
                                        <img
                                            src={c.image_url}
                                            alt=""
                                            className="size-10 rounded object-cover"
                                        />
                                    )}
                                    <span className="min-w-0 flex-1 wrap-anywhere">
                                        <b>{c.front}</b>{' '}
                                        <span className="text-muted-foreground">
                                            — {c.back}
                                        </span>
                                        {c.speech_lang && (
                                            <span className="ml-2 text-xs text-muted-foreground">
                                                ({c.speech_lang})
                                            </span>
                                        )}
                                    </span>
                                    <Button
                                        size="sm"
                                        variant="outline"
                                        onClick={() => setEditing(c.id)}
                                    >
                                        Sửa
                                    </Button>
                                    <Button
                                        size="sm"
                                        variant="ghost"
                                        onClick={() =>
                                            router.delete(c.destroy_url, {
                                                preserveScroll: true,
                                            })
                                        }
                                    >
                                        Gỡ
                                    </Button>
                                </div>
                            )}
                        </li>
                    ))}
                    {cards.length === 0 && (
                        <li className="px-6 py-8 text-center text-muted-foreground">
                            Bộ thẻ chưa có thẻ.
                        </li>
                    )}
                </ol>
            </Block>
        </Page>
    );
}
