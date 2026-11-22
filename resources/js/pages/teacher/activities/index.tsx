import { Head, Link, router } from '@inertiajs/react';
import { Archive, Dumbbell, PlayCircle, Plus, Users } from 'lucide-react';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';

type Props = {
    activities: {
        id: number;
        title: string;
        course: string;
        question_count: number;
        draw_mode: string;
        seconds_per_question: number;
        archived: boolean;
        banks: string[];
        sessions: number;
        edit_url: string;
        archive_url: string;
    }[];
    create_url: string;
};

/** MH-GV-15: hoạt động luyện tập của khóa được giao (QT-22 A1a). */
export default function ActivityIndex({ activities, create_url }: Props) {
    return (
        <Page
            title="Hoạt động luyện tập"
            description="Học viên tự chọn Learning hoặc Try Hard khi bắt đầu. Chỉ dùng ngân hàng câu hỏi thường của khóa."
            actions={
                <Button asChild>
                    <Link href={create_url}>
                        <Plus /> Tạo hoạt động
                    </Link>
                </Button>
            }
            stats={[
                {
                    label: 'Hoạt động',
                    value: activities.length,
                    icon: Dumbbell,
                },
                {
                    label: 'Đang mở',
                    value: activities.filter((a) => !a.archived).length,
                    icon: PlayCircle,
                },
                {
                    label: 'Lượt luyện tập',
                    value: activities.reduce((n, a) => n + a.sessions, 0),
                    icon: Users,
                },
                {
                    label: 'Đã ngừng',
                    value: activities.filter((a) => a.archived).length,
                    icon: Archive,
                },
            ]}
        >
            <Head title="Hoạt động luyện tập" />
            <Block className="p-0 md:p-0">
                <ul className="divide-y divide-border">
                    {activities.map((a) => (
                        <li
                            key={a.id}
                            className="flex flex-wrap items-center gap-x-4 gap-y-2 px-5 py-4 md:px-6"
                        >
                            <div className="min-w-0 flex-1">
                                <p className="font-semibold wrap-anywhere">
                                    {a.title}{' '}
                                    {a.archived && (
                                        <span className="text-sm font-normal text-muted-foreground">
                                            · đã ngừng
                                        </span>
                                    )}
                                </p>
                                <p className="text-sm text-muted-foreground">
                                    {a.course} · {a.question_count} câu ·{' '}
                                    {a.draw_mode} · {a.seconds_per_question}{' '}
                                    giây/câu · {a.banks.join(', ')} ·{' '}
                                    {a.sessions} phiên
                                </p>
                            </div>
                            <Button asChild variant="outline" size="sm">
                                <Link href={a.edit_url}>Sửa</Link>
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                onClick={() =>
                                    router.post(
                                        a.archive_url,
                                        { archived: !a.archived },
                                        { preserveScroll: true },
                                    )
                                }
                            >
                                {a.archived ? 'Mở lại' : 'Ngừng'}
                            </Button>
                        </li>
                    ))}
                    {activities.length === 0 && (
                        <li className="px-6 py-8 text-center text-muted-foreground">
                            Chưa có hoạt động nào.
                        </li>
                    )}
                </ul>
            </Block>
        </Page>
    );
}
