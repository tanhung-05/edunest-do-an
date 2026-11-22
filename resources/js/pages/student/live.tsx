import { Form, Head } from '@inertiajs/react';
import { CalendarClock, History, PlayCircle, Video } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';

type Session = {
    id: number;
    course: string | null;
    title: string;
    description: string | null;
    starts_at: string;
    ends_at: string;
    duration_minutes: number;
    state: 'SAP_TOI' | 'VAO_DUOC' | 'DA_KET_THUC' | 'DA_HUY';
    joined_at: string | null;
    join_url: string;
    recording_url: string | null;
};

const stateLabel: Record<Session['state'], string> = {
    SAP_TOI: 'Sắp tới',
    VAO_DUOC: 'Đang mở phòng',
    DA_KET_THUC: 'Đã kết thúc',
    DA_HUY: 'Đã hủy',
};

/** MH-HV buổi học trực tiếp (QT-28): lịch của các khóa còn hạn, vào phòng từ 10 phút trước giờ bắt đầu, bản ghi. */
export default function StudentLive({ sessions }: { sessions: Session[] }) {
    const upcoming = sessions.filter(
        (s) => s.state === 'SAP_TOI' || s.state === 'VAO_DUOC',
    );
    const past = sessions
        .filter((s) => s.state === 'DA_KET_THUC' || s.state === 'DA_HUY')
        .reverse();

    return (
        <Page
            title="Buổi học trực tiếp"
            description="Nút Vào phòng mở từ 10 phút trước giờ bắt đầu tới hết buổi. Bạn nhận chuông nhắc trước 30 phút."
            stats={[
                {
                    label: 'Sắp diễn ra',
                    value: upcoming.length,
                    icon: CalendarClock,
                },
                {
                    label: 'Vào phòng được ngay',
                    value: sessions.filter((s) => s.state === 'VAO_DUOC')
                        .length,
                    icon: Video,
                    attention: sessions.some((s) => s.state === 'VAO_DUOC'),
                },
                { label: 'Đã qua', value: past.length, icon: History },
            ]}
        >
            <Head title="Buổi học trực tiếp" />
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,3fr)_minmax(0,2fr)]">
                <Block className="grid gap-3 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Sắp diễn ra</h2>
                    {upcoming.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có buổi nào sắp diễn ra trong các khóa của bạn.
                        </p>
                    ) : (
                        <ul className="grid gap-3">
                            {upcoming.map((s) => (
                                <SessionRow key={s.id} s={s} />
                            ))}
                        </ul>
                    )}
                </Block>
                <Block className="grid gap-3 p-4 sm:p-6">
                    <h2 className="text-lg font-semibold">Đã qua</h2>
                    {past.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có buổi nào trong 60 ngày qua.
                        </p>
                    ) : (
                        <ul className="grid gap-3">
                            {past.map((s) => (
                                <SessionRow key={s.id} s={s} />
                            ))}
                        </ul>
                    )}
                </Block>
            </div>
        </Page>
    );
}

function SessionRow({ s }: { s: Session }) {
    return (
        <li className="grid gap-3 rounded-xl border p-4 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-center">
            <div className="grid min-w-0 gap-1">
                <p className="text-xs text-muted-foreground">{s.course}</p>
                <p className="font-semibold wrap-anywhere">{s.title}</p>
                <p className="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-muted-foreground">
                    <span className="inline-flex items-center gap-1.5">
                        <CalendarClock aria-hidden className="size-4" />
                        {vnTime(s.starts_at)} · {s.duration_minutes} phút
                    </span>
                    <span
                        className={cn(
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            s.state === 'VAO_DUOC'
                                ? 'bg-emerald-50 text-emerald-700 dark:bg-emerald-400/10 dark:text-emerald-300'
                                : 'bg-muted',
                        )}
                    >
                        {stateLabel[s.state]}
                    </span>
                    {s.joined_at && (
                        <span>Đã vào lúc {vnTime(s.joined_at)}</span>
                    )}
                </p>
                {s.description && (
                    <p className="text-sm wrap-anywhere whitespace-pre-line">
                        {s.description}
                    </p>
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                {s.state === 'VAO_DUOC' && (
                    <Form action={s.join_url} method="post">
                        {({ processing, errors }) => (
                            <div className="grid gap-1">
                                <Button type="submit" disabled={processing}>
                                    <Video aria-hidden />
                                    Vào phòng
                                </Button>
                                {Object.values(errors)[0] && (
                                    <p className="text-sm text-destructive">
                                        {Object.values(errors)[0]}
                                    </p>
                                )}
                            </div>
                        )}
                    </Form>
                )}
                {s.recording_url && (
                    <Button variant="outline" asChild>
                        <a
                            href={s.recording_url}
                            target="_blank"
                            rel="noopener"
                        >
                            <PlayCircle aria-hidden />
                            Xem bản ghi
                        </a>
                    </Button>
                )}
            </div>
        </li>
    );
}
