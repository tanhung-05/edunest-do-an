<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Support\DomainRejection;
use Illuminate\Database\Eloquent\Model;

/** QT-02 A2: đổi tên, kiểm lại trùng cùng cấp dưới khóa; liên kết khóa học giữ nguyên. */
final class RenameTaxonomy extends TaxonomyWriter
{
    public function handle(TaxonomyKind $kind, int $id, string $name): Model
    {
        $name = $this->normalizeName($name);

        return $this->write(function () use ($kind, $id, $name) {
            $row = $this->lockTaxonomy($kind, $id);
            $parentId = $kind === TaxonomyKind::Category ? $row->getAttribute('parent_id') : null;
            if ($row->getAttribute('deleted_at') === null && $this->nameTaken($kind, $name, $parentId, $id)) {
                throw new DomainRejection('Tên đã có ở cùng cấp.', 'name');
            }
            $row->setAttribute('name', $name);
            if ($kind->hasSlug()) {
                $row->setAttribute('slug', $this->uniqueSlug($kind, $name, $id));
            }
            $row->save();

            return $row;
        });
    }
}
