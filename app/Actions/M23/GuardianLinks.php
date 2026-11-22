<?php

namespace App\Actions\M23;

use App\Concerns\PasswordValidationRules;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\M23\GuardianInvite;
use App\Models\M23\GuardianLink;
use App\Models\User;
use App\Support\Audit;
use App\Support\DomainRejection;
use App\Support\Tx;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * QT-25 bước 1–4, A1/A2: mã mời, liên kết, đăng ký Phụ huynh bằng mã, gỡ liên kết (BR-MR-01..03/07, CR-99).
 * Thứ tự khóa: mã mời → cặp liên kết. Mọi kiểm vai, chủ tài khoản, hạn và lần dùng làm lại dưới khóa.
 */
final class GuardianLinks
{
    use PasswordValidationRules;

    public const VALID_DAYS = 7;

    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** BR-MR-01: chỉ Học viên tạo mã cho chính mình; hạn đúng 7 ngày kể từ mốc cấp. */
    public function issue(User $student, ?CarbonImmutable $at = null): GuardianInvite
    {
        $at ??= CarbonImmutable::now();
        if (! $student->isActive() || ! $student->hasEffectiveRole(RoleCode::HocVien)) {
            throw DomainRejection::forbidden('Chỉ học viên tạo mã mời phụ huynh cho chính mình.');
        }

        for ($try = 0; $try < 5; $try++) {
            try {
                return GuardianInvite::query()->create(['student_id' => $student->id, 'code' => self::newCode(),
                    'issued_at' => $at, 'expires_at' => $at->addDays(self::VALID_DAYS)]);
            } catch (QueryException $e) {
                if (Tx::code($e) !== 1062) {
                    throw $e;
                }
            }
        }

        throw new DomainRejection('Không tạo được mã, thử lại.');
    }

    /** Bước 3–4: Phụ huynh đã có tài khoản nhập mã. */
    public function link(User $guardian, string $code, ?CarbonImmutable $at = null): GuardianLink
    {
        $at ??= CarbonImmutable::now();

        return Tx::run(function () use ($guardian, $code, $at) {
            $fresh = User::query()->whereKey($guardian->id)->lockForUpdate()->firstOrFail();
            if (! $fresh->isActive() || ! $fresh->hasEffectiveRole(RoleCode::PhuHuynh)) {
                throw DomainRejection::forbidden('Chỉ tài khoản Phụ huynh nhập được mã mời.');
            }

            return $this->consume($fresh, $code, $at);
        });
    }

    /**
     * Bước 3a–4: Khách đăng ký Phụ huynh kèm mã. Tạo tài khoản và liên kết trong cùng một giao dịch;
     * mã bị từ chối thì không có tài khoản (AC-25-36/37/38).
     *
     * @param  array<string, mixed>  $input
     */
    public function register(array $input, ?CarbonImmutable $at = null): User
    {
        $at ??= CarbonImmutable::now();
        $input['email'] = User::normalizeEmail((string) ($input['email'] ?? ''));
        $input['name'] = trim((string) ($input['name'] ?? ''));
        $input['code'] = strtoupper(trim((string) ($input['code'] ?? '')));
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRules(),
            'code' => ['required', 'string', 'max:12'],
        ])->validate();

        try {
            return Tx::run(function () use ($data, $at) {
                $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                $user->status = AccountStatus::HoatDong;
                $user->auth_generation = 0;
                $user->must_change_password = false;
                $user->save();
                $user->roles()->attach(Role::idFor(RoleCode::PhuHuynh), ['assigned_by_id' => null, 'assigned_at' => $at]);
                $this->consume($user, $data['code'], $at);

                return $user;
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
            }
            throw $e;
        }
    }

    /** A1/A2: Học viên hoặc Phụ huynh của đúng cặp gỡ; quyền đọc và báo cáo dừng ngay (BR-MR-07). */
    public function unlink(User $actor, int $linkId, ?CarbonImmutable $at = null): GuardianLink
    {
        $at ??= CarbonImmutable::now();

        return Tx::run(function () use ($actor, $linkId, $at) {
            $link = GuardianLink::query()->whereKey($linkId)->lockForUpdate()->first();
            $isStudent = $link !== null && $link->student_id === $actor->id && $actor->hasEffectiveRole(RoleCode::HocVien);
            $isGuardian = $link !== null && $link->guardian_id === $actor->id && $actor->hasEffectiveRole(RoleCode::PhuHuynh);
            if ($link === null || ! $actor->isActive() || (! $isStudent && ! $isGuardian)) {
                throw DomainRejection::forbidden('Bạn không gỡ được liên kết này.');
            }
            if ($link->removed_at !== null) {
                return $link;
            }
            $link->forceFill(['removed_at' => $at, 'removed_by_id' => $actor->id])->save();
            Audit::log('guardian.unlink', $actor, $link, ['removed_at' => null], ['removed_at' => $at->toISOString()]);

            return $link;
        });
    }

    /** Liên kết đang hiệu lực của đúng cặp; đọc dưới khóa khi `$lock` (bước 6, BR-HT-60). */
    public static function active(User $guardian, int $studentId, bool $lock = false): ?GuardianLink
    {
        if (! $guardian->isActive() || ! $guardian->hasEffectiveRole(RoleCode::PhuHuynh)) {
            return null;
        }
        $query = GuardianLink::query()->where('guardian_id', $guardian->id)->where('student_id', $studentId)->whereNull('removed_at');

        return ($lock ? $query->lockForUpdate() : $query)->first();
    }

    private function consume(User $guardian, string $code, CarbonImmutable $at): GuardianLink
    {
        $code = strtoupper(trim($code));
        $invite = GuardianInvite::query()->where('code', $code)->lockForUpdate()->first();
        if ($invite === null) {
            throw new DomainRejection('Mã mời không đúng.', 'code');
        }
        if ($invite->used_at !== null) {
            throw new DomainRejection('Mã mời đã được dùng.', 'code');
        }
        if ($at->greaterThan($invite->expires_at)) {
            throw new DomainRejection('Mã mời đã hết hạn. Nhờ con tạo mã mới.', 'code');
        }
        if ($invite->student_id === $guardian->id) {
            throw new DomainRejection('Không liên kết với chính tài khoản của mình.', 'code');
        }
        $student = User::query()->whereKey($invite->student_id)->first();
        if ($student === null || ! $student->isActive() || ! $student->hasEffectiveRole(RoleCode::HocVien)) {
            throw new DomainRejection('Tài khoản học viên của mã này không còn hoạt động.', 'code');
        }

        $link = GuardianLink::query()->where('guardian_id', $guardian->id)->where('student_id', $invite->student_id)->lockForUpdate()->first();
        if ($link !== null && $link->removed_at === null) {
            throw new DomainRejection('Bạn đã liên kết với học viên này.', 'code');
        }
        if ($link === null) {
            $link = GuardianLink::query()->create(['guardian_id' => $guardian->id, 'student_id' => $invite->student_id,
                'guardian_invite_id' => $invite->id, 'linked_at' => $at]);
        } else {
            // AC-25-34: liên kết lại dùng cùng dòng của cặp.
            $link->forceFill(['guardian_invite_id' => $invite->id, 'linked_at' => $at, 'removed_at' => null, 'removed_by_id' => null])->save();
        }
        $invite->forceFill(['used_at' => $at, 'used_by_id' => $guardian->id])->save();
        Audit::log('guardian.link', $guardian, $link, null, ['student_id' => $invite->student_id, 'invite_id' => $invite->id]);

        return $link;
    }

    private static function newCode(): string
    {
        $code = '';
        for ($i = 0; $i < 8; $i++) {
            $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
        }

        return $code;
    }
}
