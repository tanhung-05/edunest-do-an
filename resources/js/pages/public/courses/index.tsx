import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { SearchX } from 'lucide-react';
import { Field, selectClass } from '@/components/page';
import { CourseCard } from '@/components/public/course-card';
import type { PublicCourseCard } from '@/components/public/course-card';
import { CourseSearch } from '@/components/public/course-search';
import { usePageMotion } from '@/components/public/use-page-motion';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import publicRoutes from '@/routes/public';

type Option = { id: number; name: string };
type Category = Option & { parent_id: number | null };
type Sort = 'moi' | 'ban_chay' | 'gia_tang' | 'gia_giam';
type Filters = {
    q: string;
    category: number | null;
    level: number | null;
    teacher: number | null;
    price_min: number | null;
    price_max: number | null;
    sort: Sort;
};
type Props = {
    courses: {
        data: PublicCourseCard[];
        links: { url: string | null; label: string; active: boolean }[];
        current_page: number;
        last_page: number;
        total: number;
    };
    filters: Filters;
    options: {
        categories: Category[];
        levels: Option[];
        teachers: Option[];
        sorts: { value: Sort; label: string }[];
    };
};

function OptionFilter({
    name,
    label,
    options,
    value,
}: {
    name: string;
    label: string;
    options: Option[];
    value: number | null;
}) {
    return (
        <Field label={label} htmlFor={name}>
            <select
                key={value}
                id={name}
                name={name}
                defaultValue={value ?? ''}
                className={selectClass}
            >
                <option value="">Tất cả</option>
                {options.map((option) => (
                    <option key={option.id} value={option.id}>
                        {option.name}
                    </option>
                ))}
            </select>
        </Field>
    );
}

function CatalogPagination({ courses }: Pick<Props, 'courses'>) {
    if (courses.last_page < 2) {
        return null;
    }

    return (
        <nav aria-label="Phân trang khóa học">
            <ul className="flex flex-wrap items-center gap-2">
                {courses.links.map((link, index) => {
                    const previous = index === 0;
                    const next = index === courses.links.length - 1;
                    const label = previous
                        ? 'Trang trước'
                        : next
                          ? 'Trang sau'
                          : link.label;
                    const ariaLabel =
                        previous || next ? label : `Trang ${label}`;

                    return (
                        <li key={index}>
                            {link.url ? (
                                <Link
                                    href={link.url}
                                    aria-label={ariaLabel}
                                    aria-current={
                                        link.active ? 'page' : undefined
                                    }
                                    className="inline-flex min-h-10 min-w-10 items-center justify-center rounded-lg border border-border bg-card px-3 text-sm font-medium hover:bg-accent aria-[current=page]:border-primary aria-[current=page]:bg-primary aria-[current=page]:text-primary-foreground"
                                >
                                    {label}
                                </Link>
                            ) : (
                                <span
                                    aria-disabled={
                                        previous || next ? true : undefined
                                    }
                                    className="inline-flex min-h-10 items-center px-2 text-sm text-muted-foreground"
                                >
                                    {label}
                                </span>
                            )}
                        </li>
                    );
                })}
            </ul>
        </nav>
    );
}

export default function Catalog({ courses, filters, options }: Props) {
    const scope = usePageMotion();
    const [loading, setLoading] = useState(false);
    const [failed, setFailed] = useState(false);

    function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);
        const query: Record<string, string> = {};

        for (const [name, value] of data) {
            const text = typeof value === 'string' ? value.trim() : '';
            if (text) {
                query[name] = text;
            }
        }

        function reportFailure() {
            setFailed(true);
            return false;
        }

        router.visit(publicRoutes.courses.index({ query }), {
            preserveState: true,
            preserveScroll: true,
            onStart: () => {
                setFailed(false);
                setLoading(true);
            },
            onFinish: () => setLoading(false),
            onHttpException: reportFailure,
            onNetworkError: reportFailure,
        });
    }

    return (
        <div ref={scope}>
            <Head title="Danh mục khóa học" />
            <form onSubmit={submit} action={publicRoutes.courses.index.url()}>
                <section className="mx-auto w-full max-w-7xl px-4 md:px-8">
                    <div className="relative isolate flex flex-col gap-5 overflow-hidden rounded-3xl bg-slate-950 px-6 py-10 text-white md:px-10 md:py-14">
                        <img
                            src="/images/stock/nhom-macbook.jpg"
                            alt=""
                            className="absolute inset-0 -z-20 h-full w-full object-cover"
                        />
                        <div className="absolute inset-0 -z-10 bg-slate-950/65" />
                        <h1 className="max-w-[18ch] font-display text-[clamp(2.2rem,4.4vw,3.6rem)] leading-[1.06] font-bold tracking-tight">
                            Tìm khóa học hợp với bạn
                        </h1>
                        <div className="w-full max-w-2xl">
                            <CourseSearch
                                key={filters.q}
                                initialQuery={filters.q}
                                variant="hero"
                            />
                        </div>
                    </div>
                </section>
                <div className="mx-auto grid w-full max-w-7xl items-start gap-8 px-4 pt-8 pb-16 md:px-8 lg:grid-cols-[16rem_minmax(0,1fr)]">
                    <aside className="min-w-0 rounded-2xl border border-border bg-card p-5 shadow-soft lg:sticky lg:top-24">
                        <fieldset className="flex min-w-0 flex-col gap-4">
                            <legend className="mb-4 text-lg font-bold tracking-tight">
                                Bộ lọc
                            </legend>
                            <Field label="Danh mục" htmlFor="category">
                                <select
                                    key={filters.category}
                                    id="category"
                                    name="category"
                                    defaultValue={filters.category ?? ''}
                                    className={selectClass}
                                >
                                    <option value="">Tất cả</option>
                                    {options.categories
                                        .filter(
                                            (category) =>
                                                category.parent_id === null,
                                        )
                                        .map((category) => {
                                            const children =
                                                options.categories.filter(
                                                    (child) =>
                                                        child.parent_id ===
                                                        category.id,
                                                );

                                            return children.length ? (
                                                <optgroup
                                                    key={category.id}
                                                    label={category.name}
                                                >
                                                    <option value={category.id}>
                                                        Toàn bộ {category.name}
                                                    </option>
                                                    {children.map((child) => (
                                                        <option
                                                            key={child.id}
                                                            value={child.id}
                                                        >
                                                            {child.name}
                                                        </option>
                                                    ))}
                                                </optgroup>
                                            ) : (
                                                <option
                                                    key={category.id}
                                                    value={category.id}
                                                >
                                                    {category.name}
                                                </option>
                                            );
                                        })}
                                </select>
                            </Field>
                            <OptionFilter
                                name="level"
                                label="Cấp độ"
                                value={filters.level}
                                options={options.levels}
                            />
                            <OptionFilter
                                name="teacher"
                                label="Giáo viên"
                                value={filters.teacher}
                                options={options.teachers}
                            />
                            <Field label="Giá từ (VND)" htmlFor="price_min">
                                <Input
                                    key={filters.price_min}
                                    id="price_min"
                                    name="price_min"
                                    type="number"
                                    min={0}
                                    defaultValue={filters.price_min ?? ''}
                                />
                            </Field>
                            <Field label="Giá đến (VND)" htmlFor="price_max">
                                <Input
                                    key={filters.price_max}
                                    id="price_max"
                                    name="price_max"
                                    type="number"
                                    min={0}
                                    defaultValue={filters.price_max ?? ''}
                                />
                            </Field>
                        </fieldset>
                        <div className="mt-5 flex flex-wrap items-center gap-3">
                            <Button type="submit" className="h-10 px-5">
                                Áp dụng
                            </Button>
                            <Link
                                href={publicRoutes.courses.index()}
                                className="text-sm font-medium underline underline-offset-4"
                            >
                                Xóa bộ lọc
                            </Link>
                        </div>
                    </aside>
                    <section
                        aria-labelledby="catalog-results"
                        className="flex min-w-0 flex-col gap-5"
                    >
                        <div className="flex flex-wrap items-end justify-between gap-3">
                            <h2
                                data-page-enter
                                id="catalog-results"
                                className="text-2xl font-bold tracking-tight md:text-3xl"
                            >
                                Kết quả
                            </h2>
                            <Field label="Sắp xếp" htmlFor="sort">
                                <select
                                    key={filters.sort}
                                    id="sort"
                                    name="sort"
                                    defaultValue={filters.sort}
                                    className={selectClass}
                                    onChange={(event) =>
                                        event.currentTarget.form?.requestSubmit()
                                    }
                                >
                                    {options.sorts.map((sort) => (
                                        <option
                                            key={sort.value}
                                            value={sort.value}
                                        >
                                            {sort.label}
                                        </option>
                                    ))}
                                </select>
                            </Field>
                        </div>
                        <p
                            aria-live="polite"
                            className="text-sm text-muted-foreground"
                        >
                            {loading
                                ? 'Đang tải khóa học…'
                                : failed
                                  ? 'Không tải được kết quả. Hãy thử lại.'
                                  : courses.data.length
                                    ? `${courses.total.toLocaleString('vi-VN')} khóa học`
                                    : 'Không tìm thấy khóa phù hợp'}
                        </p>
                        {failed && (
                            <Button
                                type="submit"
                                variant="outline"
                                className="self-start"
                            >
                                Thử lại
                            </Button>
                        )}
                        {courses.data.length > 0 && (
                            <ul className="grid gap-6 sm:grid-cols-2 xl:grid-cols-3">
                                {courses.data.map((course) => (
                                    <CourseCard
                                        key={course.id}
                                        course={course}
                                    />
                                ))}
                            </ul>
                        )}
                        {!loading && !failed && courses.data.length === 0 && (
                            <div className="grid items-center gap-6 rounded-2xl border border-dashed border-border bg-card p-8 sm:grid-cols-[3.5rem_1fr]">
                                <span className="grid size-14 place-items-center rounded-2xl bg-accent text-muted-foreground">
                                    <SearchX className="size-6" />
                                </span>
                                <div className="grid gap-2">
                                    <p className="text-lg font-semibold">
                                        Chưa có khóa khớp bộ lọc này
                                    </p>
                                    <p className="text-sm text-muted-foreground">
                                        Bỏ bớt điều kiện lọc hoặc tìm theo tên
                                        giáo viên.
                                    </p>
                                    <Link
                                        href={publicRoutes.courses.index()}
                                        className="text-sm font-bold underline underline-offset-4"
                                    >
                                        Xem toàn bộ khóa học
                                    </Link>
                                </div>
                            </div>
                        )}
                        <CatalogPagination courses={courses} />
                    </section>
                </div>
            </form>
        </div>
    );
}
