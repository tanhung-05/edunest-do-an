import { Form, Head, Link } from '@inertiajs/react';
import { Check } from 'lucide-react';
import { vnd } from '@/components/commerce';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import courses from '@/routes/public/courses';

type Props = {
    bundle: {
        id: number;
        slug: string;
        title: string;
        description: string | null;
        price: number;
        retail: number;
        accepts_orders: boolean;
        deduct_owned: boolean;
        courses: {
            id: number;
            title: string;
            slug: string;
            price: number;
            owned: boolean;
            cover: string;
        }[];
    };
    owned_count: number;
    ref: string | null;
    order_url: string | null;
    login_url: string | null;
};

/** QT-26 bước 3–5: trang combo, giá chung, khóa đã sở hữu, nút đặt combo. */
export default function BundleShow({
    bundle,
    owned_count,
    ref,
    order_url,
    login_url,
}: Props) {
    const ownedTotal = bundle.courses
        .filter((c) => c.owned)
        .reduce((s, c) => s + c.price, 0);
    const pay = bundle.deduct_owned ? bundle.price - ownedTotal : bundle.price;

    return (
        <>
            <Head title={bundle.title} />
            <section className="mx-auto grid w-full max-w-5xl gap-6 px-4 py-10 md:grid-cols-[1.5fr_1fr] md:px-8">
                <div className="grid content-start gap-4">
                    <h1 className="font-display text-3xl font-bold tracking-tight wrap-anywhere">
                        {bundle.title}
                    </h1>
                    {bundle.description && (
                        <p className="text-muted-foreground">
                            {bundle.description}
                        </p>
                    )}
                    <ul className="grid gap-3">
                        {bundle.courses.map((c) => (
                            <li
                                key={c.id}
                                className="flex items-center gap-3 rounded-xl border border-border bg-card p-3"
                            >
                                <img
                                    src={c.cover}
                                    alt=""
                                    className="size-14 rounded-lg object-cover"
                                    loading="lazy"
                                />
                                <div className="min-w-0 flex-1">
                                    <Link
                                        href={courses.show(c.slug)}
                                        className="font-medium wrap-anywhere hover:underline"
                                    >
                                        {c.title}
                                    </Link>
                                    <p className="text-sm text-muted-foreground">
                                        Giá lẻ {vnd(c.price)}
                                    </p>
                                </div>
                                {c.owned && (
                                    <span className="flex items-center gap-1 text-sm font-medium text-success">
                                        <Check className="size-4" /> Đã sở hữu
                                    </span>
                                )}
                            </li>
                        ))}
                    </ul>
                </div>
                <aside className="grid content-start gap-4 rounded-2xl border border-border bg-card p-5 shadow-soft">
                    <p className="text-sm text-muted-foreground">Giá combo</p>
                    <p className="flex items-baseline gap-2">
                        <span className="text-3xl font-bold">
                            {vnd(bundle.price)}
                        </span>
                        {bundle.retail > bundle.price && (
                            <span className="text-muted-foreground line-through">
                                {vnd(bundle.retail)}
                            </span>
                        )}
                    </p>
                    {owned_count > 0 && (
                        <p className="text-sm">
                            {bundle.deduct_owned
                                ? `Bạn đã sở hữu ${owned_count} khóa, trừ ${vnd(ownedTotal)}. Dự kiến trả ${vnd(Math.max(pay, 0))}.`
                                : `Bạn đã sở hữu ${owned_count} khóa. Combo này không trừ khóa đã sở hữu, hãy mua lẻ các khóa còn lại.`}
                        </p>
                    )}
                    {!bundle.accepts_orders ? (
                        <p className="rounded-xl bg-muted px-4 py-3 text-sm">
                            Combo đang tạm ngừng nhận đơn mới.
                        </p>
                    ) : order_url ? (
                        <Form action={order_url} method="post">
                            {({ processing, errors }) => (
                                <div className="grid gap-2">
                                    {ref && (
                                        <input
                                            type="hidden"
                                            name="ref"
                                            value={ref}
                                        />
                                    )}
                                    <Button
                                        type="submit"
                                        disabled={processing}
                                        className="h-12 rounded-full bg-amber text-base font-semibold text-slate-950 hover:bg-amber/90"
                                    >
                                        Đặt mua combo
                                    </Button>
                                    <InputError message={errors.bundle} />
                                </div>
                            )}
                        </Form>
                    ) : (
                        <a
                            href={login_url ?? '#'}
                            className="grid h-12 place-items-center rounded-full bg-amber font-semibold text-slate-950"
                        >
                            Đăng nhập để mua
                        </a>
                    )}
                </aside>
            </section>
        </>
    );
}
