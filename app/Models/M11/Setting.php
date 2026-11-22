<?php

namespace App\Models\M11;

use Illuminate\Database\Eloquent\Model;

/**
 * L36 settings (CR-22).
 *
 * @property int $id
 * @property string $key
 * @property mixed $value
 * @property int|null $updated_by_id
 */
class Setting extends Model
{
    protected $fillable = ['key', 'value', 'updated_by_id'];

    /** Giá trị mặc định (02 L36). Từng khóa bật sửa khi có điểm sử dụng ở mốc tương ứng. */
    public const DEFAULTS = [
        'order.payment_timeout_minutes' => 30,
        'grant.tries' => 4,
        'grant.fault_mode' => false,
        'device.max_active' => 2,
        'cert.lookup_per_minute' => 30,
        // P8 M25 (CR-239): cờ AI toàn hệ thống và hạn mức lượt hỏi theo ngày giờ VN.
        'ai.enabled' => true,
        'ai.daily_limit_user' => 20,
        'ai.daily_limit_guest' => 5,
        // P8 M27 (CR-100, CR-245): số bài cộng đồng tối đa mỗi học viên trong 60 phút.
        'community.posts_per_hour' => 5,
    ];

    /**
     * Khóa sửa được ở mốc hiện tại (P0: chưa khóa nào có điểm sử dụng; thêm từ P1).
     *
     * @var list<string>
     */
    protected static array $editable = [];

    public static function isEditable(string $key): bool
    {
        return in_array($key, static::$editable, true);
    }

    public static function valueFor(string $key): mixed
    {
        return static::query()->where('key', $key)->first()->value ?? self::DEFAULTS[$key] ?? null;
    }

    public static function number(string $key): int
    {
        $value = self::valueFor($key);

        return is_int($value) && $value > 0 ? $value : (int) (self::DEFAULTS[$key] ?? 0);
    }

    public static function grantFailureMode(): bool
    {
        return config()->boolean('app.demo', false) && self::valueFor('grant.fault_mode') === true;
    }

    protected function casts(): array
    {
        return ['value' => 'json'];
    }
}
