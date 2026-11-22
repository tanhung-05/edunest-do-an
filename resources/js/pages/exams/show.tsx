import { Form, Head, Link } from '@inertiajs/react';
import { ExamBadge, ExamFacts, RankingTable } from '@/components/exams';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import exams from '@/routes/exams';
import type { ExamSummary, RankRow } from '@/types/exams';

type Props = {
    event: ExamSummary & {
        question_count: number;
        registered_count: number;
        server_time: string;
    };
    me: {
        registered: boolean;
        unranked: boolean;
        consent: boolean | null;
        attempt: { status: string; status_label: string; url: string } | null;
        rank: number | null;
        percent: string | null;
    };
    actions: {
        register_url: string | null;
        cancel_url: string | null;
        consent_url: string | null;
        start_url: string | null;
    };
    ranking: RankRow[];
};

export default function ExamShow({ event, me, actions, ranking }: Props) {
    const published = event.status === 'DA_CONG_BO';

    return (
        <Page
            title={event.title}
            eyebrow={
                <Link href={exams.index().url} className="hover:underline">
                    Kỳ thi thử
                </Link>
            }
            actions={<ExamBadge exam={event} />}
        >
            <Head title={event.title} />
            <div className="grid items-start gap-5 lg:grid-cols-[minmax(0,1fr)_22rem]">
                <div className="grid gap-5">
                    <Block>
                        <ExamFacts
                            exam={event}
                            extra={[
                                ['Số câu', event.question_count],
                                ['Đã đăng ký', event.registered_count],
                            ]}
                        />
                    </Block>
                    {published && (
                        <Block className="grid gap-3">
                            <h2 className="text-lg font-semibold">
                                Bảng xếp hạng (top 10)
                            </h2>
                            <RankingTable rows={ranking} />
                            <p className="text-xs text-muted-foreground">
                                Tên chỉ hiện với người đồng ý công khai. Đồng
                                hạng xếp theo điểm, rồi thời gian làm, rồi lúc
                                chốt.
                            </p>
                        </Block>
                    )}
                </div>
                <Block className="grid gap-4 lg:sticky lg:top-4">
                    {me.attempt ? (
                        <div className="grid gap-2">
                            <p className="text-sm">
                                Lượt của bạn:{' '}
                                <strong>{me.attempt.status_label}</strong>
                            </p>
                            {published && (
                                <p className="text-sm">
                                    {me.percent !== null && (
                                        <>
                                            Điểm <strong>{me.percent}%</strong>
                                            .{' '}
                                        </>
                                    )}
                                    {me.unranked ? (
                                        'Không xếp hạng.'
                                    ) : me.rank !== null ? (
                                        <>
                                            Hạng <strong>{me.rank}</strong>.
                                        </>
                                    ) : null}
                                </p>
                            )}
                            <Button asChild>
                                <Link href={me.attempt.url}>
                                    {me.attempt.status === 'DANG_LAM'
                                        ? 'Tiếp tục làm bài'
                                        : 'Xem kết quả'}
                                </Link>
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-2">
                            {me.unranked && (
                                <p className="text-sm">
                                    Bạn dự thi được nhưng{' '}
                                    <strong>Không xếp hạng</strong> (người soạn
                                    đề hoặc nhân sự).
                                </p>
                            )}
                            {actions.start_url && (
                                <Form action={actions.start_url} method="post">
                                    {({ processing, errors }) => (
                                        <div className="grid gap-1">
                                            <Button
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                {processing
                                                    ? 'Đang mở…'
                                                    : 'Bắt đầu làm bài'}
                                            </Button>
                                            {errors.event && (
                                                <p className="text-sm text-destructive">
                                                    {errors.event}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </Form>
                            )}
                            {actions.register_url && (
                                <Form
                                    action={actions.register_url}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing, errors }) => (
                                        <div className="grid gap-1">
                                            <Button
                                                variant={
                                                    actions.start_url
                                                        ? 'outline'
                                                        : 'default'
                                                }
                                                disabled={processing}
                                                className="w-full"
                                            >
                                                Đăng ký dự thi
                                            </Button>
                                            {errors.event && (
                                                <p className="text-sm text-destructive">
                                                    {errors.event}
                                                </p>
                                            )}
                                        </div>
                                    )}
                                </Form>
                            )}
                            {me.registered && (
                                <p className="text-sm">
                                    Bạn đã đăng ký kỳ này.
                                </p>
                            )}
                            {actions.cancel_url && (
                                <Form
                                    action={actions.cancel_url}
                                    method="post"
                                    options={{ preserveScroll: true }}
                                >
                                    {({ processing }) => (
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            disabled={processing}
                                        >
                                            Hủy đăng ký
                                        </Button>
                                    )}
                                </Form>
                            )}
                            {!actions.start_url &&
                                !actions.register_url &&
                                !me.registered && (
                                    <p className="text-sm text-muted-foreground">
                                        Kỳ thi không nhận đăng ký hoặc làm bài
                                        lúc này.
                                    </p>
                                )}
                        </div>
                    )}
                    {actions.consent_url && me.consent !== null && (
                        <Form
                            action={actions.consent_url}
                            method="post"
                            options={{ preserveScroll: true }}
                            transform={() => ({ consent: !me.consent })}
                        >
                            {({ processing }) => (
                                <div className="grid gap-1.5 border-t border-border pt-3 text-sm">
                                    <p>
                                        {me.consent
                                            ? 'Bạn đồng ý công khai tên nếu vào top 10.'
                                            : 'Tên bạn đang ẩn trên bảng xếp hạng.'}
                                    </p>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        disabled={processing}
                                        className="justify-self-start"
                                    >
                                        {me.consent
                                            ? 'Rút đồng ý công khai tên'
                                            : 'Đồng ý công khai tên'}
                                    </Button>
                                </div>
                            )}
                        </Form>
                    )}
                </Block>
            </div>
        </Page>
    );
}
