<?php

namespace App\Http\Controllers;

use App\Enums\M05\LessonType;
use App\Models\M05\CourseVersion;
use App\Models\M05\Lesson;
use App\Models\User;
use App\Support\M05\StaffPreviewFile;
use App\Support\M09\SafeFileResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Phục vụ tệp nhánh `staff-preview` (04 §3, NH-61): kiểm lại quyền, trạng thái bản và bài còn sống của đúng bản. */
final class StaffPreviewFileController extends Controller
{
    public function __invoke(Request $request, CourseVersion $version, Lesson $lesson, string $file): BinaryFileResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User && StaffPreviewFile::allows($user, $version), 403);
        $sid = $request->query('sid');
        abort_unless(is_string($sid) && hash_equals(StaffPreviewFile::sessionHash($request), $sid), 403, 'Liên kết không thuộc phiên này.');
        abort_unless($lesson->course_version_id === $version->id && $lesson->removed_at === null, 404);

        $resource = null;
        if ($file === 'video') {
            $path = $lesson->type === LessonType::Video ? $lesson->video_path : null;
            $mime = 'video/mp4';
        } elseif ($file === 'pdf') {
            $path = $lesson->type === LessonType::Pdf ? $lesson->pdf_path : null;
            $mime = 'application/pdf';
        } else {
            abort_unless(ctype_digit($file), 404);
            $resource = $lesson->resources()->whereNull('removed_at')->findOrFail((int) $file);
            $path = $resource->file_path;
            $mime = $resource->mime_type;
        }
        $disk = Storage::disk('local');
        abort_if($path === null || ! $disk->exists($path), 404);

        return SafeFileResponse::make($disk->path($path), $mime, $resource?->original_name);
    }
}
