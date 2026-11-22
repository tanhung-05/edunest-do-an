<?php

namespace App\Actions\M03;

use App\Enums\M01\RoleCode;
use App\Enums\M03\LearningMode;
use App\Enums\M03\SaleStatus;
use App\Enums\M05\CourseVersionStatus;
use App\Models\M03\Course;
use App\Models\M03\CourseAuthor;
use App\Models\M05\CourseVersion;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Str;

/**
 * PB-1/BAN-1 (QT-03 b1–3): Giáo vụ tạo khóa `CHUA_BAN`, bản 1 `NHAP` và giao người soạn trong một giao dịch.
 * Khóa nhãn (categories → levels) trước khi chèn khóa mới (05 §1).
 */
final class AssignCourseAuthoring
{
    /**
     * @param  array{title: string, category_id: int, level_id: int, learning_mode: LearningMode, price: int, original_price?: int|null, access_months?: int|null, extension_price?: int|null, author_id: int, due_date?: string|null}  $data
     */
    public function handle(User $actor, array $data): Course
    {
        CoursePricing::validate($data['price'], $data['original_price'] ?? null, $data['access_months'] ?? null, $data['extension_price'] ?? null);

        for ($try = 1; ; $try++) {
            try {
                return Tx::run(function () use ($actor, $data) {
                    CourseTaxonomy::lockAndCheck($data['category_id'], $data['level_id']);
                    $author = User::query()->whereKey($data['author_id'])->first();
                    if ($author === null || ! $author->hasRole(RoleCode::GiaoVien) || ! $author->isActive()) {
                        throw new DomainRejection('Người soạn phải là tài khoản Giáo viên đang hoạt động.', 'author_id');
                    }

                    $course = Course::query()->create([
                        'title' => trim($data['title']),
                        'slug' => self::uniqueSlug($data['title']),
                        'category_id' => $data['category_id'],
                        'level_id' => $data['level_id'],
                        'learning_mode' => $data['learning_mode'],
                        'price' => $data['price'],
                        'original_price' => $data['original_price'] ?? null,
                        'access_months' => $data['access_months'] ?? null,
                        'extension_price' => $data['extension_price'] ?? null,
                        'sale_status' => SaleStatus::ChuaBan,
                        'created_by_id' => $actor->id,
                    ]);
                    CourseVersion::query()->create(['course_id' => $course->id, 'version_no' => 1, 'status' => CourseVersionStatus::Nhap]);
                    CourseAuthor::query()->create([
                        'course_id' => $course->id,
                        'user_id' => $author->id,
                        'due_date' => $data['due_date'] ?? null,
                        'assigned_by_id' => $actor->id,
                        'assigned_at' => now(),
                    ]);
                    Audit::log('COURSE_ASSIGN', $actor, $course, null, [
                        'title' => $course->title, 'author_id' => $author->id, 'due_date' => $data['due_date'] ?? null,
                        'price' => $course->price, 'sale_status' => SaleStatus::ChuaBan->value,
                    ]);

                    return $course;
                });
            } catch (QueryException $e) {
                // 1062 trên slug (hai khóa cùng tên đồng thời): sinh lại đường dẫn tối đa 3 lần (05 §1).
                if (Tx::code($e) !== 1062 || $try >= 3) {
                    throw $e;
                }
            }
        }
    }

    /** CR-116, AC-03-77: đường dẫn duy nhất; trùng thì thêm hậu tố -2, -3… */
    public static function uniqueSlug(string $title, ?int $exceptId = null): string
    {
        $base = Str::slug($title) ?: 'khoa-hoc';
        $base = mb_substr($base, 0, 200);
        $slug = $base;
        for ($n = 2; Course::query()->where('slug', $slug)->when($exceptId, fn ($q) => $q->whereKeyNot($exceptId))->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }
}
