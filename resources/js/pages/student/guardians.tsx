import { Form, Head } from '@inertiajs/react';
import { vnTime } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { cn } from '@/lib/utils';
import student from '@/routes/student';

type Props = {
    invites: {
        id: number;
        code: string;
        issued_at: string;
        expires_at: string;
        state: 'CON_HAN' | 'DA_DUNG' | 'HET_HAN';
    }[];
    guardians: {
        link_id: number;
        name: string | null;
        email: string | null;
        linked_at: string;
    }[];
};

const stateLabel = {
    CON_HAN: 'Còn hạn',
    DA_DUNG: 'Đã dùng',
    HET_HAN: 'Hết hạn',
} as const;

/** MH-HV-21: học viên tạo mã mời phụ huynh, xem và gỡ phụ huynh đã liên kết (QT-25 bước 1–2, A1). */
export default function StudentGuardians({ invites, guardians }: Props) {
    return (
        <Page
            title="Phụ huynh"
            description="Tạo mã mời để phụ huynh theo dõi tiến độ, điểm quiz, kết quả thi thử đã công bố, chuỗi ngày học và thời hạn khóa của bạn. Phụ huynh không xem được đáp án, chứng nhận, đơn hàng hay ghi chú."
        >
            <Head title="Phụ huynh" />
            <div className="grid gap-5 lg:grid-cols-2">
                <Block className="grid content-start gap-3">
                    <div className="flex flex-wrap items-center justify-between gap-2">
                        <h2 className="text-lg font-semibold">Mã mời</h2>
                        <Form {...student.guardians.invites.store.form()}>
                            {({ processing }) => (
                                <Button type="submit" disabled={processing}>
                                    Tạo mã mới
                                </Button>
                            )}
                        </Form>
                    </div>
                    <p className="text-xs text-muted-foreground">
                        Mỗi mã dùng một lần và hết hạn sau 7 ngày. Phụ huynh
                        đăng ký tài khoản Phụ huynh rồi nhập mã.
                    </p>
                    {invites.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có mã nào.
                        </p>
                    ) : (
                        <ul className="grid gap-2">
                            {invites.map((i) => (
                                <li
                                    key={i.id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3"
                                >
                                    <span className="font-mono text-lg font-semibold tracking-widest">
                                        {i.code}
                                    </span>
                                    <span className="text-xs text-muted-foreground">
                                        Hạn {vnTime(i.expires_at)}
                                    </span>
                                    <span
                                        className={cn(
                                            'text-sm font-medium',
                                            i.state === 'CON_HAN'
                                                ? 'text-success'
                                                : 'text-muted-foreground',
                                        )}
                                    >
                                        {stateLabel[i.state]}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </Block>
                <Block className="grid content-start gap-3">
                    <h2 className="text-lg font-semibold">Đã liên kết</h2>
                    {guardians.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa có phụ huynh nào liên kết.
                        </p>
                    ) : (
                        <ul className="grid gap-2">
                            {guardians.map((g) => (
                                <li
                                    key={g.link_id}
                                    className="flex flex-wrap items-center justify-between gap-2 rounded-xl border border-border p-3"
                                >
                                    <div className="min-w-0">
                                        <p className="font-medium wrap-anywhere">
                                            {g.name}
                                        </p>
                                        <p className="text-xs wrap-anywhere text-muted-foreground">
                                            {g.email} · từ{' '}
                                            {vnTime(g.linked_at, false)}
                                        </p>
                                    </div>
                                    <Form
                                        {...student.guardians.links.destroy.form(
                                            g.link_id,
                                        )}
                                        onBefore={() =>
                                            window.confirm(
                                                'Gỡ liên kết? Phụ huynh này sẽ không xem được dữ liệu của bạn nữa.',
                                            )
                                        }
                                    >
                                        <Button
                                            type="submit"
                                            variant="outline"
                                            size="sm"
                                        >
                                            Gỡ
                                        </Button>
                                    </Form>
                                </li>
                            ))}
                        </ul>
                    )}
                </Block>
            </div>
        </Page>
    );
}
