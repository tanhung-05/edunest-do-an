<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Models\M02\Category;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use Illuminate\Database\Eloquent\Model;

/** QT-02 bước 1–3, A1: tạo nhãn; danh mục tối đa hai cấp, tên duy nhất cùng cấp (BR-HT-20, BR-HT-59). */
final class CreateTaxonomy extends TaxonomyWriter
{
    public function handle(TaxonomyKind $kind, string $name, ?int $parentId = null): Model
    {
        $name = $this->normalizeName($name);

        return $this->write(function () use ($kind, $name, $parentId) {
            $attrs = ['name' => $name];
            if ($kind === TaxonomyKind::Category) {
                if ($parentId !== null) {
                    /** @var Category|null $parent */
                    $parent = Category::query()->withTrashed()->whereKey($parentId)->lockForUpdate()->first();
                    TestBarrier::reach('taxonomy-create.parent-locked');
                    if ($parent === null || $parent->deleted_at !== null) {
                        throw new DomainRejection('Danh mục cha không còn hiệu lực.', 'parent_id');
                    }
                    if ($parent->parent_id !== null) {
                        throw new DomainRejection('Chỉ cho phép danh mục hai cấp.', 'parent_id');
                    }
                }
                $attrs['parent_id'] = $parentId;
            }
            if ($this->nameTaken($kind, $name, $parentId)) {
                throw new DomainRejection('Tên đã có ở cùng cấp.', 'name');
            }
            if ($kind->hasSlug()) {
                $attrs['slug'] = $this->uniqueSlug($kind, $name);
            }
            $class = $kind->modelClass();
            $model = new $class;
            $model->forceFill($attrs)->save();

            return $model;
        });
    }
}
