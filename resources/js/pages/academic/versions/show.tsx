import { Form, Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import {
    CourseTree,
    lessonTitleFinder,
    ReviewHistory,
} from '@/components/course-tree';
import { Block, Field, Page, textareaClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import courses from '@/routes/academic/courses';
import versions from '@/routes/academic/course-versions';
import type { Review, SectionNode, VersionInfo } from '@/types/course';

type VersionDiff = {
    base: number;
    added: string[];
    removed: string[];
    changed: string[];
    renamed: { from: string; to: string }[];
};

/** MH-GVU-05: xem bản, duyệt / trả lại có nhận xét theo bài, công bố (QT-03 A4–A6). */
export default function AcademicVersionShow({
    course,
    version,
    sections,
    reviews,
    can,
    independence,
    diff,
}: {
    course: { id: number; title: string; sale_status_label: string };
    version: VersionInfo;
    sections: SectionNode[];
    reviews: Review[];
    can: { review: boolean; publish: boolean };
    independence: string | null;
    diff: VersionDiff | null;
}) {
    const [returning, setReturning] = useState(false);
    const title = lessonTitleFinder(sections);

    return (
        <Page
            title={`${course.title} · Bản ${version.version_no}`}
            description={`${version.status_label} · ${course.sale_status_label}`}
            actions={
                <div className="flex flex-wrap gap-2">
                    <Button variant="outline" asChild>
                        <Link href={courses.show(course.id)}>Về khóa</Link>
                    </Button>
                    {can.review && (
                        <>
                            <ConfirmAction
                                form={versions.approve.form(version.id)}
                                label="Duyệt"
                                title="Duyệt bản này?"
                                description="Bản chuyển sang Đã duyệt, chờ công bố."
                                confirmLabel="Duyệt"
                            />
                            <Button
                                variant="outline"
                                onClick={() => setReturning((v) => !v)}
                            >
                                {returning ? 'Đóng trả lại' : 'Trả lại'}
                            </Button>
                        </>
                    )}
                    {can.publish && (
                        <ConfirmAction
                            form={versions.publish.form(version.id)}
                            label="Công bố"
                            title="Công bố bản này?"
                            description="Bản trở thành nội dung chính thức của khóa. Bật bán là bước riêng."
                            confirmLabel="Công bố"
                        />
                    )}
                </div>
            }
        >
            <Head title={`Bản ${version.version_no} – ${course.title}`} />
            {independence && version.status === 'CHO_DUYET' && (
                <Block tone="accent">
                    <p className="font-semibold">{independence}</p>
                </Block>
            )}

            {diff && (
                <Block className="grid gap-3">
                    <h2 className="text-lg font-semibold">
                        So với bản đang công bố (bản {diff.base})
                    </h2>
                    {diff.added.length +
                        diff.removed.length +
                        diff.changed.length +
                        diff.renamed.length ===
                    0 ? (
                        <p className="text-sm text-muted-foreground">
                            Không có bài thêm, bỏ hoặc đổi nội dung.
                        </p>
                    ) : (
                        <dl className="grid gap-3 text-sm sm:grid-cols-2">
                            {(
                                [
                                    ['Bài thêm', diff.added],
                                    ['Bài bỏ', diff.removed],
                                    ['Bài đổi nội dung', diff.changed],
                                    [
                                        'Bài đổi tên',
                                        diff.renamed.map(
                                            (r) => `${r.from} → ${r.to}`,
                                        ),
                                    ],
                                ] as const
                            )
                                .filter(([, items]) => items.length > 0)
                                .map(([label, items]) => (
                                    <div key={label}>
                                        <dt className="font-medium">
                                            {label} ({items.length})
                                        </dt>
                                        <dd>
                                            <ul className="mt-1 list-disc pl-5 text-muted-foreground">
                                                {items.map((t) => (
                                                    <li key={t}>{t}</li>
                                                ))}
                                            </ul>
                                        </dd>
                                    </div>
                                ))}
                        </dl>
                    )}
                </Block>
            )}

            {returning && (
                <Block>
                    <h2 className="mb-4 text-lg font-bold">Trả lại bản</h2>
                    <Form
                        {...versions.return.form(version.id)}
                        options={{ preserveScroll: true }}
                        onSuccess={() => setReturning(false)}
                        className="grid gap-4"
                    >
                        {({ processing, errors }) => (
                            <>
                                <Field
                                    label="Lý do trả lại"
                                    htmlFor="reason"
                                    error={errors.reason}
                                >
                                    <textarea
                                        id="reason"
                                        name="reason"
                                        required
                                        maxLength={5000}
                                        className={textareaClass}
                                    />
                                </Field>
                                <details>
                                    <summary className="cursor-pointer text-sm font-medium">
                                        Nhận xét theo bài (không bắt buộc)
                                    </summary>
                                    <div className="mt-3 grid gap-3">
                                        {sections.flatMap((s) =>
                                            s.lessons.map((l) => (
                                                <Field
                                                    key={l.id}
                                                    label={title(l.id)}
                                                    htmlFor={`c-${l.id}`}
                                                >
                                                    <textarea
                                                        id={`c-${l.id}`}
                                                        name={`comments[${l.id}]`}
                                                        maxLength={5000}
                                                        className={
                                                            textareaClass
                                                        }
                                                    />
                                                </Field>
                                            )),
                                        )}
                                    </div>
                                </details>
                                {errors.general && (
                                    <p
                                        role="alert"
                                        className="text-sm text-destructive"
                                    >
                                        {errors.general}
                                    </p>
                                )}
                                <Button
                                    disabled={processing}
                                    variant="destructive"
                                >
                                    Trả lại
                                </Button>
                            </>
                        )}
                    </Form>
                </Block>
            )}

            <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                <CourseTree sections={sections} />
                <Block>
                    <h2 className="mb-3 text-lg font-bold">Lịch sử duyệt</h2>
                    <ReviewHistory reviews={reviews} lessonTitle={title} />
                </Block>
            </div>
        </Page>
    );
}
