<?php

namespace App\Http\Controllers\PublicSite;

use App\Enums\M01\RoleCode;
use App\Enums\M03\SaleStatus;
use App\Http\Controllers\Controller;
use App\Models\M03\Course;
use App\Models\User;
use App\Support\M03\CourseCoverUrl;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * MH-KH-05 — trang giáo viên công khai (QT-02 b8–9, A10). Ẩn mục rỗng; tài khoản BI_KHOA vẫn hiện (CR-116).
 * Không có mục điểm đánh giá trước P5 (AC-02-19). Liệt kê khóa đang bán (AC-02-16).
 */
class TeacherPageController extends Controller
{
    public function show(int $teacher): Response
    {
        $user = User::query()->with('teacherProfile')->findOrFail($teacher);
        abort_unless($user->hasRole(RoleCode::GiaoVien), 404);
        $p = $user->teacherProfile;

        return Inertia::render('public/teacher', [
            'teacher' => [
                'id' => $user->id,
                'name' => $user->name,
                'photo' => $p?->photo_path ? Storage::disk('public')->url($p->photo_path) : null,
                'bio' => filled($p?->bio) ? $p->bio : null,
                'achievements' => filled($p?->achievements) ? $p->achievements : null,
            ],
            // AC-02-16/17: chỉ khóa DANG_BAN do giáo viên đang soạn (BR-HT-24).
            'courses' => Course::query()->where('sale_status', SaleStatus::DangBan->value)
                ->whereHas('currentAuthor', fn ($q) => $q->where('user_id', $user->id))
                ->with('category')->orderBy('title')->get(['id', 'title', 'slug', 'price', 'cover_path', 'category_id'])
                ->map(fn (Course $c) => [
                    'id' => $c->id, 'title' => $c->title, 'slug' => $c->slug, 'price' => $c->price,
                    'cover' => CourseCoverUrl::for($c),
                ])->values(),
        ]);
    }
}
