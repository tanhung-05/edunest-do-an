<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Models\M02\Category;
use App\Support\DomainRejection;
use App\Support\TestBarrier;
use Illuminate\Database\Eloquent\Model;

/** QT-02 A3/A9: xóa mềm; danh mục cha còn con đang dùng thì từ chối (BR-HT-59). Liên kết cũ giữ (A6). */
final class SoftDeleteTaxonomy extends TaxonomyWriter
{
    public function handle(TaxonomyKind $kind, int $id): Model
    {
        return $this->write(function () use ($kind, $id) {
            $row = $this->lockTaxonomy($kind, $id);
            TestBarrier::reach('taxonomy-soft-delete.locked');
            if ($row->getAttribute('deleted_at') !== null) {
                return $row;
            }
            if ($kind === TaxonomyKind::Category
                && Category::query()->where('parent_id', $id)->whereNull('deleted_at')->exists()) {
                throw new DomainRejection('Còn danh mục con đang dùng. Hãy xóa các danh mục con trước.', 'general');
            }
            $row->setAttribute('deleted_at', now());
            $row->save();

            return $row;
        });
    }
}
