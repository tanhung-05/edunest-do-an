<?php

namespace App\Actions\M01;

use App\Models\User;
use App\Support\Tx;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * QT-01 A15 — sửa họ tên, avatar của chính mình; không đổi email đăng nhập (BR-HT-55).
 * Tệp lưu trước giao dịch (không I/O trong closure ngoài CSDL); tệp cũ xóa sau commit.
 */
final class UpdateOwnProfile
{
    public function handle(User $actor, string $name, ?UploadedFile $avatar): User
    {
        $newPath = $avatar?->store('avatars', 'public');
        $old = null;

        $user = Tx::run(function () use ($actor, $name, $newPath, &$old) {
            /** @var User $me */
            $me = User::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $me->name = trim($name);
            if ($newPath !== null && $newPath !== false) {
                $old = $me->avatar_path;
                $me->avatar_path = $newPath;
            }
            $me->save();

            return $me;
        });

        if ($old !== null) {
            Storage::disk('public')->delete($old);
        }

        return $user;
    }
}
