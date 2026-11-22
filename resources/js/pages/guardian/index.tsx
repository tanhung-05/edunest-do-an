import { Form, Head, Link } from '@inertiajs/react';
import { ChevronRight, UserRound } from 'lucide-react';
import { vnTime } from '@/components/commerce';
import InputError from '@/components/input-error';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import guardian from '@/routes/guardian';

type Props = {
    children: {
        link_id: number;
        id: number;
        name: string | null;
        linked_at: string;
    }[];
};

/** MH-PH-01: danh sách con đã liên kết và ô nhập mã mời (QT-25 bước 3–5). Phụ huynh chỉ đọc. */
export default function GuardianIndex({ children }: Props) {
    return (
        <Page
            title="Con của tôi"
            description="Xem tiến độ, điểm quiz, kết quả thi thử đã công bố, chuỗi ngày học và thời hạn khóa của con. Phụ huynh chỉ xem, không làm bài thay con."
        >
            <Head title="Con của tôi" />
            <div className="grid gap-5 lg:grid-cols-[1.4fr_1fr]">
                <Block className="grid content-start gap-3">
                    <h2 className="text-lg font-semibold">Đã liên kết</h2>
                    {children.length === 0 ? (
                        <p className="text-sm text-muted-foreground">
                            Chưa liên kết với học viên nào. Nhờ con tạo mã mời ở
                            mục "Phụ huynh" trong tài khoản học viên rồi nhập mã
                            ở ô bên cạnh.
                        </p>
                    ) : (
                        <ul className="grid gap-2">
                            {children.map((c) => (
                                <li key={c.link_id}>
                                    <Link
                                        href={guardian.children.show(c.id)}
                                        className="flex items-center gap-3 rounded-xl border border-border p-3 hover:bg-muted focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                    >
                                        <UserRound className="size-5 text-muted-foreground" />
                                        <div className="min-w-0 flex-1">
                                            <p className="font-medium wrap-anywhere">
                                                {c.name}
                                            </p>
                                            <p className="text-xs text-muted-foreground">
                                                Liên kết từ{' '}
                                                {vnTime(c.linked_at, false)}
                                            </p>
                                        </div>
                                        <ChevronRight className="size-4 text-muted-foreground" />
                                    </Link>
                                </li>
                            ))}
                        </ul>
                    )}
                </Block>
                <Block className="grid content-start gap-3">
                    <h2 className="text-lg font-semibold">
                        Thêm con bằng mã mời
                    </h2>
                    <Form
                        {...guardian.links.store.form()}
                        resetOnSuccess
                        className="grid gap-3"
                    >
                        {({ processing, errors }) => (
                            <>
                                <div className="grid gap-2">
                                    <Label htmlFor="code">Mã mời</Label>
                                    <Input
                                        id="code"
                                        name="code"
                                        required
                                        maxLength={12}
                                        autoComplete="off"
                                        className="font-mono uppercase"
                                    />
                                    <InputError message={errors.code} />
                                </div>
                                <Button type="submit" disabled={processing}>
                                    Liên kết
                                </Button>
                            </>
                        )}
                    </Form>
                    <p className="text-xs text-muted-foreground">
                        Mã dùng một lần, hết hạn sau 7 ngày kể từ lúc con tạo.
                    </p>
                </Block>
            </div>
        </Page>
    );
}
