<?php

use App\Enums\M01\RoleCode;
use App\Models\M02\Category;
use App\Models\M02\TeacherProfile;
use App\Models\User;
use App\Notifications\EduNotification;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\Support\HocTap;

pest()->group('P10');

it('UAT 22/11: link thông báo trỏ trong ứng dụng được lưu và mở bằng đường dẫn tương đối, không phụ thuộc localhost hay 127.0.0.1', function () {
    expect(EduNotification::relative('http://localhost/exams/attempts/5/result?x=1#k'))->toBe('/exams/attempts/5/result?x=1#k')
        ->and(EduNotification::relative('http://127.0.0.1:8000/student/orders'))->toBe('/student/orders')
        ->and(EduNotification::relative('/student/orders'))->toBe('/student/orders')
        ->and(EduNotification::relative('https://evil.example/phish'))->toBe('https://evil.example/phish')
        ->and(EduNotification::relative(null))->toBeNull();

    $u = taiKhoan('rs-tb@edunest.test', RoleCode::HocVien);
    $this->signIn($u);
    $id = (string) Str::uuid();
    DB::table('notifications')->insert([
        'id' => $id, 'type' => 'test', 'notifiable_type' => User::class, 'notifiable_id' => $u->id,
        'data' => json_encode(['title' => 'Kết quả', 'body' => '', 'url' => 'http://localhost/student/orders']),
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $this->post(route('account.notifications.read', $id))->assertRedirect('/student/orders');
});

it('UAT 22/11: lọc giá ngược (từ lớn hơn đến) được đảo lại và báo cho người xem', function () {
    $this->get(route('public.courses.index', ['price_min' => 800000, 'price_max' => 100000]))
        ->assertInertia(fn ($p) => $p->where('priceSwapped', true)
            ->where('filters.price_min', 100000)->where('filters.price_max', 800000));
    $this->get(route('public.courses.index', ['price_min' => 100000, 'price_max' => 800000]))
        ->assertInertia(fn ($p) => $p->where('priceSwapped', false));
});

it('CR-259: Giáo vụ ghi chuyên môn giáo viên, trang giao khóa nhận chuyên môn để gợi ý', function () {
    $gvu = taiKhoan('rs-gvu@edunest.test', RoleCode::GiaoVu);
    $gv = taiKhoan('rs-gv@edunest.test', RoleCode::GiaoVien);
    $hoa = Category::factory()->create(['name' => 'Hóa học RS']);
    $ly = Category::factory()->create(['name' => 'Vật lý RS']);

    $this->signIn($gvu);
    $this->put(route('academic.teachers.subjects.update', $gv), ['subject_category_ids' => [$hoa->id, $ly->id]])
        ->assertSessionHasNoErrors()->assertRedirect();
    expect(TeacherProfile::query()->where('user_id', $gv->id)->value('subject_category_ids'))->not->toBeNull()
        ->and(TeacherProfile::query()->where('user_id', $gv->id)->first()?->subject_category_ids)->toEqualCanonicalizing([$hoa->id, $ly->id]);

    $this->get(route('academic.courses.index'))
        ->assertInertia(fn ($p) => $p->where('options.teachers', fn ($list) => collect($list)
            ->firstWhere('id', $gv->id)['subject_ids'] !== []));

    $this->put(route('academic.teachers.subjects.update', $gv), ['subject_category_ids' => [999999]])
        ->assertSessionHasErrors('subject_category_ids');

    $this->put(route('academic.teachers.subjects.update', $gv), ['subject_category_ids' => []])->assertRedirect();
    expect(TeacherProfile::query()->where('user_id', $gv->id)->first()?->subject_category_ids)->toBeNull();

    $this->signIn($gv);
    $this->put(route('academic.teachers.subjects.update', $gv), ['subject_category_ids' => [$hoa->id]])->assertForbidden();
});

it('UAT 22/11: bảng điều khiển học viên có nút xem lại khi đã học hết bài', function () {
    $h = new HocTap;
    $h->signIn($this);
    foreach ([$h->read, $h->video, $h->quizLesson, $h->optional] as $lesson) {
        $h->mark($lesson);
    }
    $this->get(route('student.dashboard'))
        ->assertInertia(fn ($p) => $p->where('learningCourses.0.learning_entry', null)
            ->where('learningCourses.0.review_url', route('student.lessons.show', ['course' => $h->course->id, 'lesson' => $h->read->id])));
});
