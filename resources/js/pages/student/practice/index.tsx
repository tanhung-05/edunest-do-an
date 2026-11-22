import { Head, Link, router } from '@inertiajs/react';
import {
    BookOpen,
    Clock3,
    Dumbbell,
    Heart,
    History,
    Lightbulb,
    RotateCcw,
    Zap,
} from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';

type Activity = {
    id: number;
    title: string;
    question_count: number;
    seconds_per_question: number;
    draw_mode: string;
    open_session_url: string | null;
    start_url: string;
};

type Course = {
    id: number;
    title: string;
    active: boolean;
    activities: Activity[];
};

type HistoryRow = {
    id: number;
    title: string;
    mode: string;
    status: string;
    status_label: string;
    ended_at: string | null;
    xp: number;
    url: string;
};

/** MH-HV-16: hoạt động luyện tập theo khóa; chọn Learning hoặc Try Hard (QT-22). */
export default function PracticeIndex({
    courses,
    history,
}: {
    courses: Course[];
    history: HistoryRow[];
}) {
    const start = (url: string, mode: 'LEARNING' | 'TRY_HARD') =>
        router.post(url, { mode });

    return (
        <Page
            title="Luyện tập"
            description="Learning cho phép trả lời lại một lần và xin một gợi ý mỗi câu. Try Hard có giờ cho từng câu và 3 mạng. XP tính theo cấp lúc bắt đầu phiên."
            stats={[
                {
                    label: 'Khóa đang học',
                    value: courses.filter((c) => c.active).length,
                    icon: BookOpen,
                },
                {
                    label: 'Hoạt động',
                    value: courses.reduce((n, c) => n + c.activities.length, 0),
                    icon: Dumbbell,
                },
                {
                    label: 'Phiên gần đây',
                    value: history.length,
                    icon: History,
                },
                {
                    label: 'XP từ luyện tập',
                    value: history.reduce((n, h) => n + h.xp, 0),
                    icon: Zap,
                },
            ]}
        >
            <Head title="Luyện tập" />
            {courses.length === 0 && (
                <Block>
                    <p className="text-muted-foreground">
                        Khóa bạn đang học chưa có hoạt động luyện tập.
                    </p>
                </Block>
            )}
            {courses.map((course) => (
                <Block key={course.id} className="grid gap-4">
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-semibold wrap-anywhere">
                            {course.title}
                        </h2>
                        {!course.active && (
                            <span className="text-sm text-destructive">
                                Ghi danh đã hết hạn — chỉ làm nốt phiên đang dở
                            </span>
                        )}
                    </div>
                    <ul className="grid gap-3 md:grid-cols-2">
                        {course.activities.map((a) => (
                            <li
                                key={a.id}
                                className="grid gap-3 rounded-xl border border-border p-4"
                            >
                                <div>
                                    <p className="font-medium wrap-anywhere">
                                        {a.title}
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        {a.question_count} câu · {a.draw_mode} ·
                                        Try Hard {a.seconds_per_question}{' '}
                                        giây/câu
                                    </p>
                                </div>
                                <div className="flex flex-wrap gap-2">
                                    {a.open_session_url && (
                                        <Button asChild variant="secondary">
                                            <Link href={a.open_session_url}>
                                                <RotateCcw /> Làm tiếp
                                            </Link>
                                        </Button>
                                    )}
                                    <Button
                                        disabled={!course.active}
                                        onClick={() =>
                                            start(a.start_url, 'LEARNING')
                                        }
                                    >
                                        <Lightbulb /> Learning
                                    </Button>
                                    <Button
                                        variant="outline"
                                        disabled={!course.active}
                                        onClick={() =>
                                            start(a.start_url, 'TRY_HARD')
                                        }
                                    >
                                        <Clock3 /> Try Hard
                                        <Heart className="text-destructive" />3
                                    </Button>
                                </div>
                                {a.open_session_url && (
                                    <p className="text-xs text-muted-foreground">
                                        Bắt đầu phiên mới sẽ chốt phiên đang dở
                                        thành "Bỏ dở" (không có XP).
                                    </p>
                                )}
                            </li>
                        ))}
                    </ul>
                </Block>
            ))}
            <Block className="p-0 md:p-0">
                <h2 className="px-5 pt-5 text-lg font-semibold md:px-6">
                    Phiên gần đây
                </h2>
                <ul className="mt-2 divide-y divide-border">
                    {history.map((h) => (
                        <li key={h.id}>
                            <Link
                                href={h.url}
                                className="flex flex-wrap items-center gap-x-4 gap-y-1 px-5 py-3 hover:bg-accent/50 md:px-6"
                            >
                                <span className="min-w-0 flex-1 wrap-anywhere">
                                    {h.title}{' '}
                                    <span className="text-sm text-muted-foreground">
                                        · {h.mode}
                                    </span>
                                </span>
                                <span className="text-sm text-muted-foreground">
                                    {h.ended_at ? vnTime(h.ended_at) : ''}
                                </span>
                                <span className="text-sm">
                                    {h.status_label}
                                </span>
                                <span className="w-16 text-right font-semibold tabular-nums">
                                    +{h.xp} XP
                                </span>
                            </Link>
                        </li>
                    ))}
                    {history.length === 0 && (
                        <li className="px-6 py-6 text-center text-muted-foreground">
                            Chưa có phiên nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
