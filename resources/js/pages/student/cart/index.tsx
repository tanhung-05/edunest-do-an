import { Form, Head, Link } from '@inertiajs/react';
import { Ban, ShoppingCart, Wallet } from 'lucide-react';
import { CourseLine, vnd } from '@/components/commerce';
import type { CourseCardData } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index as publicCourses } from '@/routes/public/courses';
import cart from '@/routes/student/cart';
import wishlist from '@/routes/student/wishlist';

type Blocked = { code: string; status: string; url: string };

export default function Cart({
    items,
    subtotal,
    removedOwned,
    blocked,
}: {
    items: CourseCardData[];
    subtotal: number;
    removedOwned: number;
    blocked: Blocked[];
}) {
    const sellable = items.filter((i) => i.selling);

    return (
        <Page
            title="Giỏ hàng"
            description="Giá hiển thị là giá hiện tại; giá chốt khi tạo đơn. Mã giảm giá nhập ở trang đơn hàng."
            stats={[
                {
                    label: 'Khóa trong giỏ',
                    value: items.length,
                    icon: ShoppingCart,
                },
                { label: 'Tạm tính', value: vnd(subtotal), icon: Wallet },
                {
                    label: 'Cần xử lý',
                    value: blocked.length,
                    icon: Ban,
                    attention: blocked.length > 0,
                },
            ]}
        >
            <Head title="Giỏ hàng" />
            {removedOwned > 0 && (
                <Block tone="accent">
                    Đã bỏ {removedOwned} khóa bạn đã sở hữu khỏi giỏ.
                </Block>
            )}
            {blocked.length > 0 && (
                <Block tone="accent" className="space-y-2">
                    <p className="font-medium">
                        Bạn đang có đơn chưa xong cho khóa trong giỏ. Hoàn tất
                        hoặc hủy đơn đó trước.
                    </p>
                    <ul className="list-disc pl-5 text-sm">
                        {blocked.map((b) => (
                            <li key={b.code}>
                                <a href={b.url} className="underline">
                                    {b.code}
                                </a>{' '}
                                ({b.status})
                            </li>
                        ))}
                    </ul>
                </Block>
            )}
            <div className="grid gap-6 lg:grid-cols-[1fr_320px]">
                <Block>
                    {items.length === 0 ? (
                        <p className="text-muted-foreground">
                            Giỏ hàng trống.{' '}
                            <Link
                                href={publicCourses()}
                                className="text-foreground underline"
                            >
                                Xem khóa học
                            </Link>
                        </p>
                    ) : (
                        <ul className="divide-y divide-border">
                            {items.map((card) => (
                                <CourseLine key={card.id} card={card}>
                                    <Form
                                        {...wishlist.toggle.form(card.id)}
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button
                                            variant="ghost"
                                            size="sm"
                                            type="submit"
                                        >
                                            Lưu yêu thích
                                        </Button>
                                    </Form>
                                    <Form
                                        {...cart.destroy.form(card.id)}
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button
                                            variant="outline"
                                            size="sm"
                                            type="submit"
                                        >
                                            Bỏ khỏi giỏ
                                        </Button>
                                    </Form>
                                </CourseLine>
                            ))}
                        </ul>
                    )}
                </Block>
                <Block className="h-fit space-y-4">
                    <div className="flex items-baseline justify-between">
                        <span className="text-muted-foreground">
                            Tạm tính ({sellable.length} khóa)
                        </span>
                        <span className="text-2xl font-bold tabular-nums">
                            {vnd(subtotal)}
                        </span>
                    </div>
                    <Form {...cart.checkout.form()}>
                        {({ processing }) => (
                            <Button
                                className="w-full"
                                disabled={processing || sellable.length === 0}
                            >
                                Thanh toán
                            </Button>
                        )}
                    </Form>
                    <p className="text-xs text-muted-foreground">
                        Khóa ngừng bán hoặc đã sở hữu sẽ bị loại khi tạo đơn.
                    </p>
                </Block>
            </div>
        </Page>
    );
}
