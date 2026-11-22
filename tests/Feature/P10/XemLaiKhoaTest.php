<?php

use Tests\Support\HocTap;

pest()->group('P10');

it('UAT 22/11: khóa đang học có nút tiếp tục; học xong mọi bài thì có đường xem lại bài đầu tiên', function () {
    $h = new HocTap;
    $h->signIn($this)->get(route('student.courses.index'))
        ->assertInertia(fn ($p) => $p->where('courses.0.review_url', null)->whereNot('courses.0.learning_entry', null));

    foreach ([$h->read, $h->video, $h->quizLesson, $h->optional] as $lesson) {
        $h->mark($lesson);
    }
    $this->get(route('student.courses.index'))
        ->assertInertia(fn ($p) => $p->where('courses.0.learning_entry', null)
            ->where('courses.0.review_url', route('student.lessons.show', ['course' => $h->course->id, 'lesson' => $h->read->id])));
});
