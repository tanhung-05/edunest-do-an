<?php

namespace App\Support;

use Closure;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Giao dịch ghi theo ADR-007/05 §1: tối đa 3 lượt khi deadlock 1213, chờ ngẫu nhiên 50–150 ms giữa lượt.
 */
final class Tx
{
    /**
     * @template T
     *
     * @param  Closure(): T  $work
     * @return T
     */
    public static function run(Closure $work, int $attempts = 3): mixed
    {
        for ($try = 1; ; $try++) {
            try {
                // Đọc lại dưới khóa phải thấy dữ liệu đã commit mới nhất (BR-HT-60): giao dịch ghi dùng
                // READ COMMITTED, tránh ảnh chụp REPEATABLE READ tạo trước lúc lấy khóa.
                if (DB::transactionLevel() === 0 && DB::getDriverName() === 'mysql') {
                    DB::statement('SET TRANSACTION ISOLATION LEVEL READ COMMITTED');
                }

                return DB::transaction($work);
            } catch (QueryException $e) {
                if (($e->errorInfo[1] ?? null) !== 1213 || $try >= $attempts) {
                    throw $e;
                }
                usleep(random_int(50_000, 150_000));
            }
        }
    }

    /** Mã lỗi MySQL của ngoại lệ (1062 trùng khóa, 1213 deadlock…). */
    public static function code(QueryException $e): ?int
    {
        $code = $e->errorInfo[1] ?? null;

        return is_int($code) ? $code : null;
    }
}
