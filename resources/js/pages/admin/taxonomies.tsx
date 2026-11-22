import { Form, Head } from '@inertiajs/react';
import { useState } from 'react';
import { ConfirmAction } from '@/components/confirm-action';
import { Block, Page, selectClass } from '@/components/page';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { cn } from '@/lib/utils';
import taxonomies from '@/routes/admin/taxonomies';

type Row = { id: number; name: string; slug: string | null; deleted: boolean };
type CategoryRow = Row & { children: Row[] };
type Kind = 'categories' | 'levels' | 'tags';

type Props = {
    categories: CategoryRow[];
    levels: Row[];
    tags: Row[];
};

const tabs: { kind: Kind; label: string; one: string }[] = [
    { kind: 'categories', label: 'Danh mục', one: 'danh mục' },
    { kind: 'levels', label: 'Cấp độ', one: 'cấp độ' },
    { kind: 'tags', label: 'Thẻ', one: 'thẻ' },
];

export default function AdminTaxonomies({ categories, levels, tags }: Props) {
    const [kind, setKind] = useState<Kind>('categories');
    const tab = tabs.find((t) => t.kind === kind)!;
    const flat: Row[] = kind === 'levels' ? levels : tags;

    return (
        <Page
            title="Danh mục nền"
            description="Danh mục hai cấp, cấp độ và thẻ dùng khi soạn khóa học."
        >
            <Head title="Danh mục nền" />

            <div role="tablist" className="flex gap-1 border-b border-border">
                {tabs.map((t) => (
                    <button
                        key={t.kind}
                        role="tab"
                        aria-selected={t.kind === kind}
                        onClick={() => setKind(t.kind)}
                        className={cn(
                            '-mb-px border-b-4 px-4 py-2 text-sm font-semibold',
                            t.kind === kind
                                ? 'border-accent'
                                : 'border-transparent text-muted-foreground',
                        )}
                    >
                        {t.label}
                    </button>
                ))}
            </div>

            <Block>
                <Form
                    {...taxonomies.store.form(kind)}
                    resetOnSuccess
                    options={{ preserveScroll: true }}
                    className="flex flex-wrap items-start gap-2"
                >
                    {({ processing, errors }) => (
                        <>
                            <Input
                                name="name"
                                aria-label={`Tên ${tab.one}`}
                                placeholder={`Tên ${tab.one} mới`}
                                required
                                maxLength={kind === 'tags' ? 60 : 100}
                                className="max-w-xs"
                            />
                            {kind === 'categories' && (
                                <select
                                    name="parent_id"
                                    aria-label="Danh mục cha"
                                    className={selectClass}
                                >
                                    <option value="">Danh mục gốc</option>
                                    {categories
                                        .filter((c) => !c.deleted)
                                        .map((c) => (
                                            <option key={c.id} value={c.id}>
                                                Thuộc: {c.name}
                                            </option>
                                        ))}
                                </select>
                            )}
                            <Button type="submit" disabled={processing}>
                                Thêm
                            </Button>
                            {(errors.name || errors.parent_id) && (
                                <p
                                    role="alert"
                                    className="w-full text-sm text-destructive"
                                >
                                    {errors.name ?? errors.parent_id}
                                </p>
                            )}
                        </>
                    )}
                </Form>
            </Block>

            <ul className="divide-y divide-border rounded-lg border border-border">
                {kind === 'categories'
                    ? categories.map((c) => (
                          <li key={c.id}>
                              <TaxonomyRow kind={kind} row={c} />
                              {c.children.length > 0 && (
                                  <ul className="divide-y divide-border border-t border-border bg-secondary/40">
                                      {c.children.map((k) => (
                                          <li key={k.id} className="pl-8">
                                              <TaxonomyRow
                                                  kind={kind}
                                                  row={k}
                                              />
                                          </li>
                                      ))}
                                  </ul>
                              )}
                          </li>
                      ))
                    : flat.map((r) => (
                          <li key={r.id}>
                              <TaxonomyRow kind={kind} row={r} />
                          </li>
                      ))}
                {(kind === 'categories' ? categories.length : flat.length) ===
                    0 && (
                    <li className="px-4 py-6 text-muted-foreground">
                        Chưa có {tab.one} nào.
                    </li>
                )}
            </ul>
        </Page>
    );
}

function TaxonomyRow({ kind, row }: { kind: Kind; row: Row }) {
    const [editing, setEditing] = useState(false);
    const args = { kind, taxonomy: row.id };

    if (editing) {
        return (
            <Form
                {...taxonomies.update.form(args)}
                options={{ preserveScroll: true }}
                onSuccess={() => setEditing(false)}
                className="flex flex-wrap items-start gap-2 px-4 py-3"
            >
                {({ processing, errors }) => (
                    <>
                        <Input
                            name="name"
                            defaultValue={row.name}
                            aria-label="Tên mới"
                            required
                            autoFocus
                            className="max-w-xs"
                        />
                        <Button type="submit" size="sm" disabled={processing}>
                            Lưu
                        </Button>
                        <Button
                            type="button"
                            size="sm"
                            variant="ghost"
                            onClick={() => setEditing(false)}
                        >
                            Hủy
                        </Button>
                        {errors.name && (
                            <p
                                role="alert"
                                className="w-full text-sm text-destructive"
                            >
                                {errors.name}
                            </p>
                        )}
                    </>
                )}
            </Form>
        );
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-2 px-4 py-3">
            <div
                className={cn(
                    row.deleted && 'text-muted-foreground line-through',
                )}
            >
                <span className="font-medium">{row.name}</span>
                {row.slug && (
                    <span className="ml-2 font-mono text-xs text-muted-foreground">
                        {row.slug}
                    </span>
                )}
                {row.deleted && (
                    <span className="ml-2 text-xs no-underline">(đã xóa)</span>
                )}
            </div>
            <div className="flex flex-wrap gap-2">
                {row.deleted ? (
                    <>
                        <Form
                            {...taxonomies.restore.form(args)}
                            options={{ preserveScroll: true }}
                        >
                            {({ processing, errors }) => (
                                <>
                                    <Button
                                        size="sm"
                                        variant="secondary"
                                        disabled={processing}
                                    >
                                        Khôi phục
                                    </Button>
                                    {errors.name && (
                                        <p
                                            role="alert"
                                            className="text-sm text-destructive"
                                        >
                                            {errors.name}
                                        </p>
                                    )}
                                </>
                            )}
                        </Form>
                        <ConfirmAction
                            form={taxonomies.forceDestroy.form(args)}
                            label="Xóa vĩnh viễn"
                            size="sm"
                            variant="destructive"
                            title={`Xóa vĩnh viễn “${row.name}”?`}
                            description="Không khôi phục được."
                            confirmLabel="Xóa vĩnh viễn"
                        />
                    </>
                ) : (
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setEditing(true)}
                        >
                            Đổi tên
                        </Button>
                        <ConfirmAction
                            form={taxonomies.destroy.form(args)}
                            label="Xóa"
                            size="sm"
                            variant="outline"
                            title={`Xóa “${row.name}”?`}
                            description="Có thể khôi phục sau."
                            confirmLabel="Xóa"
                        />
                    </>
                )}
            </div>
        </div>
    );
}
