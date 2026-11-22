import { Head } from '@inertiajs/react';
import { EyeOff, ShieldAlert, Star } from 'lucide-react';
import { useState } from 'react';
import { Stars, vnTime } from '@/components/commerce';
import { ConfirmDialog } from '@/components/confirm-action';
import type { ConfirmSpec } from '@/components/confirm-action';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import moderation from '@/routes/academic/moderation';

type Rating = {
    id: number;
    student: string;
    course: string;
    stars: number;
    comment: string | null;
    hidden: boolean;
    hidden_reason: string | null;
    reply: string | null;
    created_at: string | null;
};

type Alert = {
    id: number;
    user: string;
    email: string;
    ips: string[];
    detected_at: string;
};

export default function Moderation({
    ratings,
    alerts,
}: {
    ratings: Rating[];
    alerts: Alert[];
}) {
    const [spec, setSpec] = useState<ConfirmSpec | null>(null);

    return (
        <Page
            title="Kiểm duyệt"
            description="Ẩn đánh giá vi phạm (người viết vẫn thấy lý do) và xem cảnh báo đăng nhập nhiều IP."
            stats={[
                { label: 'Đánh giá', value: ratings.length, icon: Star },
                {
                    label: 'Đánh giá đã ẩn',
                    value: ratings.filter((r) => r.hidden).length,
                    icon: EyeOff,
                },
                {
                    label: 'Cảnh báo chia sẻ tài khoản',
                    value: alerts.length,
                    icon: ShieldAlert,
                    attention: alerts.length > 0,
                },
            ]}
        >
            <Head title="Kiểm duyệt" />
            <Block>
                <h2 className="mb-4 text-lg font-bold">Đánh giá khóa học</h2>
                <ul className="divide-y divide-border">
                    {ratings.map((r) => (
                        <li
                            key={r.id}
                            className="flex flex-wrap gap-4 py-4 first:pt-0 last:pb-0"
                        >
                            <div className="min-w-0 flex-1 space-y-1">
                                <div className="flex flex-wrap items-center gap-x-3">
                                    <Stars value={r.stars} />
                                    <span className="font-medium">
                                        {r.student}
                                    </span>
                                    <span className="text-sm text-muted-foreground">
                                        {r.course} ·{' '}
                                        {vnTime(r.created_at, false)}
                                    </span>
                                </div>
                                {r.comment && (
                                    <p className="wrap-anywhere">{r.comment}</p>
                                )}
                                {r.reply && (
                                    <p className="text-sm text-muted-foreground">
                                        Giáo viên: {r.reply}
                                    </p>
                                )}
                                {r.hidden && (
                                    <p className="text-sm text-destructive">
                                        Đang ẩn: {r.hidden_reason}
                                    </p>
                                )}
                            </div>
                            <Button
                                size="sm"
                                variant={r.hidden ? 'outline' : 'destructive'}
                                onClick={() =>
                                    setSpec(
                                        r.hidden
                                            ? {
                                                  form: moderation.ratings.unhide.form(
                                                      r.id,
                                                  ),
                                                  label: 'Hiện lại',
                                                  title: 'Hiện lại đánh giá này?',
                                                  confirmLabel: 'Hiện lại',
                                                  reasonField: {
                                                      name: 'reason',
                                                      label: 'Lý do',
                                                      required: true,
                                                  },
                                              }
                                            : {
                                                  form: moderation.ratings.hide.form(
                                                      r.id,
                                                  ),
                                                  label: 'Ẩn',
                                                  title: 'Ẩn đánh giá này?',
                                                  description:
                                                      'Điểm trung bình của khóa được tính lại. Người viết thấy lý do.',
                                                  confirmLabel: 'Ẩn đánh giá',
                                                  variant: 'destructive',
                                                  reasonField: {
                                                      name: 'reason',
                                                      label: 'Lý do',
                                                      required: true,
                                                  },
                                              },
                                    )
                                }
                            >
                                {r.hidden ? 'Hiện lại' : 'Ẩn'}
                            </Button>
                        </li>
                    ))}
                    {ratings.length === 0 && (
                        <li className="text-muted-foreground">
                            Chưa có đánh giá.
                        </li>
                    )}
                </ul>
            </Block>
            <Block>
                <h2 className="mb-4 text-lg font-bold">Cảnh báo nhiều IP</h2>
                <ul className="divide-y divide-border text-sm">
                    {alerts.map((a) => (
                        <li key={a.id} className="py-3 first:pt-0 last:pb-0">
                            <span className="font-medium">{a.user}</span>{' '}
                            <span className="text-muted-foreground">
                                {a.email}
                            </span>
                            <span className="block text-muted-foreground">
                                {vnTime(a.detected_at)} · {a.ips.join(', ')}
                            </span>
                        </li>
                    ))}
                    {alerts.length === 0 && (
                        <li className="text-muted-foreground">
                            Không có cảnh báo.
                        </li>
                    )}
                </ul>
            </Block>
            <ConfirmDialog
                spec={spec}
                open={spec !== null}
                onOpenChange={(o) => !o && setSpec(null)}
            />
        </Page>
    );
}
