import { router } from '@inertiajs/react';
import { useEffect, useId, useRef, useState } from 'react';
import type { KeyboardEvent } from 'react';
import { Search } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import publicRoutes from '@/routes/public';

type Suggestion = {
    id: number;
    title: string;
    slug: string;
    teacher: string | null;
};

type Suggestions = { term: string; items: Suggestion[] };

export function CourseSearch({
    initialQuery = '',
    variant = 'default',
}: {
    initialQuery?: string;
    /** `hero`: ô dạng viên thuốc trên ảnh nền trang chủ, nhãn chỉ dành cho trình đọc màn hình. */
    variant?: 'default' | 'hero';
}) {
    const hero = variant === 'hero';
    const id = useId();
    const list = useRef<HTMLUListElement>(null);
    const [query, setQuery] = useState(initialQuery);
    const [focused, setFocused] = useState(false);
    const [dismissed, setDismissed] = useState(false);
    const [activeIndex, setActiveIndex] = useState(-1);
    const [result, setResult] = useState<Suggestions | null>(null);
    const term = query.trim();
    const canSuggest = focused && !dismissed && term.length >= 2;
    const items = canSuggest && result?.term === term ? result.items : null;
    const active = items?.[activeIndex];

    useEffect(() => {
        if (!canSuggest || result?.term === term) {
            return;
        }

        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            try {
                const response = await fetch(
                    publicRoutes.courses.suggestions.url({
                        query: { q: term },
                    }),
                    {
                        headers: { Accept: 'application/json' },
                        signal: controller.signal,
                    },
                );

                if (response.ok) {
                    const data: Suggestions = await response.json();

                    if (!controller.signal.aborted && data.term === term) {
                        setResult(data);
                    }
                }
            } catch {
                // Giữ ô tìm kiếm hoạt động khi gợi ý không tải được.
            }
        }, 200);

        return () => {
            window.clearTimeout(timer);
            controller.abort();
        };
    }, [canSuggest, result?.term, term]);

    useEffect(() => {
        const container = list.current;
        const option = container?.children[activeIndex];

        if (container && option instanceof HTMLElement) {
            const top = option.offsetTop;
            const bottom = top + option.offsetHeight;

            if (top < container.scrollTop) {
                container.scrollTop = top;
            } else if (bottom > container.scrollTop + container.clientHeight) {
                container.scrollTop = bottom - container.clientHeight;
            }
        }
    }, [activeIndex]);

    function close() {
        setDismissed(true);
        setActiveIndex(-1);
    }

    function openCourse(item: Suggestion) {
        close();
        router.visit(publicRoutes.courses.show(item.slug));
    }

    function handleKey(event: KeyboardEvent<HTMLInputElement>) {
        if (event.nativeEvent.isComposing) {
            return;
        }

        if (event.key === 'Escape' && canSuggest) {
            event.preventDefault();
            close();
        } else if (event.key === 'Enter') {
            if (active) {
                event.preventDefault();
                openCourse(active);
            } else {
                close();
            }
        } else if (event.key === 'ArrowDown' || event.key === 'ArrowUp') {
            if (term.length < 2) {
                return;
            }

            setDismissed(false);

            if (items?.length) {
                event.preventDefault();
                const step = event.key === 'ArrowDown' ? 1 : -1;

                setActiveIndex((index) =>
                    index < 0 && step === -1
                        ? items.length - 1
                        : (index + step + items.length) % items.length,
                );
            }
        }
    }

    return (
        <div className="min-w-0">
            <label
                htmlFor={id}
                className={hero ? 'sr-only' : 'mb-2 block text-sm font-medium'}
            >
                Tìm theo tên khóa học hoặc giáo viên
            </label>
            <div
                className={
                    hero
                        ? 'shadow-lift flex items-center gap-2 rounded-full bg-white p-1.5 pl-5 ring-1 ring-black/5'
                        : 'flex items-start gap-2'
                }
            >
                {hero && (
                    <Search
                        aria-hidden
                        className="size-5 shrink-0 text-slate-400"
                    />
                )}
                <div className="relative min-w-0 flex-1">
                    <Input
                        id={id}
                        name="q"
                        role="combobox"
                        aria-autocomplete="list"
                        aria-expanded={Boolean(items?.length)}
                        aria-controls={items?.length ? `${id}-list` : undefined}
                        aria-activedescendant={
                            active ? `${id}-${active.id}` : undefined
                        }
                        autoComplete="off"
                        value={query}
                        onChange={(event) => {
                            setQuery(event.target.value);
                            setDismissed(false);
                            setActiveIndex(-1);
                        }}
                        onFocus={() => {
                            setFocused(true);
                            setDismissed(false);
                        }}
                        onBlur={() => {
                            setFocused(false);
                            setActiveIndex(-1);
                        }}
                        onKeyDown={handleKey}
                        placeholder={
                            hero ? 'Tìm khóa học hoặc giáo viên' : undefined
                        }
                        className={
                            hero
                                ? 'h-11 border-0 bg-transparent px-1 text-base text-slate-900 shadow-none placeholder:text-slate-400 focus-visible:ring-0'
                                : 'h-11 bg-background text-foreground'
                        }
                    />
                    {Boolean(items?.length) && (
                        <ul
                            ref={list}
                            id={`${id}-list`}
                            role="listbox"
                            aria-label="Gợi ý khóa học"
                            data-lenis-prevent
                            className="shadow-lift absolute inset-x-0 top-full z-20 mt-2 max-h-64 overflow-y-auto rounded-xl border bg-popover text-left text-popover-foreground"
                        >
                            {items?.map((item, index) => (
                                <li
                                    key={item.id}
                                    id={`${id}-${item.id}`}
                                    role="option"
                                    aria-selected={index === activeIndex}
                                    onMouseDown={(event) =>
                                        event.preventDefault()
                                    }
                                    onClick={() => openCourse(item)}
                                    className="cursor-pointer px-3 py-2 wrap-anywhere hover:bg-accent hover:text-accent-foreground aria-selected:bg-accent aria-selected:text-accent-foreground"
                                >
                                    <span className="block font-medium">
                                        {item.title}
                                    </span>
                                    {item.teacher && (
                                        <span className="text-sm">
                                            {item.teacher}
                                        </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    )}
                </div>
                <Button
                    type="submit"
                    className={
                        hero
                            ? 'h-11 rounded-full bg-amber px-6 text-slate-950 hover:bg-amber/90'
                            : 'h-11'
                    }
                >
                    Tìm kiếm
                </Button>
            </div>
            <div aria-live="polite" aria-atomic="true">
                {items &&
                    (items.length ? (
                        <span className="sr-only">
                            {items.length} gợi ý khóa học
                        </span>
                    ) : (
                        <p className="mt-2 text-sm text-current">
                            Không tìm thấy khóa phù hợp
                        </p>
                    ))}
            </div>
        </div>
    );
}
