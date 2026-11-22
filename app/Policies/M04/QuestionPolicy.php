<?php

namespace App\Policies\M04;

use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\User;
use App\Support\M03\AuthoringScope;

/** 04 §4b (09 §2.D, NH-66): ngân hàng và câu chỉ cho người soạn hiện tại của khóa sở hữu. */
class QuestionPolicy
{
    public function view(User $actor, Question $question): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $question->bank->course_id);
    }

    public function update(User $actor, Question $question): bool
    {
        return $this->view($actor, $question);
    }

    public function deactivate(User $actor, Question $question): bool
    {
        return $this->view($actor, $question);
    }

    public function reactivate(User $actor, Question $question): bool
    {
        return $this->view($actor, $question);
    }

    public static function ownsBank(User $actor, QuestionBank $bank): bool
    {
        return AuthoringScope::isCurrentAuthor($actor, (int) $bank->course_id);
    }
}
