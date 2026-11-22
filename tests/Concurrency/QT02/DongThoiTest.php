<?php

/*
| DT-29 (QT-02 A9, AC-02-27): xóa mềm danh mục cha × tạo danh mục con — cùng khóa dòng cha, hai thứ tự.
*/

use App\Models\M02\Category;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Tests\Support\HaiTienTrinh;

uses(DatabaseTruncation::class);
pest()->group('P0', 'concurrency');

it('DT-29 Xóa mềm cha trước: tạo con chờ khóa cha rồi bị từ chối', function () {
    $root = Category::query()->create(['name' => 'Khoa học tự nhiên', 'slug' => 'khtn']);

    $kq = HaiTienTrinh::chay(
        ['soft-delete-category', ['id' => $root->id]], 'taxonomy-soft-delete.locked',
        ['create-category', ['name' => 'Hóa học', 'parent' => $root->id]],
    );

    expect($kq['a']['ok'])->toBeTrue()
        ->and($kq['b'])->toMatchArray(['ok' => false, 'error' => 'Danh mục cha không còn hiệu lực.'])
        ->and(Category::withTrashed()->where('parent_id', $root->id)->count())->toBe(0);
});

it('DT-29 Tạo con trước: xóa mềm cha chờ khóa rồi bị chặn vì còn con đang dùng', function () {
    $root = Category::query()->create(['name' => 'Khoa học tự nhiên', 'slug' => 'khtn']);

    $kq = HaiTienTrinh::chay(
        ['create-category', ['name' => 'Hóa học', 'parent' => $root->id]], 'taxonomy-create.parent-locked',
        ['soft-delete-category', ['id' => $root->id]],
    );

    expect($kq['a']['ok'])->toBeTrue()
        ->and($kq['b']['ok'])->toBeFalse()
        ->and($root->fresh()->deleted_at)->toBeNull();
});
