<?php

namespace App\Http\Controllers\PublicSite;

use App\Http\Controllers\Controller;
use App\Models\M10\Certificate;
use App\Models\User;
use App\Queries\M06\PublicCatalog;
use App\Queries\M06\PublicStats;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/** Trang Giới thiệu (22/11): sứ mệnh, cách EduNest vận hành, đội ngũ giảng viên có ảnh, số liệu thật. */
class AboutController extends Controller
{
    public function show(): Response
    {
        $teachers = User::query()->whereHas('teacherProfile')->with('teacherProfile')->orderBy('id')->get()
            ->map(fn (User $u) => [
                'id' => $u->id,
                'name' => $u->name,
                'photo' => $u->teacherProfile?->photo_path !== null ? Storage::disk('public')->url($u->teacherProfile->photo_path) : null,
                'bio' => filled($u->teacherProfile?->bio) ? $u->teacherProfile->bio : null,
                'achievements' => filled($u->teacherProfile?->achievements) ? $u->teacherProfile->achievements : null,
            ])->values();

        return Inertia::render('public/about', [
            'teachers' => $teachers,
            'stats' => [
                'courses' => PublicCatalog::selling()->count(),
                'students' => PublicStats::students(),
                'teachers' => $teachers->count(),
                'certificates' => Certificate::query()->count(),
            ],
        ]);
    }
}
