import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';
import type { MouseEvent } from 'react';
import { Button } from '@/components/ui/button';
import publicRoutes from '@/routes/public';

type Props = {
    course: { title: string; slug: string; sale_status_label: string };
    section: string | null;
    lesson: {
        id: number;
        title: string;
        type: 'VIDEO' | 'PDF' | 'BAI_DOC' | 'QUIZ';
        type_label: string;
        objectives: string | null;
        content: string | null;
        minutes: number | null;
        video_url: string | null;
        pdf_url: string | null;
    };
    resources: { id: number; title: string; url: string }[];
};

export default function PreviewPage({
    course,
    section,
    lesson,
    resources,
}: Props) {
    const [failedUrl, setFailedUrl] = useState<string | null>(null);

    function openResource(event: MouseEvent<HTMLAnchorElement>) {
        const url = event.currentTarget.href;
        const expires = new URL(url).searchParams.get('expires');

        if (expires && Number(expires) * 1000 <= Date.now()) {
            event.preventDefault();
            setFailedUrl(url);
        }
    }

    return (
        <>
            <Head title={lesson.title} />
            <header className="bg-surface text-surface-ink">
                <div className="mx-auto flex max-w-6xl flex-col items-start gap-4 px-4 py-10 md:px-8 md:py-14">
                    <Link
                        href={publicRoutes.courses.show(course.slug)}
                        className="text-sm font-medium wrap-anywhere text-accent underline underline-offset-4"
                    >
                        Về khóa học {course.title}
                    </Link>
                    <p className="text-sm font-medium text-surface-muted">
                        Bài xem thử
                    </p>
                    <h1 className="text-3xl font-bold tracking-tight wrap-anywhere md:text-5xl">
                        {lesson.title}
                    </h1>
                </div>
            </header>
            <div className="mx-auto grid max-w-6xl items-start gap-8 px-4 py-8 md:px-8 lg:grid-cols-[minmax(0,1fr)_18rem]">
                <div className="flex min-w-0 flex-col gap-5">
                    {lesson.video_url && (
                        <video
                            key={lesson.video_url}
                            src={lesson.video_url}
                            controls
                            preload="metadata"
                            aria-label={lesson.title}
                            onError={(event) =>
                                setFailedUrl(event.currentTarget.src)
                            }
                            className="aspect-video w-full rounded-lg bg-surface"
                        />
                    )}
                    {lesson.pdf_url && (
                        <Button
                            asChild
                            variant="outline"
                            className="min-h-11 self-start"
                        >
                            <a
                                href={lesson.pdf_url}
                                target="_blank"
                                rel="noopener noreferrer"
                                onClick={openResource}
                            >
                                Mở PDF (tab mới)
                            </a>
                        </Button>
                    )}
                    <div aria-live="polite">
                        {failedUrl && (
                            <div className="flex flex-wrap items-center gap-3 rounded-lg border border-accent p-4">
                                <p>Liên kết đã hết hạn, hãy tải lại trang</p>
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => window.location.reload()}
                                >
                                    Tải lại trang
                                </Button>
                            </div>
                        )}
                    </div>
                    {lesson.objectives && (
                        <section className="flex flex-col gap-3">
                            <h2 className="text-xl font-bold">
                                Mục tiêu bài học
                            </h2>
                            <p className="leading-relaxed wrap-anywhere whitespace-pre-line">
                                {lesson.objectives}
                            </p>
                        </section>
                    )}
                    {lesson.content && (
                        <section className="flex flex-col gap-3">
                            <h2 className="text-xl font-bold">
                                Nội dung bài học
                            </h2>
                            <div
                                data-lenis-prevent
                                className="overflow-x-auto leading-relaxed wrap-anywhere [&_a]:underline [&_img]:h-auto [&_img]:max-w-full [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mb-3 [&_ul]:list-disc [&_ul]:pl-6"
                                dangerouslySetInnerHTML={{
                                    __html: lesson.content,
                                }}
                            />
                        </section>
                    )}
                </div>
                <aside className="flex min-w-0 flex-col gap-5 rounded-lg border bg-card p-5">
                    <section className="flex flex-col gap-3">
                        <h2 className="text-lg font-bold">Thông tin bài học</h2>
                        <ul className="flex flex-col gap-2 text-sm wrap-anywhere text-muted-foreground">
                            {section && <li>{section}</li>}
                            <li>{lesson.type_label}</li>
                            {lesson.minutes !== null && (
                                <li>{lesson.minutes} phút</li>
                            )}
                            <li>{course.sale_status_label}</li>
                        </ul>
                    </section>
                    {resources.length > 0 && (
                        <section className="flex flex-col gap-3 border-t pt-4">
                            <h2 className="text-lg font-bold">Tài nguyên</h2>
                            <ul className="flex flex-col gap-2 text-sm">
                                {resources.map((resource) => (
                                    <li
                                        key={resource.id}
                                        className="wrap-anywhere"
                                    >
                                        <a
                                            href={resource.url}
                                            target="_blank"
                                            rel="noopener noreferrer"
                                            onClick={openResource}
                                            className="font-medium underline underline-offset-4"
                                        >
                                            {resource.title}
                                            <span className="sr-only">
                                                {' '}
                                                (mở tab mới)
                                            </span>
                                        </a>
                                    </li>
                                ))}
                            </ul>
                        </section>
                    )}
                </aside>
            </div>
        </>
    );
}
