<?php

/*
| QT-04 Ngân hàng câu hỏi — nhóm P1 của 07a.
*/

use App\Actions\M04\QuestionBankActions;
use App\Actions\M05\CreateDraftVersion;
use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionDifficulty;
use App\Enums\M04\QuestionStatus;
use App\Enums\M04\QuestionType;
use App\Enums\M05\CourseVersionStatus;
use App\Enums\M05\LessonType;
use App\Models\M02\Tag;
use App\Models\M04\Question;
use App\Models\M04\QuestionBank;
use App\Models\M04\QuizQuestion;
use App\Models\M05\Lesson;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Support\KhoaHoc;

pest()->group('P1');

beforeEach(function () {
    Storage::fake('public');
    $this->lan = taiKhoan('lan.pham@edunest.test', RoleCode::GiaoVu);
    $this->minh = taiKhoan('minh@edunest.test', RoleCode::GiaoVu);
    $this->a = taiKhoan('hoa.tran@edunest.test', RoleCode::GiaoVien);
    $this->b = taiKhoan('binh.gv@edunest.test', RoleCode::GiaoVien);
    $this->an = taiKhoan('an.nguyen@edunest.test', RoleCode::HocVien);
    $this->k = KhoaHoc::giao($this->lan, $this->a);
});

function nh(User $u, string $method, string $url, array $data = [])
{
    return test()->signIn($u)->json($method, $url, $data);
}

/** @param list<string> $labels */
function phuongAn(array $labels, array $dung): array
{
    return array_map(fn ($l) => ['content' => $l, 'is_correct' => in_array($l, $dung, true)], $labels);
}

function themCau(User $u, QuestionBank $bank, array $data)
{
    return nh($u, 'POST', route('teacher.question-banks.questions.store', $bank), array_merge([
        'type' => 'MOT_DAP_AN', 'stem' => 'Câu?', 'options' => phuongAn(['A', 'B', 'C', 'D'], ['B']),
    ], $data));
}

it('AC-04-01 A tạo ngân hàng, thêm câu một đáp án (điểm mặc định 1, thẻ Este), chọn vào quiz bài 12 và gửi duyệt', function () {
    $este = Tag::query()->create(['name' => 'Este', 'slug' => 'este']);
    $v = KhoaHoc::duDieuKien($this->k, 1);
    $b12 = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
    KhoaHoc::quiz($b12, []);

    nh($this->a, 'POST', route('teacher.courses.question-banks.store', $this->k), ['name' => 'Chương 1'])->assertRedirect();
    $bank = QuestionBank::query()->sole();
    themCau($this->a, $bank, [
        'stem' => 'Công thức của etanol?', 'options' => [
            ['content' => 'CH3OH', 'is_correct' => false], ['content' => 'C2H5OH', 'is_correct' => true],
            ['content' => 'C3H7OH', 'is_correct' => false], ['content' => 'CH3COOH', 'is_correct' => false],
        ],
        'difficulty' => 'TRUNG_BINH', 'hint' => 'Hai cacbon', 'explanation' => 'Etanol là C2H5OH', 'tag_ids' => [$este->id],
    ])->assertRedirect();
    $c = Question::query()->sole();
    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12), ['question_ids' => [$c->id]])->assertRedirect();
    nh($this->a, 'POST', route('teacher.course-versions.submit', $v))->assertRedirect();

    expect((string) $c->points)->toBe('1.00')->and($c->tags->pluck('name')->all())->toBe(['Este'])
        ->and($c->difficulty)->toBe(QuestionDifficulty::TrungBinh)
        ->and($v->fresh()->status)->toBe(CourseVersionStatus::ChoDuyet)
        ->and(collect(QuizQuestion::query()->sole()->snapshot['options'])->firstWhere('is_correct', true)['content'])->toBe('C2H5OH');
});

it('AC-04-02/03 Câu nhiều đáp án {A, C} và câu đúng/sai lưu được', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    themCau($this->a, $bank, ['type' => 'NHIEU_DAP_AN', 'options' => phuongAn(['A', 'B', 'C', 'D'], ['A', 'C'])])->assertRedirect();
    themCau($this->a, $bank, ['type' => 'DUNG_SAI', 'options' => phuongAn(['Đúng', 'Sai'], ['Đúng'])])->assertRedirect();

    [$nhieu, $ds] = Question::query()->with('options')->orderBy('id')->get()->all();
    expect($nhieu->options->where('is_correct', true)->pluck('content')->all())->toBe(['A', 'C'])
        ->and($ds->options->pluck('content')->all())->toBe(['Đúng', 'Sai'])
        ->and($ds->options->firstWhere('is_correct', true)->content)->toBe('Đúng');
});

it('AC-04-04 Đổi độ khó C1 từ DE thành KHO, các câu khác không đổi', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 2);
    [$c1, $c2] = Question::query()->orderBy('id')->get()->all();
    $c1->forceFill(['difficulty' => 'DE'])->save();
    nh($this->a, 'POST', route('teacher.questions.update', $c1), ['difficulty' => 'KHO'])->assertRedirect();
    expect($c1->fresh()->difficulty)->toBe(QuestionDifficulty::Kho)->and($c2->fresh()->difficulty)->toBe($c2->difficulty);
});

it('AC-04-05 Ngừng dùng C2 chưa nằm trong quiz: NGUNG_DUNG, biến khỏi danh sách chọn, bản ghi còn', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 2);
    $c2 = Question::query()->orderByDesc('id')->first();
    nh($this->a, 'POST', route('teacher.questions.deactivate', $c2))->assertRedirect();
    expect($c2->fresh()->status)->toBe(QuestionStatus::NgungDung)->and(Question::query()->count())->toBe(2);
    $this->signIn($this->a)->get(route('teacher.courses.show', $this->k))
        ->assertInertia(fn ($p) => $p->has('bankQuestions.0.questions', 1));
});

it('AC-04-06 Sửa câu 3 sau khi bản 1 công bố không đổi quiz bản 1', function () {
    $v = KhoaHoc::duDieuKien($this->k, 1);
    $b12 = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $c3 = KhoaHoc::cau($bank, $this->a, 'Câu 3 cũ?', 'B');
    KhoaHoc::quiz($b12, [$c3->id]);
    KhoaHoc::guiDuyet($v, $this->a);
    KhoaHoc::duyet($v, $this->minh);
    KhoaHoc::congBo($v, $this->lan);

    nh($this->a, 'POST', route('teacher.questions.update', $c3), ['stem' => 'Câu 3 mới?', 'options' => phuongAn(['A', 'B', 'C', 'D'], ['C'])])->assertRedirect();

    $snap = QuizQuestion::query()->sole()->snapshot;
    expect($snap['stem'])->toBe('Câu 3 cũ?')->and(collect($snap['options'])->firstWhere('is_correct', true)['content'])->toBe('B')
        ->and($c3->fresh()->stem)->toBe('Câu 3 mới?');
});

it('AC-04-07 Lọc độ khó + thẻ, lọc thẻ, tìm theo từ', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $este = Tag::query()->create(['name' => 'Este', 'slug' => 'este']);
    $axit = Tag::query()->create(['name' => 'Axit', 'slug' => 'axit']);
    $ids = [];
    foreach (range(1, 30) as $i) {
        $q = KhoaHoc::cau($bank, $this->a, sprintf('q%02d', $i).(in_array($i, [3, 18]) ? ' etanol' : '').'?');
        $q->forceFill(['difficulty' => $i <= 12 ? 'KHO' : 'DE'])->save();
        if ($i <= 5) {
            $q->tags()->attach($este);
        } elseif ($i <= 9) {
            $q->tags()->attach($axit);
        }
        $ids[$i] = $q->id;
    }
    $lay = fn (array $query) => collect($this->signIn($this->a)->get(route('teacher.question-banks.show', ['bank' => $bank, ...$query]))
        ->viewData('page')['props']['questions']['data'])->pluck('id')->all();

    expect($lay(['difficulty' => 'KHO', 'tag' => $este->id]))->toBe(array_map(fn ($i) => $ids[$i], [1, 2, 3, 4, 5]))
        ->and($lay(['tag' => $axit->id]))->toBe(array_map(fn ($i) => $ids[$i], [6, 7, 8, 9]))
        ->and($lay(['q' => 'etanol']))->toBe([$ids[3], $ids[18]]);
});

it('AC-04-08 Độ khó RAT_KHO bị 422; KHO được', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    themCau($this->a, $bank, ['difficulty' => 'RAT_KHO'])->assertStatus(422);
    themCau($this->a, $bank, ['difficulty' => 'KHO'])->assertRedirect();
    expect(Question::query()->count())->toBe(1);
});

it('AC-04-09 Bỏ trống điểm thành 1; điểm 2 giữ 2', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    themCau($this->a, $bank, ['points' => null])->assertRedirect();
    themCau($this->a, $bank, ['points' => 2])->assertRedirect();
    expect(Question::query()->orderBy('id')->pluck('points')->map(fn ($p) => (string) $p)->all())->toBe(['1.00', '2.00']);
});

it('AC-04-10 Chọn câu của K vào quiz được; câu M1 của khóa L bị 422', function () {
    $b12 = KhoaHoc::bai(KhoaHoc::chuong(KhoaHoc::banMo($this->k)), 'Bài 12', LessonType::Quiz);
    $c = KhoaHoc::cau(KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0), $this->a, 'K?');
    $l = KhoaHoc::giao($this->lan, $this->a, ['title' => 'L']);
    $m1 = KhoaHoc::cau(KhoaHoc::nganHang($l, $this->a, 'Chương 1', 0), $this->a, 'M1?');
    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12), ['question_ids' => [$c->id]])->assertRedirect();
    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12), ['question_ids' => [$m1->id]])->assertStatus(422);
    expect($b12->quiz()->first()->items()->pluck('question_id')->all())->toBe([$c->id]);
});

it('AC-04-11 Ngân hàng thứ hai của K: câu mỗi ngân hàng tách biệt', function () {
    $b1 = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 2);
    nh($this->a, 'POST', route('teacher.courses.question-banks.store', $this->k), ['name' => 'Chương 2'])->assertRedirect();
    $b2 = QuestionBank::query()->where('name', 'Chương 2')->sole();
    KhoaHoc::cau($b2, $this->a, 'C2?');
    expect(QuestionBank::query()->where('course_id', $this->k->id)->count())->toBe(2)
        ->and($b1->questions()->count())->toBe(2)->and($b2->questions()->count())->toBe(1);
});

it('AC-04-28 Lan và An gọi mở ngân hàng, thêm câu: 403', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $this->signIn($this->lan)->get(route('teacher.question-banks.show', $bank))->assertForbidden();
    themCau($this->lan, $bank, [])->assertForbidden();
    $this->signIn($this->an)->get(route('teacher.question-banks.show', $bank))->assertForbidden();
    expect(Question::query()->count())->toBe(0);
});

it('AC-04-29 B (không được giao K) xem và thêm câu: 403', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $this->signIn($this->b)->get(route('teacher.question-banks.show', $bank))->assertForbidden();
    themCau($this->b, $bank, [])->assertForbidden();
    expect(Question::query()->count())->toBe(0);
});

it('AC-04-36 Đổi tên ngân hàng giữ câu và liên kết quiz', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 12);
    nh($this->a, 'PATCH', route('teacher.question-banks.update', $bank), ['name' => 'Chương 1 – Este'])->assertRedirect();
    expect($bank->fresh()->name)->toBe('Chương 1 – Este')->and($bank->questions()->count())->toBe(12);
});

it('AC-04-43 Một đáp án: 1 và 7 lựa chọn bị 422; 2 và 6 được; 2 lựa chọn đúng bị 422', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $nhan = fn (int $n) => array_map(fn ($i) => chr(64 + $i), range(1, $n));
    themCau($this->a, $bank, ['options' => phuongAn($nhan(1), ['A'])])->assertStatus(422)->assertJsonValidationErrors('options');
    themCau($this->a, $bank, ['options' => phuongAn($nhan(7), ['A'])])->assertStatus(422);
    themCau($this->a, $bank, ['options' => phuongAn($nhan(2), ['A'])])->assertRedirect();
    themCau($this->a, $bank, ['options' => phuongAn($nhan(6), ['A'])])->assertRedirect();
    themCau($this->a, $bank, ['options' => phuongAn($nhan(4), ['A', 'B'])])->assertStatus(422);
    expect(Question::query()->count())->toBe(2);
});

it('AC-04-44 Nhiều đáp án: 0 đúng bị 422; 1 đúng được; 7 lựa chọn bị 422', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    themCau($this->a, $bank, ['type' => 'NHIEU_DAP_AN', 'options' => phuongAn(['A', 'B', 'C', 'D'], [])])->assertStatus(422);
    themCau($this->a, $bank, ['type' => 'NHIEU_DAP_AN', 'options' => phuongAn(['A', 'B', 'C', 'D'], ['A'])])->assertRedirect();
    themCau($this->a, $bank, ['type' => 'NHIEU_DAP_AN', 'options' => phuongAn(['A', 'B', 'C', 'D', 'E', 'F', 'G'], ['A'])])->assertStatus(422);
    expect(Question::query()->count())->toBe(1);
});

it('AC-04-45 Điểm 0 và -1 bị 422; điểm 1 được', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    themCau($this->a, $bank, ['points' => 0])->assertStatus(422)->assertJsonValidationErrors('points');
    themCau($this->a, $bank, ['points' => -1])->assertStatus(422);
    themCau($this->a, $bank, ['points' => 1])->assertRedirect();
    expect(Question::query()->count())->toBe(1);
});

it('AC-04-46 Ảnh câu hỏi 5 MB được; 6 MB bị 422', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $c3 = KhoaHoc::cau($bank, $this->a, 'C3?');
    $this->signIn($this->a)->post(route('teacher.questions.update', $c3), ['image' => UploadedFile::fake()->image('anh-a.png')->size(5 * 1024)])->assertRedirect()->assertSessionHasNoErrors();
    $this->signIn($this->a)->postJson(route('teacher.questions.update', $c3), ['image' => UploadedFile::fake()->image('anh-b.png')->size(6 * 1024)])->assertStatus(422);
    expect($c3->fresh()->image_path)->not->toBeNull();
});

it('AC-04-47 Trùng tên ngân hàng trong cùng khóa bị từ chối; khác khóa được', function () {
    KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $c2 = KhoaHoc::nganHang($this->k, $this->a, 'Chương 2', 0);
    $m = KhoaHoc::giao($this->lan, $this->b, ['title' => 'M']);
    nh($this->a, 'POST', route('teacher.courses.question-banks.store', $this->k), ['name' => 'Chương 1'])->assertStatus(422)->assertJsonValidationErrors('name');
    nh($this->a, 'PATCH', route('teacher.question-banks.update', $c2), ['name' => 'Chương 1'])->assertStatus(422);
    nh($this->b, 'POST', route('teacher.courses.question-banks.store', $m), ['name' => 'Chương 1'])->assertRedirect();
    expect($c2->fresh()->name)->toBe('Chương 2')->and(QuestionBank::query()->where('name', 'Chương 1')->count())->toBe(2);
});

it('AC-04-48 Ngừng dùng câu nằm trong quiz bản NHAP, TRA_LAI, CHO_DUYET, DA_DUYET: đều bị từ chối', function () {
    $qs = [];
    foreach (['NHAP', 'TRA_LAI', 'CHO_DUYET', 'DA_DUYET'] as $i => $st) {
        $k = KhoaHoc::giao($this->lan, $this->a, ['title' => "K{$i}"]);
        $v = KhoaHoc::duDieuKien($k, 1);
        $bai = KhoaHoc::bai($v->sections()->first(), 'Bài 12', LessonType::Quiz);
        $q = KhoaHoc::cau(KhoaHoc::nganHang($k, $this->a, 'Chương 1', 0), $this->a, "Q{$i}?");
        KhoaHoc::quiz($bai, [$q->id]);
        $v->forceFill(['status' => $st])->save();
        $qs[] = $q;
    }
    foreach ($qs as $q) {
        nh($this->a, 'POST', route('teacher.questions.deactivate', $q))->assertStatus(422);
    }
    expect(Question::query()->whereIn('id', collect($qs)->pluck('id'))->pluck('status')->unique()->all())->toBe([QuestionStatus::DangDung]);
});

it('AC-04-49/50/51 Câu chỉ nằm trong bản đã công bố ngừng dùng được; không chọn được vào quiz; dùng lại rồi chọn được', function () {
    $v1 = KhoaHoc::duDieuKien($this->k, 1);
    $bai = KhoaHoc::bai($v1->sections()->first(), 'Bài 12', LessonType::Quiz);
    $q5 = KhoaHoc::cau(KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0), $this->a, 'Q5?');
    KhoaHoc::quiz($bai, [$q5->id]);
    KhoaHoc::guiDuyet($v1, $this->a);
    KhoaHoc::duyet($v1, $this->minh);
    KhoaHoc::congBo($v1, $this->lan);
    $v2 = app(CreateDraftVersion::class)->handle($this->a, $this->k->fresh())['version'];
    $b12v2 = Lesson::query()->where('course_version_id', $v2->id)->where('title', 'Bài 12')->sole();
    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12v2), ['question_ids' => []])->assertRedirect();

    nh($this->a, 'POST', route('teacher.questions.deactivate', $q5))->assertRedirect();
    expect($q5->fresh()->status)->toBe(QuestionStatus::NgungDung)
        ->and(QuizQuestion::query()->where('question_id', $q5->id)->whereNotNull('snapshot')->count())->toBe(1);

    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12v2), ['question_ids' => [$q5->id]])->assertStatus(422);
    nh($this->a, 'POST', route('teacher.questions.reactivate', $q5))->assertRedirect();
    nh($this->a, 'PUT', route('teacher.lessons.quiz.questions', $b12v2), ['question_ids' => [$q5->id]])->assertRedirect();
    expect($q5->fresh()->status)->toBe(QuestionStatus::DangDung);
});

it('AC-04-52/53 Lan, An, B gọi ngừng dùng hoặc dùng lại: 403', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $q6 = KhoaHoc::cau($bank, $this->a, 'Q6?');
    $q5 = KhoaHoc::cau($bank, $this->a, 'Q5?');
    $q5->forceFill(['status' => 'NGUNG_DUNG'])->save();
    foreach ([$this->lan, $this->an, $this->b] as $u) {
        nh($u, 'POST', route('teacher.questions.deactivate', $q6))->assertForbidden();
        nh($u, 'POST', route('teacher.questions.reactivate', $q5))->assertForbidden();
    }
    expect($q6->fresh()->status)->toBe(QuestionStatus::DangDung)->and($q5->fresh()->status)->toBe(QuestionStatus::NgungDung);
});

it('AC-04-54 Sửa C1 thành 1 lựa chọn hoặc điểm 0 bị 422; 3 lựa chọn đúng B được', function () {
    $c1 = KhoaHoc::cau(KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0), $this->a, 'C1?');
    nh($this->a, 'POST', route('teacher.questions.update', $c1), ['options' => phuongAn(['B'], ['B'])])->assertStatus(422);
    nh($this->a, 'POST', route('teacher.questions.update', $c1), ['points' => 0])->assertStatus(422);
    expect($c1->fresh()->options()->count())->toBe(4)->and((string) $c1->fresh()->points)->toBe('1.00');
    nh($this->a, 'POST', route('teacher.questions.update', $c1), ['options' => phuongAn(['A', 'B', 'C'], ['B'])])->assertRedirect();
    expect($c1->fresh()->options()->count())->toBe(3);
});

it('AC-04-60 Bộ lọc và trang nằm trên URL: mở lại URL thấy đúng bộ lọc và trang 2', function () {
    $bank = KhoaHoc::nganHang($this->k, $this->a, 'Chương 1', 0);
    $este = Tag::query()->create(['name' => 'Este', 'slug' => 'este']);
    foreach (range(1, 25) as $i) {
        $q = app(QuestionBankActions::class)->createQuestion($this->a, $bank, [
            'type' => QuestionType::DungSai, 'stem' => "DS {$i}?", 'difficulty' => QuestionDifficulty::Kho,
            'options' => phuongAn(['Đúng', 'Sai'], ['Đúng']), 'tag_ids' => [$este->id],
        ]);
    }
    $url = route('teacher.question-banks.show', ['bank' => $bank, 'type' => 'DUNG_SAI', 'difficulty' => 'KHO', 'tag' => $este->id, 'page' => 2]);
    $this->signIn($this->a)->get($url)->assertInertia(fn ($p) => $p
        ->where('filters.type', 'DUNG_SAI')->where('filters.difficulty', 'KHO')->where('filters.tag', (string) $este->id)
        ->where('questions.current_page', 2)->has('questions.data', 5));
});
