<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Models\M02\Category;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use Illuminate\Database\Eloquent\Model;

/** QT-02 A4/A9: khôi phục; con cần cha còn hiệu lực; trùng tên/slug đang dùng thì chặn (CR-195). */
final class RestoreTaxonomy extends TaxonomyWriter
{
    public function handle(TaxonomyKind $kind, int $id): Model
    {
        return $this->write(function () use ($kind, $id) {
            $row = $this->lockTaxonomy($kind, $id);
            TestBarrier::reach('taxonomy-restore.locked');
            if ($row->getAttribute('deleted_at') === null) {
                return $row;
            }
            $parentId = null;
            if ($kind === TaxonomyKind::Category) {
                $parentId = $row->getAttribute('parent_id');
                if ($parentId !== null) {
                    $parent = Category::query()->withTrashed()->whereKey($parentId)->first();
                    if ($parent === null || $parent->trashed()) {
                        throw new DomainRejection('Hãy khôi phục danh mục cha trước.', 'general');
                    }
                }
            }
            if ($this->nameTaken($kind, (string) $row->getAttribute('name'), $parentId, $id)
                || ($kind->hasSlug() && $this->slugTaken($kind, (string) $row->getAttribute('slug'), $id))) {
                throw new DomainRejection('Đã có nhãn đang dùng cùng tên. Hãy đổi tên nhãn đó trước.', 'general');
            }
            $row->setAttribute('deleted_at', null);
            $row->save();

            return $row;
        });
    }
}
