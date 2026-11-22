import { Head, Link } from '@inertiajs/react';
import { vnd } from '@/components/commerce';
import bundlesRoute from '@/routes/public/bundles';

type Bundle = {
    id: number;
    slug: string;
    title: string;
    price: number;
    retail: number;
    course_count: number;
    cover: string | null;
};

/** QT-26a: danh sách combo đang nhận đơn. */
export default function BundlesIndex({ bundles }: { bundles: Bundle[] }) {
    return (
        <>
            <Head title="Combo khóa học" />
            <section className="mx-auto grid w-full max-w-7xl gap-6 px-4 py-10 md:px-8">
                <header className="grid gap-2">
                    <h1 className="font-display text-3xl font-bold tracking-tight md:text-4xl">
                        Combo khóa học
                    </h1>
                    <p className="max-w-[65ch] text-muted-foreground">
                        Mua nhiều khóa cùng lúc với giá chung. Khóa bạn đã sở
                        hữu được trừ theo giá lẻ khi combo cho phép.
                    </p>
                </header>
                {bundles.length === 0 ? (
                    <p className="text-muted-foreground">
                        Chưa có combo nào đang bán.
                    </p>
                ) : (
                    <ul className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
                        {bundles.map((b) => (
                            <li key={b.id}>
                                <Link
                                    href={bundlesRoute.show(b.slug)}
                                    className="group grid overflow-hidden rounded-2xl border border-border bg-card shadow-soft focus-visible:ring-2 focus-visible:ring-ring focus-visible:outline-none"
                                >
                                    {b.cover && (
                                        <img
                                            src={b.cover}
                                            alt=""
                                            className="aspect-[16/9] w-full object-cover"
                                            loading="lazy"
                                        />
                                    )}
                                    <div className="grid gap-2 p-5">
                                        <h2 className="text-lg font-semibold wrap-anywhere group-hover:underline">
                                            {b.title}
                                        </h2>
                                        <p className="text-sm text-muted-foreground">
                                            {b.course_count} khóa
                                        </p>
                                        <p className="flex items-baseline gap-2">
                                            <span className="text-xl font-bold">
                                                {vnd(b.price)}
                                            </span>
                                            {b.retail > b.price && (
                                                <span className="text-sm text-muted-foreground line-through">
                                                    {vnd(b.retail)}
                                                </span>
                                            )}
                                        </p>
                                    </div>
                                </Link>
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </>
    );
}
