<?php

/*
| QT-02 — Danh mục nền và hồ sơ giáo viên: tiêu chí nghiệm thu mốc P0 (07a).
| Liên kết nhãn với khóa học/câu hỏi cần bảng P1 (courses, course_tag, question_tag): các ca đó
| đánh dấu todo và hoàn thành khi xây P1.
*/

use App\Actions\M03\AttachCourseTags;
use App\Actions\M03\ChangeSaleStatus;
use App\Enums\M01\RoleCode;
use App\Models\M02\Category;
use App\Models\M02\Level;
use App\Models\M02\Tag;
use App\Models\M02\TeacherProfile;
use App\Models\M03\Course;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KhoaHoc;

pest()->group('P0');

beforeEach(function () {
    $this->quang = taiKhoan('quang@edunest.test', RoleCode::QuanTri);
});

function danhMuc(string $name, ?Category $parent = null): Category
{
    return Category::query()->create(['name' => $name, 'slug' => Str::slug($name).'-'.Str::random(4), 'parent_id' => $parent?->id]);
}

function the(string $name): Tag
{
    return Tag::query()->create(['name' => $name, 'slug' => Str::slug($name)]);
}

function giaoVienCoHoSo(string $email, string $name, array $profile = []): User
{
    $u = User::factory()->teacher()->create(['email' => $email, 'name' => $name]);
    TeacherProfile::query()->create([
        'user_id' => $u->id,
        'bio' => $profile['bio'] ?? null,
        'achievements' => $profile['achievements'] ?? null,
        'photo_path' => $profile['photo_path'] ?? null,
        'created_by_id' => User::factory()->academic()->create()->id,
    ]);

    return $u;
}

/** Danh sách chọn = nhãn đang dùng (chưa xóa mềm). */
function dangChon(string $class): array
{
    return $class::query()->pluck('name')->all();
}

it('AC-02-01 Tạo danh mục con, cấp tài khoản Giáo viên kèm hồ sơ, Giáo viên sửa, Khách xem trang', function () {
    Storage::fake('public');
    $root = danhMuc('Khoa học tự nhiên');
    $this->signIn($this->quang)->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Hóa học', 'parent_id' => $root->id])
        ->assertSessionHasNoErrors();
    expect(dangChon(Category::class))->toContain('Hóa học');

    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->signIn($lan)->post(route('academic.teachers.store'), [
        'name' => 'Trần Minh Hóa', 'email' => 'hoa.tran@edunest.test', 'password' => 'TamThoi123',
        'bio' => '10 năm dạy Hóa', 'achievements' => 'Giải ba Hóa quốc gia', 'photo' => UploadedFile::fake()->image('a.jpg'),
    ])->assertSessionHasNoErrors();
    $a = User::query()->where('email', 'hoa.tran@edunest.test')->sole();
    $a->forceFill(['must_change_password' => false])->save();

    $this->signIn($a)->post(route('teacher.profile.update'), ['bio' => '12 năm dạy Hóa', 'achievements' => 'Giải ba Hóa quốc gia'])
        ->assertSessionHasNoErrors();

    auth()->forgetGuards();
    $this->get(route('public.teachers.show', $a->id))->assertOk()->assertInertia(fn ($p) => $p
        ->component('public/teacher')
        ->where('teacher.bio', '12 năm dạy Hóa')
        ->where('teacher.achievements', 'Giải ba Hóa quốc gia')
        ->whereNot('teacher.photo', null));
});

it('AC-02-02 Quang tạo cấp độ "Nâng cao" và thẻ "Este"', function () {
    $this->signIn($this->quang)->post(route('admin.taxonomies.store', 'levels'), ['name' => 'Nâng cao'])->assertSessionHasNoErrors();
    $this->post(route('admin.taxonomies.store', 'tags'), ['name' => 'Este'])->assertSessionHasNoErrors();

    expect(dangChon(Level::class))->toBe(['Nâng cao'])->and(dangChon(Tag::class))->toBe(['Este']);
});

it('AC-02-03 Tạo con của danh mục cấp hai bị từ chối; con của danh mục gốc thành công', function () {
    $root = danhMuc('Khoa học tự nhiên');
    $hoa = danhMuc('Hóa học', $root);

    $this->signIn($this->quang)->postJson(route('admin.taxonomies.store', 'categories'), ['name' => 'Hóa hữu cơ', 'parent_id' => $hoa->id])
        ->assertStatus(422)->assertJsonValidationErrors('parent_id');
    expect(Category::query()->where('name', 'Hóa hữu cơ')->exists())->toBeFalse();

    $this->postJson(route('admin.taxonomies.store', 'categories'), ['name' => 'Hóa vô cơ', 'parent_id' => $root->id])->assertRedirect();
    expect(Category::query()->where('name', 'Hóa vô cơ')->value('parent_id'))->toBe($root->id);
});

it('AC-02-04 Quang đổi tên thẻ "Este" thành "Este và lipit"', function () {
    $tag = the('Este');
    $k = khoaK(['tag' => $tag]);
    $this->signIn($this->quang)->patch(route('admin.taxonomies.update', ['tags', $tag->id]), ['name' => 'Este và lipit'])->assertSessionHasNoErrors();
    expect($tag->fresh()->name)->toBe('Este và lipit')->and($k->tags()->pluck('name')->all())->toBe(['Este và lipit']);
});

it('AC-02-05 Quang xóa mềm thẻ "Este" chưa gắn khóa nào', function () {
    $tag = the('Este');
    $this->signIn($this->quang)->delete(route('admin.taxonomies.destroy', ['tags', $tag->id]))->assertSessionHasNoErrors();

    expect(dangChon(Tag::class))->not->toContain('Este')
        ->and(Tag::withTrashed()->find($tag->id)->deleted_at)->not->toBeNull();
});

it('AC-02-06 Quang xóa mềm danh mục, cấp độ, thẻ đang gắn khóa K', function () {
    $root = danhMuc('Khoa học tự nhiên');
    $cat = danhMuc('Hóa học', $root);
    $lv = Level::query()->create(['name' => 'Cơ bản']);
    $tag = the('Este');
    $k = khoaK(['category' => $cat, 'level' => $lv, 'tag' => $tag]);

    $this->signIn($this->quang);
    foreach ([['categories', $cat->id], ['levels', $lv->id], ['tags', $tag->id]] as [$kind, $id]) {
        $this->delete(route('admin.taxonomies.destroy', [$kind, $id]))->assertSessionHasNoErrors();
    }
    expect(Category::withTrashed()->find($cat->id)->deleted_at)->not->toBeNull()
        ->and(Level::withTrashed()->find($lv->id)->deleted_at)->not->toBeNull()
        ->and(Tag::withTrashed()->find($tag->id)->deleted_at)->not->toBeNull();
    $k->refresh();
    expect($k->category_id)->toBe($cat->id)->and($k->level_id)->toBe($lv->id)
        ->and($k->tags()->withTrashed()->pluck('tags.id')->all())->toBe([$tag->id]);
});

it('AC-02-07 Quang khôi phục thẻ "Este" đã xóa mềm', function () {
    $tag = the('Este');
    $tag->delete();
    $this->signIn($this->quang)->patch(route('admin.taxonomies.restore', ['tags', $tag->id]))->assertSessionHasNoErrors();
    expect(dangChon(Tag::class))->toContain('Este');
});

it('AC-02-08 Quang xóa vĩnh viễn danh mục "Hóa học" đang gắn 1 khóa', function () {
    $hoa = danhMuc('Hóa học', danhMuc('Khoa học tự nhiên'));
    $k = khoaK(['category' => $hoa]);
    $this->signIn($this->quang)->delete(route('admin.taxonomies.force-destroy', ['categories', $hoa->id]))->assertSessionHasErrors('general');
    expect(Category::withTrashed()->find($hoa->id))->not->toBeNull()->and($k->fresh()->category_id)->toBe($hoa->id);
});

it('AC-02-09 Quang xóa vĩnh viễn danh mục "Sinh học" gắn 0 khóa', function () {
    $sinh = danhMuc('Sinh học', danhMuc('Khoa học tự nhiên'));
    $this->signIn($this->quang)->delete(route('admin.taxonomies.force-destroy', ['categories', $sinh->id]))->assertSessionHasNoErrors();
    expect(Category::withTrashed()->find($sinh->id))->toBeNull();
});

it('AC-02-10 Xóa vĩnh viễn ba nhãn còn gắn khóa K đều bị từ chối', function () {
    $hoa = danhMuc('Hóa học', danhMuc('Khoa học tự nhiên'));
    $lv = Level::query()->create(['name' => 'Cơ bản']);
    $tag = the('Este');
    $k = khoaK(['category' => $hoa, 'level' => $lv, 'tag' => $tag]);
    $hoa->delete();

    $this->signIn($this->quang);
    foreach ([['categories', $hoa->id], ['levels', $lv->id], ['tags', $tag->id]] as [$kind, $id]) {
        $this->delete(route('admin.taxonomies.force-destroy', [$kind, $id]))->assertSessionHasErrors('general');
    }
    expect(Category::withTrashed()->find($hoa->id))->not->toBeNull()->and(Level::query()->find($lv->id))->not->toBeNull()
        ->and(Tag::query()->find($tag->id))->not->toBeNull()->and($k->tags()->count())->toBe(1);
});

it('AC-02-11 Chọn thẻ đã xóa mềm cho khóa mới/khóa khác bị từ chối; khóa cũ giữ thẻ', function () {
    $tag = the('Este');
    $k = khoaK(['tag' => $tag]);
    $tag->delete();
    $k3 = khoaK(['title' => 'K3']);
    $k2 = khoaK(['title' => 'K2']);

    $this->signIn(nv('lan.pham@edunest.test', RoleCode::GiaoVu));
    $this->putJson(route('academic.courses.tags', $k2), ['tag_ids' => [$tag->id]])->assertStatus(422);
    $this->putJson(route('academic.courses.tags', $k3), ['tag_ids' => [$tag->id]])->assertStatus(422);
    $this->patchJson(route('academic.courses.update', $k), ['title' => 'K đổi tên'])->assertRedirect();
    $this->putJson(route('academic.courses.tags', $k), ['tag_ids' => [$tag->id]])->assertRedirect();

    expect($k2->tags()->withTrashed()->count())->toBe(0)->and($k3->tags()->withTrashed()->count())->toBe(0)
        ->and($k->fresh()->title)->toBe('K đổi tên')->and($k->tags()->withTrashed()->pluck('tags.id')->all())->toBe([$tag->id]);
});

it('AC-02-12 Lan tạo tài khoản Giáo viên B kèm hồ sơ', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $khac = User::factory()->teacher()->create();
    $this->signIn($lan)->post(route('academic.teachers.store'), [
        'name' => 'Giáo viên B', 'email' => 'gvb@edunest.test', 'password' => 'TamThoi123', 'bio' => '5 năm dạy Lý',
    ])->assertSessionHasNoErrors();

    $b = User::query()->where('email', 'gvb@edunest.test')->sole();
    expect(TeacherProfile::query()->where('user_id', $b->id)->count())->toBe(1)
        ->and(TeacherProfile::query()->where('user_id', $khac->id)->exists())->toBeFalse()
        ->and(TeacherProfile::query()->count())->toBe(1);
});

it('AC-02-13 A sửa thành tích thành "Giải nhì Hóa quốc gia"; hồ sơ B không đổi', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'Trần Minh Hóa', ['bio' => 'cũ', 'achievements' => 'Giải ba']);
    $b = giaoVienCoHoSo('gvb@edunest.test', 'B', ['bio' => 'B bio', 'achievements' => 'B tt']);

    $this->signIn($a)->post(route('teacher.profile.update'), ['bio' => 'cũ', 'achievements' => 'Giải nhì Hóa quốc gia'])->assertSessionHasNoErrors();
    expect($a->teacherProfile->fresh()->achievements)->toBe('Giải nhì Hóa quốc gia')
        ->and($b->teacherProfile->fresh()->only(['bio', 'achievements']))->toBe(['bio' => 'B bio', 'achievements' => 'B tt']);
});

it('AC-02-14 Quang mở hồ sơ A: xem được, không có thao tác sửa', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'Trần Minh Hóa', ['bio' => '12 năm dạy Hóa']);
    $this->signIn($this->quang)->get(route('public.teachers.show', $a->id))->assertOk()
        ->assertInertia(fn ($p) => $p->where('teacher.bio', '12 năm dạy Hóa'));
    $this->post(route('teacher.profile.update'), ['bio' => 'x'])->assertForbidden();
});

it('AC-02-15 Khách mở trang giáo viên A', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'Trần Minh Hóa', ['bio' => 'Giới thiệu', 'achievements' => 'Thành tích', 'photo_path' => 'teacher-photos/a.jpg']);
    $this->get(route('public.teachers.show', $a->id))->assertOk()->assertInertia(fn ($p) => $p
        ->where('teacher.bio', 'Giới thiệu')->where('teacher.achievements', 'Thành tích')->whereNot('teacher.photo', null));
});

it('AC-02-19 Chưa bật M14: trang giáo viên không có mục điểm đánh giá', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'Trần Minh Hóa', ['bio' => 'x']);
    $this->get(route('public.teachers.show', $a->id))->assertOk()
        ->assertInertia(fn ($p) => $p->missing('teacher.rating')->missing('rating'));
});

it('AC-02-20 Giáo vụ, Giáo viên, Học viên gọi tạo danh mục, cấp độ, thẻ', function () {
    $nguoi = [
        taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu),
        taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien),
        taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien),
    ];
    foreach ($nguoi as $u) {
        foreach (['categories', 'levels', 'tags'] as $kind) {
            $this->signIn($u)->post(route('admin.taxonomies.store', $kind), ['name' => 'Mới'])->assertForbidden();
        }
    }
    expect(Category::withTrashed()->count() + Level::withTrashed()->count() + Tag::withTrashed()->count())->toBe(0);
});

it('AC-02-21 Lan gọi khôi phục thẻ và xóa vĩnh viễn danh mục', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $tag = the('Este');
    $tag->delete();
    $sinh = danhMuc('Sinh học');

    $this->signIn($lan)->patch(route('admin.taxonomies.restore', ['tags', $tag->id]))->assertForbidden();
    $this->delete(route('admin.taxonomies.force-destroy', ['categories', $sinh->id]))->assertForbidden();
    expect(Tag::withTrashed()->find($tag->id)->deleted_at)->not->toBeNull()
        ->and(Category::query()->find($sinh->id))->not->toBeNull();
});

it('AC-02-22 Học viên An gọi sửa Hồ sơ giáo viên của A', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'A', ['bio' => 'gốc']);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->signIn($an)->post(route('teacher.profile.update'), ['user_id' => $a->id, 'bio' => 'đổi'])->assertForbidden();
    expect($a->teacherProfile->fresh()->bio)->toBe('gốc');
});

it('AC-02-23 Giáo viên A gọi sửa hồ sơ của Giáo viên B', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'A', ['bio' => 'A gốc']);
    $b = giaoVienCoHoSo('gvb@edunest.test', 'B', ['bio' => 'B gốc']);

    $this->signIn($a)->post(route('teacher.profile.update'), ['user_id' => $b->id, 'bio' => 'đổi'])->assertForbidden();
    expect($b->teacherProfile->fresh()->bio)->toBe('B gốc')->and($a->teacherProfile->fresh()->bio)->toBe('A gốc');
});

it('AC-02-24 Quản trị gọi sửa hồ sơ A thành "Giáo viên giỏi"', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'A', ['bio' => '12 năm dạy Hóa']);
    $this->signIn($this->quang)->post(route('teacher.profile.update'), ['user_id' => $a->id, 'bio' => 'Giáo viên giỏi'])->assertForbidden();
    expect($a->teacherProfile->fresh()->bio)->toBe('12 năm dạy Hóa');
});

it('AC-02-25 Tên trùng cùng cấp bị từ chối; tên mới cùng cha thành công', function () {
    $root = danhMuc('Khoa học tự nhiên');
    danhMuc('Hóa học', $root);
    $this->signIn($this->quang);

    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Hóa học', 'parent_id' => $root->id])->assertSessionHasErrors('name');
    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Khoa học tự nhiên'])->assertSessionHasErrors('name');
    expect(Category::query()->count())->toBe(2);
    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Sinh học', 'parent_id' => $root->id])->assertSessionHasNoErrors();
    expect(Category::query()->count())->toBe(3);
});

it('AC-02-26 Xóa mềm danh mục cha khi còn con đang dùng', function () {
    $this->signIn($this->quang);
    // Ca 1: hai con đang dùng. Ca 2: một con đã xóa mềm. Ca 3: cả hai con đã xóa mềm.
    $root = danhMuc('Khoa học tự nhiên');
    $hoa = danhMuc('Hóa học', $root);
    $ly = danhMuc('Vật lý', $root);

    $this->delete(route('admin.taxonomies.destroy', ['categories', $root->id]))->assertSessionHasErrors('general');
    $ly->delete();
    $this->delete(route('admin.taxonomies.destroy', ['categories', $root->id]))->assertSessionHasErrors('general');
    expect($root->fresh()->deleted_at)->toBeNull();
    $hoa->delete();
    $this->delete(route('admin.taxonomies.destroy', ['categories', $root->id]))->assertSessionHasNoErrors();
    expect(Category::withTrashed()->find($root->id)->deleted_at)->not->toBeNull();
});

it('AC-02-27 Khôi phục con khi cha đang xóa mềm bị từ chối; khôi phục cha trước thì được', function () {
    $root = danhMuc('Khoa học tự nhiên');
    $hoa = danhMuc('Hóa học', $root);
    $ly = danhMuc('Vật lý', $root);
    $hoa->delete();
    $ly->delete();
    $root->delete();
    $this->signIn($this->quang);

    $this->patch(route('admin.taxonomies.restore', ['categories', $hoa->id]))->assertSessionHasErrors(['general' => 'Hãy khôi phục danh mục cha trước.']);
    expect(Category::withTrashed()->find($hoa->id)->deleted_at)->not->toBeNull();

    $this->patch(route('admin.taxonomies.restore', ['categories', $root->id]))->assertSessionHasNoErrors();
    $this->patch(route('admin.taxonomies.restore', ['categories', $hoa->id]))->assertSessionHasNoErrors();
    expect(Category::query()->find($hoa->id))->not->toBeNull();
});

it('AC-02-28 Hồ sơ chỉ có giới thiệu: trang ẩn mục ảnh và thành tích rỗng', function () {
    $b = giaoVienCoHoSo('gvb@edunest.test', 'B', ['bio' => '5 năm dạy Lý', 'achievements' => '']);
    $this->get(route('public.teachers.show', $b->id))->assertOk()->assertInertia(fn ($p) => $p
        ->where('teacher.bio', '5 năm dạy Lý')->where('teacher.photo', null)->where('teacher.achievements', null));
});

it('AC-02-30 Giáo vụ, Giáo viên, Học viên đổi tên danh mục và xóa mềm thẻ', function () {
    $hoa = danhMuc('Hóa học');
    $tag = the('Este');
    foreach ([
        taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu),
        taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien),
        taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien),
    ] as $u) {
        $this->signIn($u)->patch(route('admin.taxonomies.update', ['categories', $hoa->id]), ['name' => 'Hóa phổ thông'])->assertForbidden();
        $this->delete(route('admin.taxonomies.destroy', ['tags', $tag->id]))->assertForbidden();
    }
    expect($hoa->fresh()->name)->toBe('Hóa học')->and($tag->fresh()->deleted_at)->toBeNull();
});

it('AC-02-31 Đổi tên "Vật lý" thành "Hóa học" (trùng cùng cha) bị từ chối; "Vật lý đại cương" thành công', function () {
    $root = danhMuc('Khoa học tự nhiên');
    danhMuc('Hóa học', $root);
    $ly = danhMuc('Vật lý', $root);
    $this->signIn($this->quang);

    $this->patch(route('admin.taxonomies.update', ['categories', $ly->id]), ['name' => 'Hóa học'])->assertSessionHasErrors('name');
    expect($ly->fresh()->name)->toBe('Vật lý');
    $this->patch(route('admin.taxonomies.update', ['categories', $ly->id]), ['name' => 'Vật lý đại cương'])->assertSessionHasNoErrors();
    expect($ly->fresh()->name)->toBe('Vật lý đại cương');
});

it('AC-02-32 "Cơ bản" dưới hai cha khác nhau hợp lệ; lần hai cùng cha bị từ chối', function () {
    $tn = danhMuc('Khoa học tự nhiên');
    $xh = danhMuc('Khoa học xã hội');
    $this->signIn($this->quang);

    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Cơ bản', 'parent_id' => $tn->id])->assertSessionHasNoErrors();
    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Cơ bản', 'parent_id' => $xh->id])->assertSessionHasNoErrors();
    $this->post(route('admin.taxonomies.store', 'categories'), ['name' => 'Cơ bản', 'parent_id' => $tn->id])->assertSessionHasErrors('name');
    expect(Category::query()->where('name', 'Cơ bản')->count())->toBe(2);
});

it('AC-02-33 Học viên và Quản trị gọi tạo hồ sơ giáo viên cho C', function () {
    $c = User::factory()->teacher()->create(['email' => 'gvc@edunest.test']);
    $an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);

    $this->signIn($an)->post(route('academic.teachers.profile.store', $c), ['bio' => '3 năm dạy Sinh'])->assertForbidden();
    $this->signIn($this->quang)->post(route('academic.teachers.profile.store', $c), ['bio' => '3 năm dạy Sinh'])->assertForbidden();
    expect(TeacherProfile::query()->where('user_id', $c->id)->exists())->toBeFalse();
    auth()->forgetGuards();
    $this->get(route('public.teachers.show', $c->id))->assertInertia(fn ($p) => $p->where('teacher.bio', null));
});

it('AC-02-34 Giáo viên A gọi tạo hồ sơ giáo viên cho C', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'A', ['bio' => 'A gốc']);
    $c = User::factory()->teacher()->create(['email' => 'gvc@edunest.test']);

    $this->signIn($a)->post(route('academic.teachers.profile.store', $c), ['bio' => 'x'])->assertForbidden();
    expect(TeacherProfile::query()->where('user_id', $c->id)->exists())->toBeFalse()
        ->and($a->teacherProfile->fresh()->bio)->toBe('A gốc');
});

it('AC-02-35 Giáo vụ Lan mở danh sách Giáo viên', function () {
    $lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    User::factory()->teacher()->create(['email' => 'hoa.tran@edunest.test', 'name' => 'Hóa']);
    User::factory()->locked()->teacher()->create(['email' => 'binh.gv@edunest.test', 'name' => 'Bình']);

    $this->signIn($lan)->get(route('academic.teachers.index'))->assertOk()->assertInertia(fn ($p) => $p
        ->component('academic/teachers')
        ->has('teachers', 2)
        ->where('teachers.0.name', 'Bình')->where('teachers.0.status', 'BI_KHOA')->where('teachers.0.authoring_count', 0)
        ->where('teachers.1.name', 'Hóa')->where('teachers.1.status', 'HOAT_DONG')->where('teachers.1.email', 'hoa.tran@edunest.test')
        ->missing('teachers.0.password'));
});

it('AC-02-36 Giáo viên Hóa gọi danh sách Giáo viên', function () {
    $hoa = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $this->signIn($hoa)->get(route('academic.teachers.index'))->assertForbidden();
});

it('AC-02-37 Xóa vĩnh viễn danh mục còn con (kể cả đã xóa mềm) bị chặn và nêu số con', function () {
    $hoa = danhMuc('Hóa học');
    $con = danhMuc('Hóa hữu cơ', $hoa);
    $con->delete();
    $hoa->delete();

    $this->signIn($this->quang)->delete(route('admin.taxonomies.force-destroy', ['categories', $hoa->id]))
        ->assertSessionHasErrors(['general' => 'Còn 1 danh mục con. Không thể xóa vĩnh viễn.']);
    expect(Category::withTrashed()->count())->toBe(2);

    $este = the('este');
    $k = khoaK();
    $bank = KhoaHoc::nganHang($k, $k->currentAuthor->user, 'Chương 1', 3);
    foreach ($bank->questions as $q) {
        $q->tags()->attach($este);
    }
    $this->delete(route('admin.taxonomies.force-destroy', ['tags', $este->id]))
        ->assertSessionHasErrors(['general' => 'Nhãn vẫn được dùng: còn 3 câu hỏi.']);
});

it('AC-02-38 Khôi phục thẻ trùng tên thẻ đang dùng bị chặn; đổi tên thẻ kia rồi khôi phục được', function () {
    $cu = the('este');
    $cu->delete();
    $moi = the('este');
    $this->signIn($this->quang);

    $this->patch(route('admin.taxonomies.restore', ['tags', $cu->id]))->assertSessionHasErrors('general');
    expect(Tag::withTrashed()->find($cu->id)->deleted_at)->not->toBeNull()->and($moi->fresh()->name)->toBe('este');

    $this->patch(route('admin.taxonomies.update', ['tags', $moi->id]), ['name' => 'este-2'])->assertSessionHasNoErrors();
    $this->patch(route('admin.taxonomies.restore', ['tags', $cu->id]))->assertSessionHasNoErrors();
    expect(Tag::query()->pluck('name')->sort()->values()->all())->toBe(['este', 'este-2']);
});

it('AC-02-16 Trang A chỉ liệt kê khóa DANG_BAN (không có NGUNG_BAN, CHUA_BAN)', function () {
    $a = nv('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $k1 = khoaK(['title' => 'K1', 'author' => $a, 'congBo' => true, 'ban' => true]);
    $k2 = khoaK(['title' => 'K2', 'author' => $a, 'congBo' => true, 'ban' => true]);
    app(ChangeSaleStatus::class)->stop(nv('lan.pham@edunest.test', RoleCode::GiaoVu), $k2);
    khoaK(['title' => 'K3', 'author' => $a]);
    $this->get(route('public.teachers.show', $a))->assertInertia(fn ($p) => $p->has('courses', 1)->where('courses.0.id', $k1->id));
})->group('P1');

it('AC-02-17 Khóa CHUA_BAN (bản NHAP hoặc đã công bố) không có trên trang A; URL 404', function () {
    $a = nv('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $k3 = khoaK(['title' => 'K3', 'author' => $a]);
    $k4 = khoaK(['title' => 'K4', 'author' => $a, 'congBo' => true]);
    $this->get(route('public.teachers.show', $a))->assertInertia(fn ($p) => $p->where('courses', []));
    $this->get('/courses/'.$k3->slug)->assertNotFound();
    $this->get('/courses/'.$k4->slug)->assertNotFound();
})->group('P1');

it('AC-02-29 Giáo viên A bị khóa: trang A vẫn hiện hồ sơ và K1', function () {
    $a = giaoVienCoHoSo('hoa.tran@edunest.test', 'Hóa', ['bio' => 'Dạy Hóa']);
    $k1 = khoaK(['title' => 'K1', 'author' => $a, 'congBo' => true, 'ban' => true]);
    $a->forceFill(['status' => 'BI_KHOA'])->save();
    $this->get(route('public.teachers.show', $a))->assertInertia(fn ($p) => $p->where('teacher.bio', 'Dạy Hóa')->where('courses.0.id', $k1->id));
})->group('P1');

it('AC-02-35 (P1) Số khóa đang soạn đọc từ người soạn hiện hành', function () {
    $hoa = nv('hoa.tran@edunest.test', RoleCode::GiaoVien);
    khoaK(['author' => $hoa]);
    khoaK(['title' => 'K2', 'author' => $hoa]);
    $this->signIn(nv('lan.pham@edunest.test', RoleCode::GiaoVu))->get(route('academic.teachers.index'))
        ->assertInertia(fn ($p) => $p->where('teachers.0.name', $hoa->name)->where('teachers.0.authoring_count', 2));
})->group('P1');

/** Lấy tài khoản đã có theo email hoặc tạo mới. */
function nv(string $email, RoleCode ...$roles): User
{
    return User::query()->where('email', $email)->first() ?? taiKhoan($email, ...$roles);
}

/**
 * Khóa K giao cho Giáo viên (mặc định gv.k), tùy chọn danh mục/cấp độ/thẻ, công bố, bật bán.
 *
 * @param  array{title?: string, category?: Category, level?: Level, tag?: Tag, author?: User, congBo?: bool, ban?: bool}  $o
 */
function khoaK(array $o = []): Course
{
    $lan = nv('lan.pham@edunest.test', RoleCode::GiaoVu);
    $minh = nv('minh@edunest.test', RoleCode::GiaoVu);
    $author = $o['author'] ?? nv('gv.k@edunest.test', RoleCode::GiaoVien);
    $over = ['title' => $o['title'] ?? 'K'];
    if (isset($o['category'])) {
        $over['category_id'] = $o['category']->id;
    }
    if (isset($o['level'])) {
        $over['level_id'] = $o['level']->id;
    }
    $k = ($o['congBo'] ?? false) ? KhoaHoc::daCongBo($lan, $author, $minh, $o['ban'] ?? false, $over) : KhoaHoc::giao($lan, $author, $over);
    if (isset($o['tag'])) {
        app(AttachCourseTags::class)->handle($lan, $k, [$o['tag']->id]);
    }

    return $k->fresh();
}
