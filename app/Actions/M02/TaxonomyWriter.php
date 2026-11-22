<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Models\M02\Category;
use App\Support\DomainRejection;
use App\Support\Tx;
use Closure;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * Dùng chung cho Create/Rename/SoftDelete/Restore/ForceDeleteTaxonomy (03 §12, 02 L05–L07).
 * Khóa: danh mục cha trước con, rồi đúng dòng nhãn (05 §1). UNIQUE theo alive_key là lưới an toàn.
 */
abstract class TaxonomyWriter
{
    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    protected function write(Closure $work): mixed
    {
        try {
            return Tx::run($work);
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw new DomainRejection('Tên đã được dùng.', 'name');
            }
            throw $e;
        }
    }

    /** @return Builder<Model> */
    protected function query(TaxonomyKind $kind): Builder
    {
        /** @var class-string<Model> $class */
        $class = $kind->modelClass();

        /** @phpstan-ignore-next-line method.notFound (SoftDeletes) */
        return $class::query()->withTrashed();
    }

    /** Khóa dòng cha (nếu là danh mục con) rồi dòng nhãn; trả bản đọc lại. */
    protected function lockTaxonomy(TaxonomyKind $kind, int $id): Model
    {
        if ($kind === TaxonomyKind::Category) {
            $parentId = Category::query()->withTrashed()->whereKey($id)->value('parent_id');
            if ($parentId !== null) {
                Category::query()->withTrashed()->whereKey($parentId)->lockForUpdate()->first();
            }
        }
        $row = $this->query($kind)->whereKey($id)->lockForUpdate()->first();
        if ($row === null) {
            throw new DomainRejection('Nhãn không còn tồn tại.', 'general', 404);
        }

        return $row;
    }

    /** Tên trùng với nhãn đang dùng cùng cấp (danh mục: cùng cha hoặc cùng gốc). */
    protected function nameTaken(TaxonomyKind $kind, string $name, ?int $parentId, ?int $exceptId = null): bool
    {
        $q = $this->query($kind)->whereNull('deleted_at')->where('name', $name);
        if ($kind === TaxonomyKind::Category) {
            $parentId === null ? $q->whereNull('parent_id') : $q->where('parent_id', $parentId);
        }
        if ($exceptId !== null) {
            $q->whereKeyNot($exceptId);
        }

        return $q->exists();
    }

    protected function slugTaken(TaxonomyKind $kind, string $slug, ?int $exceptId = null): bool
    {
        $q = $this->query($kind)->whereNull('deleted_at')->where('slug', $slug);
        if ($exceptId !== null) {
            $q->whereKeyNot($exceptId);
        }

        return $q->exists();
    }

    protected function uniqueSlug(TaxonomyKind $kind, string $name, ?int $exceptId = null): string
    {
        $base = Str::slug($name) ?: 'nhan';
        $slug = $base;
        for ($i = 2; $this->slugTaken($kind, $slug, $exceptId); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }

    protected function normalizeName(string $name): string
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');
        if ($name === '') {
            throw new DomainRejection('Tên không được để trống.', 'name');
        }

        return $name;
    }
}
