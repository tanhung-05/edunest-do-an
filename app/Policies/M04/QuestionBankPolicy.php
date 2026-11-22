<?php

namespace App\Policies\M04;

use App\Models\M04\QuestionBank;
use App\Models\User;
use App\Support\M03\AuthoringScope;

/** 09 §2.D: QuestionBankPolicy::view/update/createQuestion — người soạn hiện tại của khóa. */
class QuestionBankPolicy
{
    public function view(User $actor, QuestionBank $bank): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $bank->course_id);
    }

    public function update(User $actor, QuestionBank $bank): bool
    {
        return $this->view($actor, $bank);
    }

    public function createQuestion(User $actor, QuestionBank $bank): bool
    {
        return $this->view($actor, $bank);
    }
}
