<?php

namespace App\Actions\M02;

use App\Enums\M02\TaxonomyKind;
use App\Models\M02\Category;
use App\Support\DomainRejection;

/**
 * QT-02 A5 (BR-HT-22, CR-194): xóa vĩnh viễn khi không còn liên kết, kiểm dưới khóa nhãn.
 * P0 kiểm danh mục con (kể cả đã xóa mềm). Liên kết khóa học/câu hỏi thêm ở P1 qua TaxonomyUsage;
 * FK RESTRICT là lưới an toàn.
 */
final class ForceDeleteTaxonomy extends TaxonomyWriter
{
    public function handle(TaxonomyKind $kind, int $id): void
    {
        $this->write(function () use ($kind, $id) {
            $row = $this->lockTaxonomy($kind, $id);
            if ($kind === TaxonomyKind::Category) {
                $children = Category::query()->withTrashed()->where('parent_id', $id)->count();
                if ($children > 0) {
                    throw new DomainRejection("Còn {$children} danh mục con. Không thể xóa vĩnh viễn.", 'general');
                }
            }
            foreach (TaxonomyUsage::links($kind, $id) as $label => $count) {
                if ($count > 0) {
                    throw new DomainRejection("Nhãn vẫn được dùng: còn {$count} {$label}.", 'general');
                }
            }
            $row->forceDelete();
        });
    }
}
