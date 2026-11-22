import { Form, Head, http, Link, router, usePage } from '@inertiajs/react';
import {
    Bot,
    BookOpenText,
    Check,
    ChevronDown,
    ChevronLeft,
    ChevronRight,
    CirclePlay,
    Clock3,
    FileText,
    Info,
    ListChecks,
    Lock,
    Paperclip,
    PlayCircle,
    Target,
    UserRound,
} from 'lucide-react';
import { useEffect, useMemo, useRef, useState } from 'react';
import type { ReactNode } from 'react';
import { ActionError } from '@/components/learning/actions';
import { NotesPanel, QnaPanel } from '@/components/learning/lesson-community';
import type { Community } from '@/components/learning/lesson-community';
import { VideoWatermark } from '@/components/learning/watermark';
import { RatingForm } from '@/components/public/course-commerce';
import type { Ratings } from '@/components/public/course-commerce';
import { LearningProgress } from '@/components/learning/records';
import { OrderReload } from '@/components/order/refresh';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import student from '@/routes/student';
import { complete } from '@/routes/student/lessons';
import { store as watchBeat } from '@/routes/student/lessons/watch-beats';
import type { Progress } from '@/types/learning';
import { minuteSecond, percentage } from '@/types/learning';

type OutlineLesson = {
    id: number;
    title: string;
    section_id: number;
    section: string;
    type: string;
    duration_ms: number | null;
    required: boolean;
    completed: boolean;
    url: string | null;
};

type LessonProps = {
    course: string;
    course_meta: { id: number; teacher: string | null; cover: string };
    quiz: {
        title: string | null;
        pass_percent: number | null;
        duration_minutes: number | null;
        max_attempts: number | null;
    } | null;
    has_new_version: boolean;
    switch_version_url: string | null;
    ai_url: string | null;
    community: Community;
    progress: Progress;
    rating: Ratings;
    lesson: {
        title: string;
        type: string;
        objectives: string | null;
        content: string | null;
    };
    position_ms: number | null;
    completed: boolean;
    video_url: string | null;
    pdf_url: string | null;
    resources: { title: string; url: string }[];
    watch_url: string;
    complete_url: string;
    quiz_url: string | null;
    lessons: OutlineLesson[];
};

type WatchResult = {
    counted: boolean;
    completed: boolean;
    valid_watch_ms: number;
    position_ms: number;
};

const lessonTypes: Record<string, string> = {
    VIDEO: 'Video',
    BAI_DOC: 'Bài đọc',
    PDF: 'Bài đọc PDF',
    QUIZ: 'Quiz',
};

type Tab =
    | 'tong-quan'
    | 'hoi-dap'
    | 'ghi-chu'
    | 'tai-lieu'
    | 'kiem-tra'
    | 'khoa-hoc';

export default function LessonPage(props: LessonProps) {
    return <Lesson key={props.watch_url} {...props} />;
}

function routePath(url: string) {
    return new URL(url, 'https://edunest.invalid').pathname.replace(/\/$/, '');
}

function duration(ms: number | null) {
    return ms ? minuteSecond(Math.round(ms / 1_000)) : null;
}

function TypeIcon({ type, className }: { type: string; className?: string }) {
    const Icon =
        type === 'VIDEO'
            ? PlayCircle
            : type === 'QUIZ'
              ? ListChecks
              : type === 'PDF'
                ? FileText
                : BookOpenText;

    return <Icon aria-hidden className={className} />;
}

/** MH-HV-03 trình học v5: đường dẫn, video có thanh thao tác, tab dưới video, đề cương theo chương. */
function Lesson(props: LessonProps) {
    const {
        course,
        course_meta: meta,
        lesson,
        progress,
        rating,
        lessons,
        video_url,
        pdf_url,
        resources,
        quiz_url,
        quiz,
    } = props;
    const { url } = usePage();
    const current = lessons.findIndex(
        (item) => item.url && routePath(item.url) === routePath(url),
    );
    const here = current < 0 ? null : lessons[current];
    const previous = current > 0 ? lessons[current - 1] : null;
    const next = current < 0 ? null : (lessons[current + 1] ?? null);
    const lessonId = here?.id;
    const courseId = Number(routePath(props.watch_url).split('/')[3]);
    const params =
        lessonId && courseId ? { course: courseId, lesson: lessonId } : null;
    const [resourceError, setResourceError] = useState('');
    const fileCount = resources.length + (pdf_url ? 1 : 0);
    const [tab, setTab] = useState<Tab>('tong-quan');
    const canComplete =
        !props.completed && ['BAI_DOC', 'PDF'].includes(lesson.type);
    const quizLessons = lessons.filter((item) => item.type === 'QUIZ');

    function checkResource(
        event: React.MouseEvent<HTMLAnchorElement>,
        signedUrl: string,
    ) {
        const expiry = Number(new URL(signedUrl).searchParams.get('expires'));

        if (expiry && Date.now() >= expiry * 1000) {
            event.preventDefault();
            setResourceError(
                'Liên kết đã hết hạn. Tải lại bài để lấy liên kết mới.',
            );
            router.reload({
                only: ['pdf_url', 'resources'],
                onSuccess: () => setResourceError(''),
                onNetworkError: () => false,
            });
        }
    }

    const completeButton = canComplete && (
        <Form
            {...(params
                ? complete.form(params)
                : { action: props.complete_url, method: 'post' as const })}
            options={{ preserveScroll: true }}
        >
            {({ processing, errors }) => (
                <div className="grid gap-1">
                    <Button
                        type="submit"
                        disabled={processing}
                        className="bg-emerald-600 text-white hover:bg-emerald-600/90"
                    >
                        <Check className="size-4" />
                        {processing ? 'Đang lưu…' : 'Đánh dấu hoàn thành'}
                    </Button>
                    <ActionError message={errors.general} />
                </div>
            )}
        </Form>
    );

    const tabs: { id: Tab; label: string; count?: number }[] = [
        { id: 'tong-quan', label: 'Tổng quan' },
        {
            id: 'hoi-dap',
            label: 'Hỏi đáp',
            count: props.community.discussions.length,
        },
        {
            id: 'ghi-chu',
            label: 'Ghi chú',
            count: props.community.notes.length,
        },
        { id: 'tai-lieu', label: 'Tài liệu', count: fileCount },
        { id: 'kiem-tra', label: 'Kiểm tra', count: quizLessons.length },
        { id: 'khoa-hoc', label: 'Khóa học' },
    ];

    return (
        <div className="mx-auto w-full max-w-[1520px] min-w-0 px-4 py-4 wrap-anywhere md:px-6 md:py-5">
            <Head title={lesson.title} />
            <nav
                aria-label="Đường dẫn"
                className="flex flex-wrap items-center gap-1.5 text-sm text-muted-foreground"
            >
                <Link
                    href={student.courses.index()}
                    className="hover:text-foreground"
                >
                    Khóa của tôi
                </Link>
                <ChevronRight aria-hidden className="size-3.5" />
                <span className="max-w-[40ch] truncate">{course}</span>
                <ChevronRight aria-hidden className="size-3.5" />
                <span className="max-w-[40ch] truncate text-foreground">
                    {lesson.title}
                </span>
            </nav>

            <div className="mt-3 grid min-w-0 items-start gap-5 xl:grid-cols-[minmax(0,1fr)_380px]">
                <div className="grid min-w-0 gap-4">
                    <header className="grid gap-2">
                        <h1 className="font-display text-2xl leading-tight font-bold tracking-tight md:text-[1.85rem]">
                            {lesson.title}
                        </h1>
                        <ul className="flex flex-wrap items-center gap-x-4 gap-y-1.5 text-sm text-muted-foreground">
                            <li className="flex items-center gap-1.5">
                                <TypeIcon
                                    type={lesson.type}
                                    className="size-4"
                                />
                                {lessonTypes[lesson.type] ?? 'Bài học'}
                            </li>
                            {duration(here?.duration_ms ?? null) && (
                                <li className="flex items-center gap-1.5">
                                    <Clock3 aria-hidden className="size-4" />
                                    {duration(here?.duration_ms ?? null)}
                                </li>
                            )}
                            {here && (
                                <li>
                                    Bài {current + 1}/{lessons.length},{' '}
                                    {here.section}
                                </li>
                            )}
                            {meta.teacher && (
                                <li className="flex items-center gap-1.5">
                                    <UserRound aria-hidden className="size-4" />
                                    {meta.teacher}
                                </li>
                            )}
                            {props.ai_url && (
                                <li>
                                    <Link
                                        href={props.ai_url}
                                        className="inline-flex items-center gap-1.5 rounded-full border px-2.5 py-0.5 font-medium text-foreground hover:bg-muted"
                                    >
                                        <Bot aria-hidden className="size-3.5" />
                                        Hỏi AI về bài này
                                    </Link>
                                </li>
                            )}
                            <li>
                                {props.completed ? (
                                    <span className="inline-flex items-center gap-1 rounded-full bg-emerald-50 px-2.5 py-0.5 font-medium text-emerald-700 ring-1 ring-emerald-200 dark:bg-emerald-400/10 dark:text-emerald-300 dark:ring-emerald-400/30">
                                        <Check className="size-3.5" />
                                        Đã hoàn thành
                                    </span>
                                ) : (
                                    <span className="inline-flex rounded-full bg-amber-50 px-2.5 py-0.5 font-medium text-amber-800 ring-1 ring-amber-200 dark:bg-amber-400/10 dark:text-amber-200 dark:ring-amber-400/30">
                                        Đang học
                                    </span>
                                )}
                            </li>
                        </ul>
                    </header>

                    {props.has_new_version && (
                        <div
                            role="status"
                            className="flex items-center gap-2 rounded-xl border border-sky-200 bg-sky-50 px-4 py-2.5 text-sm text-sky-900 dark:border-sky-400/30 dark:bg-sky-400/10 dark:text-sky-100"
                        >
                            <Info className="size-4 shrink-0" />
                            <span className="flex-1">
                                Khóa học có phiên bản mới. Bạn đang học theo
                                phiên bản đã ghi danh.
                            </span>
                            {props.switch_version_url && (
                                <Form
                                    action={props.switch_version_url}
                                    method="post"
                                    onBefore={() =>
                                        window.confirm(
                                            'Chuyển sang phiên bản mới? Tiến độ bài không đổi nội dung được giữ; quiz phải làm lại trên bản mới.',
                                        )
                                    }
                                >
                                    {({ processing }) => (
                                        <Button
                                            size="sm"
                                            variant="outline"
                                            disabled={processing}
                                        >
                                            Chuyển sang bản mới
                                        </Button>
                                    )}
                                </Form>
                            )}
                        </div>
                    )}

                    <section
                        aria-label="Nội dung bài"
                        className="overflow-hidden rounded-2xl border border-border bg-card shadow-soft"
                    >
                        {video_url ? (
                            <LessonVideo
                                key={video_url}
                                url={video_url}
                                watchUrl={
                                    params
                                        ? watchBeat(params).url
                                        : props.watch_url
                                }
                                position={props.position_ms}
                                completed={props.completed}
                            />
                        ) : lesson.content ? (
                            <article
                                className="max-w-[75ch] overflow-x-auto px-5 py-6 text-[1.02rem] leading-relaxed md:px-8 md:py-8 [&_a]:underline [&_h2]:mt-6 [&_h2]:mb-2 [&_h2]:text-xl [&_h2]:font-semibold [&_h3]:mt-4 [&_h3]:mb-2 [&_h3]:font-semibold [&_img]:h-auto [&_img]:max-w-full [&_img]:rounded-xl [&_ol]:list-decimal [&_ol]:pl-6 [&_p]:mb-3 [&_ul]:list-disc [&_ul]:pl-6"
                                // HTML đã lọc phía máy chủ lúc soạn (HtmlSanitizer).
                                dangerouslySetInnerHTML={{
                                    __html: lesson.content,
                                }}
                            />
                        ) : (
                            <StagePoster
                                cover={meta.cover}
                                type={lesson.type}
                                action={
                                    pdf_url ? (
                                        <a
                                            href={pdf_url}
                                            onClick={(event) =>
                                                checkResource(event, pdf_url)
                                            }
                                            className="inline-flex h-11 items-center gap-2 rounded-full bg-white px-6 font-semibold text-slate-950 transition-transform hover:-translate-y-0.5"
                                        >
                                            <FileText className="size-4" />
                                            Đọc PDF
                                        </a>
                                    ) : quiz_url ? (
                                        <Link
                                            href={quiz_url}
                                            className="inline-flex h-11 items-center gap-2 rounded-full bg-white px-6 font-semibold text-slate-950 transition-transform hover:-translate-y-0.5"
                                        >
                                            <ListChecks className="size-4" />
                                            Làm quiz
                                        </Link>
                                    ) : null
                                }
                            />
                        )}

                        <div className="flex flex-wrap items-center gap-2 border-t border-border px-3 py-3 md:px-4">
                            {previous?.url ? (
                                <Button variant="ghost" asChild>
                                    <Link href={previous.url}>
                                        <ChevronLeft className="size-4" />
                                        Bài trước
                                    </Link>
                                </Button>
                            ) : null}
                            {fileCount > 0 && (
                                <Button
                                    variant="ghost"
                                    onClick={() => setTab('tai-lieu')}
                                >
                                    <Paperclip className="size-4" />
                                    Tài liệu ({fileCount})
                                </Button>
                            )}
                            {quiz_url && (
                                <Button variant="ghost" asChild>
                                    <Link href={quiz_url}>
                                        <ListChecks className="size-4" />
                                        Mở quiz
                                    </Link>
                                </Button>
                            )}
                            <div className="ml-auto flex flex-wrap items-center gap-2">
                                {completeButton}
                                {next?.url ? (
                                    <Button asChild>
                                        <Link href={next.url}>
                                            Bài tiếp theo
                                            <ChevronRight className="size-4" />
                                        </Link>
                                    </Button>
                                ) : next ? (
                                    <span className="flex items-center gap-1.5 text-sm text-muted-foreground">
                                        <Lock className="size-4" />
                                        Hoàn thành bài bắt buộc để mở bài tiếp
                                        theo
                                    </span>
                                ) : null}
                            </div>
                        </div>
                    </section>
                    <ActionError message={resourceError} />

                    <section className="min-w-0 rounded-2xl border border-border bg-card shadow-soft">
                        <div
                            role="tablist"
                            aria-label="Thông tin bài học"
                            className="flex [scrollbar-width:none] gap-1 overflow-x-auto overflow-y-hidden border-b border-border px-3 md:px-5"
                        >
                            {tabs.map((item) => (
                                <button
                                    key={item.id}
                                    type="button"
                                    role="tab"
                                    id={`tab-${item.id}`}
                                    aria-selected={tab === item.id}
                                    aria-controls={`panel-${item.id}`}
                                    onClick={() => setTab(item.id)}
                                    className={cn(
                                        '-mb-px flex shrink-0 items-center gap-2 border-b-2 px-3 py-3.5 text-sm font-medium transition-colors',
                                        tab === item.id
                                            ? 'border-foreground text-foreground'
                                            : 'border-transparent text-muted-foreground hover:text-foreground',
                                    )}
                                >
                                    {item.label}
                                    {item.count !== undefined &&
                                        item.count > 0 && (
                                            <span className="rounded-full bg-accent px-2 py-0.5 text-xs">
                                                {item.count}
                                            </span>
                                        )}
                                </button>
                            ))}
                        </div>
                        <div
                            role="tabpanel"
                            id={`panel-${tab}`}
                            aria-labelledby={`tab-${tab}`}
                            className="p-5 md:p-6"
                        >
                            {tab === 'tong-quan' && (
                                <OverviewPanel
                                    lesson={lesson}
                                    section={here?.section ?? null}
                                    rating={rating}
                                />
                            )}
                            {tab === 'hoi-dap' && (
                                <QnaPanel
                                    community={props.community}
                                    isVideo={lesson.type === 'VIDEO'}
                                />
                            )}
                            {tab === 'ghi-chu' && (
                                <NotesPanel
                                    community={props.community}
                                    isVideo={lesson.type === 'VIDEO'}
                                />
                            )}
                            {tab === 'tai-lieu' && (
                                <FilesPanel
                                    pdfUrl={pdf_url}
                                    resources={resources}
                                    onOpen={checkResource}
                                />
                            )}
                            {tab === 'kiem-tra' && (
                                <QuizPanel
                                    quiz={quiz}
                                    quizUrl={quiz_url}
                                    quizLessons={quizLessons}
                                />
                            )}
                            {tab === 'khoa-hoc' && (
                                <div className="grid max-w-xl gap-4">
                                    <p className="font-semibold">{course}</p>
                                    <LearningProgress progress={progress} />
                                    {progress.required === 0 && (
                                        <p className="text-sm text-muted-foreground">
                                            Khóa học chưa có bài bắt buộc.
                                        </p>
                                    )}
                                    {meta.teacher && (
                                        <p className="text-sm text-muted-foreground">
                                            Giáo viên: {meta.teacher}
                                        </p>
                                    )}
                                </div>
                            )}
                        </div>
                    </section>

                    <OrderReload
                        only={[
                            'video_url',
                            'pdf_url',
                            'resources',
                            'position_ms',
                            'completed',
                            'lessons',
                            'progress',
                        ]}
                        label="Tải lại bài"
                        failure="Không tải được nội dung bài."
                    />
                </div>

                <CourseOutline
                    course={course}
                    cover={meta.cover}
                    lessons={lessons}
                    current={current}
                    progress={progress}
                />
            </div>
        </div>
    );
}

function StagePoster({
    cover,
    type,
    action,
}: {
    cover: string;
    type: string;
    action: ReactNode;
}) {
    return (
        <div className="relative isolate grid aspect-video max-h-[520px] w-full place-items-center overflow-hidden bg-slate-950 text-white">
            <img
                src={cover}
                alt=""
                className="absolute inset-0 -z-20 h-full w-full object-cover"
            />
            <div className="absolute inset-0 -z-10 bg-slate-950/70" />
            <div className="grid justify-items-center gap-4 p-6 text-center">
                <span className="grid size-14 place-items-center rounded-2xl bg-white/15 backdrop-blur">
                    <TypeIcon type={type} className="size-7" />
                </span>
                {action}
            </div>
        </div>
    );
}

function OverviewPanel({
    lesson,
    section,
    rating,
}: {
    lesson: LessonProps['lesson'];
    section: string | null;
    rating: Ratings;
}) {
    return (
        <div className="grid gap-5">
            <div className="grid gap-5 md:grid-cols-[minmax(0,1fr)_16rem]">
                <div className="grid content-start gap-3">
                    <h2 className="flex items-center gap-2 font-semibold">
                        <Target className="size-4 text-muted-foreground" />
                        Mục tiêu bài học
                    </h2>
                    {lesson.objectives ? (
                        <p className="leading-relaxed whitespace-pre-wrap text-foreground/90">
                            {lesson.objectives}
                        </p>
                    ) : (
                        <p className="text-sm text-muted-foreground">
                            Giáo viên chưa ghi mục tiêu cho bài này.
                        </p>
                    )}
                </div>
                <dl className="grid content-start gap-3 rounded-xl bg-accent/60 p-4 text-sm">
                    <div>
                        <dt className="text-muted-foreground">Loại bài</dt>
                        <dd className="font-medium">
                            {lessonTypes[lesson.type] ?? 'Bài học'}
                        </dd>
                    </div>
                    {section && (
                        <div>
                            <dt className="text-muted-foreground">Chương</dt>
                            <dd className="font-medium">{section}</dd>
                        </div>
                    )}
                    <div>
                        <dt className="text-muted-foreground">
                            Điều kiện hoàn thành
                        </dt>
                        <dd className="font-medium">
                            {lesson.type === 'VIDEO'
                                ? 'Xem hợp lệ 90% thời lượng'
                                : lesson.type === 'QUIZ'
                                  ? 'Đạt điểm qua của quiz'
                                  : 'Đọc xong và bấm hoàn thành'}
                        </dd>
                    </div>
                </dl>
            </div>
            <RatingForm ratings={rating} />
        </div>
    );
}

function FilesPanel({
    pdfUrl,
    resources,
    onOpen,
}: {
    pdfUrl: string | null;
    resources: LessonProps['resources'];
    onOpen: (event: React.MouseEvent<HTMLAnchorElement>, url: string) => void;
}) {
    const files = [
        ...(pdfUrl ? [{ title: 'Bài đọc PDF', url: pdfUrl }] : []),
        ...resources,
    ];

    if (files.length === 0) {
        return (
            <p className="text-sm text-muted-foreground">
                Bài này không có tệp đính kèm.
            </p>
        );
    }

    return (
        <ul className="grid gap-2 sm:grid-cols-2">
            {files.map((file) => (
                <li key={file.url}>
                    <a
                        href={file.url}
                        onClick={(event) => onOpen(event, file.url)}
                        className="group flex items-center gap-3 rounded-xl border border-border p-3 transition-colors hover:bg-accent/60"
                    >
                        <span className="grid size-10 shrink-0 place-items-center rounded-lg bg-rose-50 text-rose-600 dark:bg-rose-400/10 dark:text-rose-300">
                            <FileText className="size-5" />
                        </span>
                        <span className="min-w-0 flex-1 font-medium wrap-anywhere">
                            {file.title}
                        </span>
                        <ChevronRight className="size-4 text-muted-foreground transition-transform group-hover:translate-x-0.5" />
                    </a>
                </li>
            ))}
        </ul>
    );
}

function QuizPanel({
    quiz,
    quizUrl,
    quizLessons,
}: {
    quiz: LessonProps['quiz'];
    quizUrl: string | null;
    quizLessons: OutlineLesson[];
}) {
    return (
        <div className="grid gap-5">
            {quiz && quizUrl ? (
                <div className="flex flex-wrap items-center gap-4 rounded-xl border border-border p-4">
                    <span className="grid size-12 place-items-center rounded-xl bg-sky-50 text-sky-700 dark:bg-sky-400/10 dark:text-sky-300">
                        <ListChecks className="size-6" />
                    </span>
                    <div className="min-w-0 flex-1">
                        <p className="font-semibold">
                            {quiz.title ?? 'Quiz của bài'}
                        </p>
                        <p className="text-sm text-muted-foreground">
                            {[
                                quiz.pass_percent !== null &&
                                    `Điểm qua ${quiz.pass_percent}%`,
                                quiz.duration_minutes !== null &&
                                    `${quiz.duration_minutes} phút`,
                                quiz.max_attempts !== null
                                    ? `Tối đa ${quiz.max_attempts} lượt`
                                    : 'Không giới hạn lượt',
                            ]
                                .filter(Boolean)
                                .join(', ')}
                        </p>
                    </div>
                    <Button asChild>
                        <Link href={quizUrl}>Làm quiz</Link>
                    </Button>
                </div>
            ) : (
                <p className="text-sm text-muted-foreground">
                    Bài này không có quiz riêng.
                </p>
            )}
            {quizLessons.length > 0 && (
                <div className="grid gap-2">
                    <h2 className="text-sm font-semibold">Quiz trong khóa</h2>
                    <ul className="grid gap-1.5">
                        {quizLessons.map((item) => (
                            <li
                                key={item.id}
                                className="flex items-center gap-3 rounded-lg px-3 py-2 text-sm hover:bg-accent/60"
                            >
                                {item.completed ? (
                                    <Check className="size-4 text-emerald-600" />
                                ) : item.url ? (
                                    <ListChecks className="size-4 text-muted-foreground" />
                                ) : (
                                    <Lock className="size-4 text-muted-foreground/60" />
                                )}
                                {item.url ? (
                                    <Link
                                        href={item.url}
                                        className="min-w-0 flex-1 hover:underline"
                                    >
                                        {item.title}
                                    </Link>
                                ) : (
                                    <span className="min-w-0 flex-1 text-muted-foreground">
                                        {item.title}
                                    </span>
                                )}
                                <span className="text-xs text-muted-foreground">
                                    {item.completed
                                        ? 'Đã đạt'
                                        : item.url
                                          ? 'Có thể làm'
                                          : 'Chưa mở'}
                                </span>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}

/** Bảng "Nội dung khóa học": tiến độ, chương gập mở, số bài xong và tổng thời lượng mỗi chương. */
function CourseOutline({
    course,
    cover,
    lessons,
    current,
    progress,
}: {
    course: string;
    cover: string;
    lessons: OutlineLesson[];
    current: number;
    progress: Progress;
}) {
    const sections = useMemo(() => {
        const list: {
            id: number;
            title: string;
            items: { lesson: OutlineLesson; index: number }[];
        }[] = [];

        lessons.forEach((lesson, index) => {
            const last = list.at(-1);

            if (last && last.id === lesson.section_id) {
                last.items.push({ lesson, index });
            } else {
                list.push({
                    id: lesson.section_id,
                    title: lesson.section,
                    items: [{ lesson, index }],
                });
            }
        });

        return list;
    }, [lessons]);
    const currentSection = lessons[current]?.section_id;
    const [open, setOpen] = useState<Record<number, boolean>>({});
    const percent =
        progress.required === 0
            ? 0
            : Math.round((100 * progress.completed) / progress.required);

    return (
        <aside className="min-w-0 overflow-hidden rounded-2xl border border-border bg-card shadow-soft xl:sticky xl:top-4">
            <div className="grid gap-3 border-b border-border p-5">
                <div className="flex items-center gap-3">
                    <img
                        src={cover}
                        alt=""
                        className="size-12 shrink-0 rounded-xl object-cover"
                    />
                    <p className="min-w-0 text-sm leading-snug font-semibold wrap-anywhere">
                        {course}
                    </p>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <h2 className="font-semibold">Nội dung khóa học</h2>
                    <span className="text-sm text-muted-foreground">
                        {progress.completed}/{progress.required} bài bắt buộc
                    </span>
                </div>
                <div
                    role="progressbar"
                    aria-label="Tiến độ khóa học"
                    aria-valuemin={0}
                    aria-valuemax={100}
                    aria-valuenow={percent}
                    className="h-2 overflow-hidden rounded-full bg-accent"
                >
                    <div
                        className="h-full rounded-full bg-emerald-500 transition-[width] duration-700"
                        style={{ width: `${percent}%` }}
                    />
                </div>
                <p className="text-xs text-muted-foreground">
                    Đã xong {percentage(progress.percent)} khóa học
                </p>
            </div>
            <nav
                aria-label="Các bài học"
                data-lenis-prevent
                className="max-h-[calc(100svh-14rem)] overflow-y-auto"
            >
                {sections.map((section, sectionIndex) => {
                    const isOpen =
                        open[section.id] ?? section.id === currentSection;
                    const done = section.items.filter(
                        ({ lesson }) => lesson.completed,
                    ).length;
                    const total = section.items.reduce(
                        (sum, { lesson }) => sum + (lesson.duration_ms ?? 0),
                        0,
                    );

                    return (
                        <div
                            key={section.id}
                            className="border-b border-border last:border-0"
                        >
                            <button
                                type="button"
                                aria-expanded={isOpen}
                                onClick={() =>
                                    setOpen((state) => ({
                                        ...state,
                                        [section.id]: !isOpen,
                                    }))
                                }
                                className="flex w-full items-center gap-3 px-5 py-3.5 text-left transition-colors hover:bg-accent/50"
                            >
                                <span className="min-w-0 flex-1">
                                    <span className="block text-sm font-semibold">
                                        {/^chương\s/i.test(section.title)
                                            ? section.title
                                            : `Chương ${sectionIndex + 1}. ${section.title}`}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        {done}/{section.items.length} bài
                                        {total > 0 && `, ${duration(total)}`}
                                    </span>
                                </span>
                                <ChevronDown
                                    aria-hidden
                                    className={cn(
                                        'size-4 shrink-0 text-muted-foreground transition-transform duration-200',
                                        isOpen && 'rotate-180',
                                    )}
                                />
                            </button>
                            {isOpen && (
                                <ol className="grid gap-0.5 px-2 pb-3">
                                    {section.items.map(({ lesson, index }) => (
                                        <OutlineRow
                                            key={lesson.id}
                                            lesson={lesson}
                                            here={index === current}
                                        />
                                    ))}
                                </ol>
                            )}
                        </div>
                    );
                })}
            </nav>
        </aside>
    );
}

function OutlineRow({
    lesson,
    here,
}: {
    lesson: OutlineLesson;
    here: boolean;
}) {
    const status = lesson.completed
        ? 'Hoàn thành'
        : !lesson.url
          ? 'Chưa mở'
          : here
            ? 'Đang học'
            : 'Có thể học';
    const body = (
        <>
            <span
                aria-hidden
                className={cn(
                    'grid size-6 shrink-0 place-items-center rounded-full',
                    lesson.completed
                        ? 'bg-emerald-500 text-white'
                        : here
                          ? 'bg-primary text-primary-foreground'
                          : 'text-muted-foreground',
                )}
            >
                {lesson.completed ? (
                    <Check className="size-3.5" strokeWidth={3} />
                ) : here ? (
                    <CirclePlay className="size-3.5" />
                ) : !lesson.url ? (
                    <Lock className="size-3.5" />
                ) : (
                    <TypeIcon type={lesson.type} className="size-4" />
                )}
            </span>
            <span className="min-w-0 flex-1">
                <span
                    className={cn(
                        'block text-sm leading-snug',
                        here && 'font-semibold',
                        !lesson.url && 'text-muted-foreground',
                    )}
                >
                    {lesson.title}
                </span>
                <span className="sr-only">{status}</span>
                {!lesson.required && (
                    <span className="text-xs text-muted-foreground">
                        Không bắt buộc
                    </span>
                )}
            </span>
            {duration(lesson.duration_ms) && (
                <span className="shrink-0 text-xs text-muted-foreground tabular-nums">
                    {duration(lesson.duration_ms)}
                </span>
            )}
        </>
    );
    const row = cn(
        'flex items-start gap-3 rounded-xl px-3 py-2.5',
        here && 'bg-accent ring-1 ring-border',
    );

    return (
        <li>
            {lesson.url ? (
                <Link
                    href={lesson.url}
                    aria-current={here ? 'page' : undefined}
                    className={cn(row, !here && 'hover:bg-accent/60')}
                >
                    {body}
                </Link>
            ) : (
                <div className={row} title="Hoàn thành bài bắt buộc đứng trước">
                    {body}
                </div>
            )}
        </li>
    );
}

function LessonVideo({
    url,
    watchUrl,
    position,
    completed,
}: {
    url: string;
    watchUrl: string;
    position: number | null;
    completed: boolean;
}) {
    const player = useRef<HTMLVideoElement>(null);
    const refreshAttempted = useRef(false);
    const [valid, setValid] = useState<number | null>(null);
    const [videoLength, setVideoLength] = useState<number | null>(null);
    const [savedPosition, setSavedPosition] = useState(position);
    const [playbackBlocked, setPlaybackBlocked] = useState(false);
    const [message, setMessage] = useState('');

    useEffect(() => {
        const video = player.current;

        if (!video) {
            return;
        }

        let active = true;
        let pending = false;
        let queued = false;
        let wasCompleted = completed;
        let lastPosition = position;
        let denied = false;

        async function beat() {
            if (!video || denied || video.seeking || video.readyState < 1) {
                return;
            }

            if (pending) {
                queued = true;

                return;
            }

            const currentPosition = Math.round(video.currentTime * 1_000);
            pending = true;

            try {
                const response = await http.getClient().request({
                    method: 'post',
                    url: watchUrl,
                    data: { position_ms: currentPosition },
                    headers: { Accept: 'application/json' },
                });

                if (!active) {
                    return;
                }

                const result = JSON.parse(response.data) as WatchResult;
                setValid(result.valid_watch_ms);
                setSavedPosition(result.position_ms);

                if (
                    lastPosition !== null &&
                    currentPosition > lastPosition &&
                    !result.counted
                ) {
                    setMessage(
                        'Mốc xem này không được tính vào thời lượng hợp lệ.',
                    );
                } else {
                    setMessage('');
                }

                lastPosition = result.position_ms;

                if (result.completed && !wasCompleted) {
                    wasCompleted = true;
                    router.reload({
                        only: ['completed', 'lessons', 'progress'],
                    });
                }
            } catch (error) {
                if (!active) {
                    return;
                }

                denied = true;
                video.pause();
                setPlaybackBlocked(true);
                const status =
                    error && typeof error === 'object' && 'response' in error
                        ? (error.response as { status?: number } | undefined)
                              ?.status
                        : undefined;
                setMessage(
                    status === 403
                        ? 'Quyền học không còn hiệu lực.'
                        : 'Không lưu được tiến độ. Hãy tải lại bài.',
                );
            } finally {
                pending = false;

                if (queued && active) {
                    queued = false;
                    void beat();
                }
            }
        }

        const send = () => {
            void beat();
        };
        const timer = window.setInterval(() => {
            if (!video.paused && !video.ended) {
                send();
            }
        }, 10_000);
        video.addEventListener('playing', send);
        video.addEventListener('pause', send);
        video.addEventListener('seeked', send);
        video.addEventListener('ended', send);

        return () => {
            active = false;
            window.clearInterval(timer);
            video.removeEventListener('playing', send);
            video.removeEventListener('pause', send);
            video.removeEventListener('seeked', send);
            video.removeEventListener('ended', send);
        };
    }, [completed, position, watchUrl]);

    return (
        <div className="relative grid">
            <VideoWatermark />
            <video
                ref={player}
                controls={!playbackBlocked}
                playsInline
                preload="metadata"
                src={url}
                aria-label="Video bài học"
                className="aspect-video max-h-[70svh] w-full min-w-0 bg-slate-950"
                onPlay={({ currentTarget }) => {
                    if (playbackBlocked) {
                        currentTarget.pause();
                    }
                }}
                onLoadedMetadata={({ currentTarget: video }) => {
                    if (Number.isFinite(video.duration) && video.duration > 0) {
                        setVideoLength(video.duration);

                        if (position !== null) {
                            video.currentTime = Math.min(
                                position / 1_000,
                                video.duration,
                            );
                        }
                    }
                }}
                onError={() => {
                    const expiry = Number(
                        new URL(url).searchParams.get('expires'),
                    );

                    if (
                        !refreshAttempted.current &&
                        expiry &&
                        Date.now() >= expiry * 1_000
                    ) {
                        refreshAttempted.current = true;
                        setMessage(
                            'Liên kết đã hết hạn. Đang tải liên kết mới…',
                        );
                        router.reload({
                            only: ['video_url', 'position_ms'],
                            onNetworkError: () => {
                                setMessage(
                                    'Liên kết đã hết hạn. Tải lại bài để lấy liên kết mới.',
                                );

                                return false;
                            },
                        });
                    } else {
                        setMessage(
                            'Không phát được video. Tải lại bài để lấy liên kết mới.',
                        );
                    }
                }}
            />
            <div className="flex flex-wrap items-center gap-x-4 gap-y-1 border-t border-border px-4 py-2.5 text-xs text-muted-foreground md:px-5">
                {savedPosition !== null && (
                    <span>
                        Vị trí đã lưu{' '}
                        {minuteSecond(Math.floor(savedPosition / 1_000))}
                    </span>
                )}
                <span>
                    {valid !== null && videoLength !== null
                        ? `Đã xem hợp lệ ${percentage(Math.min(100, valid / (videoLength * 10)))}`
                        : 'Cần xem hợp lệ 90% để hoàn thành'}
                </span>
                <ActionError message={message} />
            </div>
        </div>
    );
}
