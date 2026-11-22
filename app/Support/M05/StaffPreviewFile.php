<?php

namespace App\Support\M05;

use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Support\M09\LearningFile;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\URL;

/**
 * Nhánh `staff-preview` (04 §3, NH-61): URL tệp cho nhân sự xem trước/xem để duyệt, không cần ghi danh.
 * Quyền kiểm cả lúc cấp URL lẫn lúc phục vụ; URL ký 15 phút và gắn phiên. Không ghi tiến độ, lượt (CR-20).
 */
final class StaffPreviewFile
{
    public static function allows(User $user, CourseVersion $version): bool
    {
        return Gate::forUser($user)->allows('preview', $version) || Gate::forUser($user)->allows('viewForReview', $version);
    }

    /**
     * Thêm `files` (video, pdf, resources) vào từng bài của cây VersionTree::sections.
     *
     * @param  array<int, array<string, mixed>>  $sections
     * @return array<int, array<string, mixed>>
     */
    public static function attach(Request $request, CourseVersion $version, array $sections): array
    {
        $user = $request->user();
        if (! $user instanceof User || ! self::allows($user, $version)) {
            return $sections;
        }
        $url = fn (int $lesson, string $file) => URL::temporarySignedRoute('course-versions.preview.files', now()->addMinutes(15), [
            'version' => $version->id, 'lesson' => $lesson, 'file' => $file, 'sid' => self::sessionHash($request),
        ]);

        return array_map(function (array $section) use ($url) {
            $section['lessons'] = array_map(function (array $lesson) use ($url) {
                $resources = [];
                foreach ($lesson['resources'] as $resource) {
                    $resources[(string) $resource['id']] = $url($lesson['id'], (string) $resource['id']);
                }
                $lesson['files'] = [
                    'video' => $lesson['has_video'] ? $url($lesson['id'], 'video') : null,
                    'pdf' => $lesson['has_pdf'] ? $url($lesson['id'], 'pdf') : null,
                    'resources' => $resources,
                ];

                return $lesson;
            }, $section['lessons']);

            return $section;
        }, $sections);
    }

    public static function sessionHash(Request $request): string
    {
        return LearningFile::hash('staff-preview-session', $request->session()->getId());
    }
}
