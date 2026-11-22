<?php

use App\Actions\M04\QuestionBankActions;
use App\Enums\M01\RoleCode;
use App\Enums\M04\QuestionType;
use App\Models\M04\Question;
use App\Models\M04\QuizQuestion;
use App\Support\DomainRejection;
use App\Support\M10\QuizScore;
use Illuminate\Http\UploadedFile;
use Tests\Support\KhoaHoc;

pest()->group('P6');

beforeEach(function () {
    $this->g = taiKhoan('giaovu-p6n@edunest.test', RoleCode::GiaoVu);
    $this->t = taiKhoan('giaovien-p6n@edunest.test', RoleCode::GiaoVien);
    $this->course = KhoaHoc::giao($this->g, $this->t);
    $this->bank = app(QuestionBankActions::class)->createBank($this->t, $this->course, 'Loại N');
});

/** Bản chụp câu như trong quiz để chấm. */
function chup(Question $q): QuizQuestion
{
    $q->load('options');

    return new QuizQuestion(['snapshot' => [
        'type' => $q->type->value, 'stem' => $q->stem,
        'options' => $q->options->map(fn ($o) => ['id' => $o->id, 'content' => $o->content, 'is_correct' => $o->is_correct, 'position' => $o->position])->all(),
    ]]);
}

it('CR-69 Điền từ: chuẩn hóa NFC, gộp khoảng trắng, không phân biệt hoa thường, giữ dấu', function () {
    $q = app(QuestionBankActions::class)->createQuestion($this->t, $this->bank, [
        'type' => QuestionType::DienTu, 'stem' => 'Thủ đô Việt Nam là ___',
        'options' => [['content' => 'Hà Nội', 'is_correct' => true], ['content' => 'Ha Noi city', 'is_correct' => true]],
    ]);
    $s = chup($q);
    expect(QuizScore::correct($s, '  hà   NỘI '))->toBeTrue()
        ->and(QuizScore::correct($s, "Ha\u{0300} Nội"))->toBeTrue()
        ->and(QuizScore::correct($s, 'Ha Noi'))->toBeFalse()
        ->and(QuizScore::correct($s, 'ha noi CITY'))->toBeTrue();
    expect(fn () => app(QuestionBankActions::class)->createQuestion($this->t, $this->bank, [
        'type' => QuestionType::DienTu, 'stem' => 'Trùng',
        'options' => [['content' => 'Hà Nội', 'is_correct' => true], ['content' => 'hà  nội', 'is_correct' => true]],
    ]))->toThrow(DomainRejection::class);
});

it('M04 (N) Sắp xếp: đúng khi đủ hoán vị theo thứ tự đúng; thiếu phần tử là không hợp lệ', function () {
    $q = app(QuestionBankActions::class)->createQuestion($this->t, $this->bank, [
        'type' => QuestionType::SapXep, 'stem' => 'Sắp tăng dần',
        'options' => [['content' => '1', 'is_correct' => true], ['content' => '2', 'is_correct' => true], ['content' => '3', 'is_correct' => true]],
    ]);
    $s = chup($q);
    $ids = $q->options()->orderBy('position')->pluck('id')->all();
    expect(QuizScore::correct($s, $ids))->toBeTrue()
        ->and(QuizScore::correct($s, array_reverse($ids)))->toBeFalse()
        ->and(QuizScore::answer($s, [$ids[0], $ids[1]]))->toBeNull();
});

it('CR-68 Nhập CSV: có dòng lỗi thì không nhập dòng nào và báo theo dòng; tệp đúng nhập đủ', function () {
    $head = 'loai,do_kho,noi_dung,diem,goi_y,loi_giai,lua_chon_1,lua_chon_2,lua_chon_3,lua_chon_4,lua_chon_5,lua_chon_6,lua_chon_7,lua_chon_8,dap_an';
    $bad = UploadedFile::fake()->createWithContent('cau.csv', $head."\nMOT_DAP_AN,DE,Câu 1,1,,,A,B,,,,,,,2\nMOT_DAP_AN,DE,Câu 2,1,,,A,B,,,,,,,5\n");
    $this->signIn($this->t)->post(route('teacher.question-banks.import', $this->bank), ['file' => $bad])->assertRedirect()
        ->assertSessionHas('import.errors', fn ($errors) => $errors[0]['row'] === 3);
    expect(Question::query()->count())->toBe(0);
    $good = UploadedFile::fake()->createWithContent('cau.csv', $head."\nMOT_DAP_AN,DE,Câu 1,1,,,A,B,,,,,,,2\nDIEN_TU,KHO,Nước là ___,1,,,H2O,,,,,,,,\nSAP_XEP,DE,Sắp,1,,,a,b,c,,,,,,\n");
    $this->signIn($this->t)->post(route('teacher.question-banks.import', $this->bank), ['file' => $good])->assertRedirect();
    expect(Question::query()->count())->toBe(3)
        ->and(Question::query()->orderBy('id')->pluck('type')->map->value->all())->toBe(['MOT_DAP_AN', 'DIEN_TU', 'SAP_XEP']);
    $this->signIn($this->t)->get(route('teacher.question-banks.template'))->assertOk();
});
