<?php

namespace App\Actions\Fortify;

use App\Concerns\PasswordValidationRules;
use App\Enums\M01\AccountStatus;
use App\Enums\M01\RoleCode;
use App\Models\M01\Role;
use App\Models\User;
use App\Support\Tx;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\CreatesNewUsers;

/**
 * TK-1 RegisterStudent — Khách tự đăng ký: đúng một vai Học viên, bỏ qua mọi tham số vai (BR-HT-12).
 * Email chuẩn hóa, duy nhất không phân biệt hoa/thường (CR-05); 1062 → "email đã dùng".
 */
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    /**
     * @param  array<string, mixed>  $input
     */
    public function create(array $input): User
    {
        $input['email'] = User::normalizeEmail((string) ($input['email'] ?? ''));
        $input['name'] = trim((string) ($input['name'] ?? ''));

        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class, 'email')],
            'password' => $this->passwordRules(),
        ])->validate();

        try {
            return Tx::run(function () use ($data) {
                $user = new User(['name' => $data['name'], 'email' => $data['email'], 'password' => $data['password']]);
                $user->status = AccountStatus::HoatDong;
                $user->auth_generation = 0;
                $user->must_change_password = false;
                $user->save();
                $user->roles()->attach(Role::idFor(RoleCode::HocVien), ['assigned_by_id' => null, 'assigned_at' => now()]);

                return $user;
            });
        } catch (QueryException $e) {
            if (Tx::code($e) === 1062) {
                throw ValidationException::withMessages(['email' => __('validation.unique', ['attribute' => 'email'])]);
            }
            throw $e;
        }
    }
}
