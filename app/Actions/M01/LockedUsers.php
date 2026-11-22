<?php

namespace App\Actions\M01;

use App\Models\User;
use Illuminate\Support\Collection;

/** Khóa nhiều dòng `users` theo `id` tăng dần (05 §1) và trả bản đọc lại. */
final class LockedUsers
{
    /**
     * @param  list<int>  $ids
     * @return Collection<int, User>
     */
    public static function lock(array $ids): Collection
    {
        $ids = array_values(array_unique($ids));
        sort($ids);

        return User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id');
    }
}
