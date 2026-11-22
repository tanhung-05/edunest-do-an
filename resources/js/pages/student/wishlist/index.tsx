import { Form, Head, Link } from '@inertiajs/react';
import { Heart, Tag } from 'lucide-react';
import { CourseLine } from '@/components/commerce';
import type { CourseCardData } from '@/components/commerce';
import { Block, Page } from '@/components/page';
import { Button } from '@/components/ui/button';
import { index as publicCourses } from '@/routes/public/courses';
import cart from '@/routes/student/cart';
import wishlist from '@/routes/student/wishlist';

export default function Wishlist({ items }: { items: CourseCardData[] }) {
    return (
        <Page
            title="Khóa yêu thích"
            description="Bạn sẽ nhận thông báo khi khóa yêu thích bắt đầu giảm giá."
            stats={[
                { label: 'Khóa yêu thích', value: items.length, icon: Heart },
                {
                    label: 'Đang mở bán',
                    value: items.filter((c) => c.selling).length,
                    icon: Tag,
                },
            ]}
        >
            <Head title="Khóa yêu thích" />
            <Block>
                {items.length === 0 ? (
                    <p className="text-muted-foreground">
                        Chưa có khóa yêu thích.{' '}
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
                                {card.selling && (
                                    <Form
                                        {...cart.store.form(card.id)}
                                        options={{ preserveScroll: true }}
                                    >
                                        <Button size="sm" type="submit">
                                            Thêm vào giỏ
                                        </Button>
                                    </Form>
                                )}
                                <Form
                                    {...wishlist.toggle.form(card.id)}
                                    options={{ preserveScroll: true }}
                                >
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        type="submit"
                                    >
                                        Bỏ yêu thích
                                    </Button>
                                </Form>
                            </CourseLine>
                        ))}
                    </ul>
                )}
            </Block>
        </Page>
    );
}
